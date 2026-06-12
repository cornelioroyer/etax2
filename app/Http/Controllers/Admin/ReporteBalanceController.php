<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Balance de Situación (estado de situación financiera) a una fecha de
 * corte (fin de mes), desde cgl_saldos (asientos POSTEADOS).
 *
 * Convención: saldo deudor = débito - crédito. Activos se presentan en
 * deudor; Pasivos y Patrimonio en acreedor. El Patrimonio incluye los
 * resultados acumulados de años anteriores y la utilidad del período
 * (cuentas de resultado aún sin cerrar).
 */
class ReporteBalanceController extends Controller
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
            return view('admin.reportes.balance-situacion', [
                'sinDatos' => true, 'periodos' => $periodos,
                'anio' => now()->year, 'mes' => now()->month, 'corte' => now(),
                'activos' => collect(), 'pasivos' => collect(), 'patrimonio' => collect(),
                'totalActivos' => 0.0, 'totalPasivos' => 0.0, 'totalPatrimonio' => 0.0,
            ]);
        }

        $anio = (int) $request->input('anio', $periodos->first()->anio);
        $mes = (int) $request->input('mes', 0);

        if (! $periodos->contains(fn ($p) => $p->anio == $anio && $p->mes == $mes)) {
            $delAnio = $periodos->firstWhere('anio', $anio) ?? $periodos->first();
            $anio = (int) $delAnio->anio;
            $mes = (int) $delAnio->mes;
        }

        $corte = Carbon::create($anio, $mes, 1)->endOfMonth();

        $filas = DB::table('cgl_saldos as s')
            ->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
            ->join('cgl_cuentas as c', 'c.id', '=', 's.cuenta_id')
            ->join('cgl_tipos_cuenta as t', 't.id', '=', 'c.tipo_cuenta_id')
            ->where('s.compania_id', $companiaId)
            ->where(fn ($q) => $q->where('p.anio', '<', $anio)
                ->orWhere(fn ($q) => $q->where('p.anio', $anio)->where('p.mes', '<=', $mes)))
            ->groupBy('t.codigo', 'c.codigo', 'c.nombre')
            ->select([
                't.codigo as tipo',
                'c.codigo',
                'c.nombre',
                DB::raw('SUM(s.debito - s.credito) as deudor_total'),
                DB::raw("SUM(CASE WHEN p.anio = {$anio} THEN s.debito - s.credito ELSE 0 END) as deudor_anio"),
            ])
            ->get();

        $grupos = DB::table('cgl_cuentas')
            ->where('compania_id', $companiaId)
            ->whereRaw('LENGTH(codigo) = 3')
            ->pluck('nombre', 'codigo');

        $seccion = function (array $tipos, int $signo) use ($filas, $grupos): Collection {
            return $filas->whereIn('tipo', $tipos)
                ->map(fn ($f) => ['codigo' => $f->codigo, 'nombre' => $f->nombre, 'saldo' => round($signo * (float) $f->deudor_total, 2)])
                ->filter(fn ($c) => abs($c['saldo']) >= 0.01)
                ->sortBy('codigo')
                ->groupBy(fn ($c) => substr($c['codigo'], 0, 3))
                ->map(fn ($cuentas, $cod) => [
                    'grupo' => $grupos[$cod] ?? "Grupo {$cod}",
                    'cuentas' => $cuentas->values(),
                    'subtotal' => round((float) $cuentas->sum('saldo'), 2),
                ])
                ->sortKeys()
                ->values();
        };

        $activos = $seccion(['ACTIVO'], 1);
        $pasivos = $seccion(['PASIVO'], -1);
        $patrimonio = $seccion(['PATRIMONIO'], -1);

        // Resultados (cuentas de ingreso/costo/gasto sin cerrar)
        $deudorResultado = fn (string $col) => (float) $filas->whereIn('tipo', ['INGRESO', 'COSTO', 'GASTO'])->sum($col);
        $utilidadAnio = round(-$deudorResultado('deudor_anio'), 2);
        $utilidadAcumulada = round(-$deudorResultado('deudor_total') - $utilidadAnio, 2);

        $resultados = collect();
        if (abs($utilidadAcumulada) >= 0.01) {
            $resultados->push(['codigo' => '', 'nombre' => 'Resultados acumulados de años anteriores', 'saldo' => $utilidadAcumulada]);
        }
        $resultados->push(['codigo' => '', 'nombre' => "Utilidad (pérdida) del período {$anio}", 'saldo' => $utilidadAnio]);

        $patrimonio->push([
            'grupo' => 'Resultados',
            'cuentas' => $resultados,
            'subtotal' => round((float) $resultados->sum('saldo'), 2),
        ]);

        return view('admin.reportes.balance-situacion', [
            'sinDatos' => false,
            'periodos' => $periodos,
            'anio' => $anio,
            'mes' => $mes,
            'corte' => $corte,
            'activos' => $activos,
            'pasivos' => $pasivos,
            'patrimonio' => $patrimonio,
            'totalActivos' => round((float) $activos->sum('subtotal'), 2),
            'totalPasivos' => round((float) $pasivos->sum('subtotal'), 2),
            'totalPatrimonio' => round((float) $patrimonio->sum('subtotal'), 2),
        ]);
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
