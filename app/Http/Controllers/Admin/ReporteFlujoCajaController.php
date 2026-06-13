<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Flujo de Efectivo — método indirecto, desde cgl_saldos.
 *
 * Parte de la utilidad neta del período y ajusta por movimientos en
 * capital de trabajo (CxC, CxP, inventarios) y cuentas de banco/caja
 * para llegar al efectivo neto del período.
 *
 * Secciones:
 *   A) Actividades operativas (utilidad neta + ajustes)
 *   B) Actividades de inversión (activos fijos)
 *   C) Actividades de financiamiento (patrimonio, deuda largo plazo)
 *   D) Variación neta de efectivo
 */
class ReporteFlujoCajaController extends Controller
{
    public function __invoke(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $periodos = DB::table('cgl_saldos as s')
            ->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
            ->where('s.compania_id', $companiaId)
            ->selectRaw('DISTINCT p.anio, p.mes')
            ->orderByDesc('p.anio')
            ->orderByDesc('p.mes')
            ->get();

        if ($periodos->isEmpty()) {
            return view('admin.reportes.flujo-caja', [
                'sinDatos' => true, 'periodos' => $periodos,
                'anio' => now()->year, 'mes' => now()->month, 'corte' => now(),
                'secciones' => collect(), 'efectivoInicio' => 0.0,
                'efectivoFin' => 0.0, 'variacionNeta' => 0.0,
            ]);
        }

        $anio = (int) $request->input('anio', $periodos->first()->anio);
        $mes  = (int) $request->input('mes', 0);

        if (! $periodos->contains(fn ($p) => $p->anio == $anio && $p->mes == $mes)) {
            $delAnio = $periodos->firstWhere('anio', $anio) ?? $periodos->first();
            $anio = (int) $delAnio->anio;
            $mes  = (int) $delAnio->mes;
        }

        $corte = Carbon::create($anio, $mes, 1)->endOfMonth();

        // Movimientos del período (mes actual)
        $movPeriodo = DB::table('cgl_saldos as s')
            ->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
            ->join('cgl_cuentas as c', 'c.id', '=', 's.cuenta_id')
            ->join('cgl_tipos_cuenta as t', 't.id', '=', 'c.tipo_cuenta_id')
            ->where('s.compania_id', $companiaId)
            ->where('p.anio', $anio)
            ->where('p.mes', $mes)
            ->groupBy('t.codigo', 'c.codigo', 'c.nombre')
            ->selectRaw("t.codigo as tipo, c.codigo, c.nombre, SUM(s.debito - s.credito) as deudor")
            ->get();

        // Saldo acumulado hasta el período anterior (para efectivo inicio)
        $saldoAnterior = DB::table('cgl_saldos as s')
            ->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
            ->join('cgl_cuentas as c', 'c.id', '=', 's.cuenta_id')
            ->join('cgl_tipos_cuenta as t', 't.id', '=', 'c.tipo_cuenta_id')
            ->where('s.compania_id', $companiaId)
            ->whereIn('t.codigo', ['ACTIVO'])
            ->where(fn ($q) => $q
                ->where('p.anio', '<', $anio)
                ->orWhere(fn ($q) => $q->where('p.anio', $anio)->where('p.mes', '<', $mes))
            )
            ->whereRaw("c.codigo LIKE '11%'") // Cuentas de efectivo/banco: 11xxx
            ->selectRaw('SUM(s.debito - s.credito) as deudor')
            ->value('deudor');

        $efectivoInicio = round((float) $saldoAnterior, 2);

        // ── Utilidad neta del período ──
        $ingDeudor  = (float) $movPeriodo->where('tipo', 'INGRESO')->sum('deudor');
        $costDeudor = (float) $movPeriodo->where('tipo', 'COSTO')->sum('deudor');
        $gastDeudor = (float) $movPeriodo->where('tipo', 'GASTO')->sum('deudor');
        $utilidadNeta = round(-$ingDeudor + $costDeudor + $gastDeudor, 2) * -1;

        // ── Variación en capital de trabajo ──
        $capitalTrabajo = [];

        $cxcDeudor  = (float) $movPeriodo->where('tipo', 'ACTIVO')->filter(fn($r) => str_starts_with($r->codigo, '101'))->sum('deudor');
        $cxpDeudor  = (float) $movPeriodo->where('tipo', 'PASIVO')->filter(fn($r) => str_starts_with($r->codigo, '201'))->sum('deudor');
        $invDeudor  = (float) $movPeriodo->where('tipo', 'ACTIVO')->filter(fn($r) => str_starts_with($r->codigo, '103'))->sum('deudor');

        if (abs($cxcDeudor) >= 0.01) {
            $capitalTrabajo[] = ['nombre' => '(Aumento) disminución en cuentas por cobrar', 'monto' => round(-$cxcDeudor, 2)];
        }
        if (abs($invDeudor) >= 0.01) {
            $capitalTrabajo[] = ['nombre' => '(Aumento) disminución en inventarios', 'monto' => round(-$invDeudor, 2)];
        }
        if (abs($cxpDeudor) >= 0.01) {
            $capitalTrabajo[] = ['nombre' => 'Aumento (disminución) en cuentas por pagar', 'monto' => round($cxpDeudor, 2)];
        }

        $ajusteCapital = round(array_sum(array_column($capitalTrabajo, 'monto')), 2);
        $flujoOperativo = round($utilidadNeta + $ajusteCapital, 2);

        // ── Actividades de inversión (activos fijos 12xxx) ──
        $activoFijoDeudor = (float) $movPeriodo->where('tipo', 'ACTIVO')->filter(fn($r) => str_starts_with($r->codigo, '12'))->sum('deudor');
        $flujoInversion = round(-$activoFijoDeudor, 2);
        $inversionItems = abs($activoFijoDeudor) >= 0.01
            ? [['nombre' => $activoFijoDeudor > 0 ? 'Adquisición de activos fijos' : 'Disposición de activos fijos', 'monto' => $flujoInversion]]
            : [];

        // ── Actividades de financiamiento (patrimonio 3xxx, deuda largo plazo 22xxx) ──
        $patrimonioDeudor = (float) $movPeriodo->where('tipo', 'PATRIMONIO')->sum('deudor');
        $deudaLPDeudor    = (float) $movPeriodo->where('tipo', 'PASIVO')->filter(fn($r) => str_starts_with($r->codigo, '22'))->sum('deudor');
        $flujoFinanciamiento = round(-$patrimonioDeudor + $deudaLPDeudor, 2);
        $finItems = [];
        if (abs($patrimonioDeudor) >= 0.01) {
            $finItems[] = ['nombre' => $patrimonioDeudor < 0 ? 'Aportaciones de capital' : 'Distribución de dividendos', 'monto' => round(-$patrimonioDeudor, 2)];
        }
        if (abs($deudaLPDeudor) >= 0.01) {
            $finItems[] = ['nombre' => $deudaLPDeudor > 0 ? 'Préstamos obtenidos' : 'Pago de deuda largo plazo', 'monto' => round($deudaLPDeudor, 2)];
        }

        $variacionNeta  = round($flujoOperativo + $flujoInversion + $flujoFinanciamiento, 2);
        $efectivoFin    = round($efectivoInicio + $variacionNeta, 2);

        $secciones = collect([
            [
                'titulo' => 'A. Actividades Operativas',
                'color'  => 'blue',
                'items'  => array_merge(
                    [['nombre' => 'Utilidad (pérdida) neta del período', 'monto' => $utilidadNeta, 'negrita' => true]],
                    $capitalTrabajo
                ),
                'total'  => $flujoOperativo,
                'label_total' => 'Efectivo neto de operaciones',
            ],
            [
                'titulo' => 'B. Actividades de Inversión',
                'color'  => 'amber',
                'items'  => $inversionItems ?: [['nombre' => 'Sin movimientos de inversión en el período', 'monto' => 0, 'vacio' => true]],
                'total'  => $flujoInversion,
                'label_total' => 'Efectivo neto de inversión',
            ],
            [
                'titulo' => 'C. Actividades de Financiamiento',
                'color'  => 'emerald',
                'items'  => $finItems ?: [['nombre' => 'Sin movimientos de financiamiento en el período', 'monto' => 0, 'vacio' => true]],
                'total'  => $flujoFinanciamiento,
                'label_total' => 'Efectivo neto de financiamiento',
            ],
        ]);

        return view('admin.reportes.flujo-caja', compact(
            'periodos', 'anio', 'mes', 'corte', 'secciones',
            'efectivoInicio', 'efectivoFin', 'variacionNeta'
        ) + ['sinDatos' => false]);
    }

    private function companiaActivaId(Request $request): int
    {
        $companiaId = session('compania_activa_id');
        abort_if(! $companiaId, 404, 'No hay compañía activa.');
        abort_unless(
            $request->user()->is_admin || $request->user()->companiasAccesibles()->contains('id', $companiaId),
            403
        );

        return (int) $companiaId;
    }
}
