<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Estado de Resultado del año fiscal, con corte a fin de mes:
 * columna del mes corriente y acumulado del año (YTD), desde cgl_saldos.
 *
 * Convención: saldo deudor = débito - crédito. Ingresos se presentan en
 * acreedor (positivo = ganancia); costos y gastos en deudor.
 */
class ReporteResultadosController extends Controller
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
            return view('admin.reportes.estado-resultado', [
                'sinDatos' => true, 'periodos' => $periodos,
                'anio' => now()->year, 'mes' => now()->month, 'corte' => now(),
                'secciones' => collect(), 'utilidadBruta' => ['mes' => 0.0, 'ytd' => 0.0],
                'utilidadNeta' => ['mes' => 0.0, 'ytd' => 0.0], 'ingresos' => ['mes' => 0.0, 'ytd' => 0.0],
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
            ->where('p.anio', $anio)
            ->where('p.mes', '<=', $mes)
            ->whereIn('t.codigo', ['INGRESO', 'COSTO', 'GASTO'])
            ->groupBy('t.codigo', 'c.codigo', 'c.nombre')
            ->select([
                't.codigo as tipo',
                'c.codigo',
                'c.nombre',
                DB::raw('SUM(s.debito - s.credito) as deudor_ytd'),
                DB::raw("SUM(CASE WHEN p.mes = {$mes} THEN s.debito - s.credito ELSE 0 END) as deudor_mes"),
            ])
            ->get();

        $grupos = DB::table('cgl_cuentas')
            ->where('compania_id', $companiaId)
            ->whereRaw('LENGTH(codigo) = 3')
            ->pluck('nombre', 'codigo');

        $seccion = function (string $tipo, int $signo) use ($filas, $grupos): Collection {
            return $filas->where('tipo', $tipo)
                ->map(fn ($f) => [
                    'codigo' => $f->codigo,
                    'nombre' => $f->nombre,
                    'mes' => round($signo * (float) $f->deudor_mes, 2),
                    'ytd' => round($signo * (float) $f->deudor_ytd, 2),
                ])
                ->filter(fn ($c) => abs($c['mes']) >= 0.01 || abs($c['ytd']) >= 0.01)
                ->sortBy('codigo')
                ->groupBy(fn ($c) => substr($c['codigo'], 0, 3))
                ->map(fn ($cuentas, $cod) => [
                    'grupo' => $grupos[$cod] ?? "Grupo {$cod}",
                    'cuentas' => $cuentas->values(),
                    'mes' => round((float) $cuentas->sum('mes'), 2),
                    'ytd' => round((float) $cuentas->sum('ytd'), 2),
                ])
                ->sortKeys()
                ->values();
        };

        $totales = fn (Collection $grupos) => [
            'mes' => round((float) $grupos->sum('mes'), 2),
            'ytd' => round((float) $grupos->sum('ytd'), 2),
        ];

        $ingresosGrupos = $seccion('INGRESO', -1);
        $costosGrupos = $seccion('COSTO', 1);
        $gastosGrupos = $seccion('GASTO', 1);

        $ingresos = $totales($ingresosGrupos);
        $costos = $totales($costosGrupos);
        $gastos = $totales($gastosGrupos);

        $utilidadBruta = [
            'mes' => round($ingresos['mes'] - $costos['mes'], 2),
            'ytd' => round($ingresos['ytd'] - $costos['ytd'], 2),
        ];
        $utilidadNeta = [
            'mes' => round($utilidadBruta['mes'] - $gastos['mes'], 2),
            'ytd' => round($utilidadBruta['ytd'] - $gastos['ytd'], 2),
        ];

        $secciones = collect([
            ['titulo' => 'Ingresos', 'grupos' => $ingresosGrupos, 'total' => $ingresos, 'color' => 'blue'],
            ['titulo' => 'Costos', 'grupos' => $costosGrupos, 'total' => $costos, 'color' => 'red', 'subtotal' => ['label' => 'UTILIDAD BRUTA', 'valores' => $utilidadBruta]],
            ['titulo' => 'Gastos', 'grupos' => $gastosGrupos, 'total' => $gastos, 'color' => 'red'],
        ]);

        return view('admin.reportes.estado-resultado', [
            'sinDatos' => false,
            'periodos' => $periodos,
            'anio' => $anio,
            'mes' => $mes,
            'corte' => $corte,
            'secciones' => $secciones,
            'ingresos' => $ingresos,
            'utilidadBruta' => $utilidadBruta,
            'utilidadNeta' => $utilidadNeta,
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
