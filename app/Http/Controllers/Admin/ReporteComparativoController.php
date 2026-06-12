<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Análisis comparativo mensual de cuentas de resultado: una columna por
 * cada mes del año con movimientos, más total anual, desde cgl_saldos.
 *
 * Convención: saldo deudor = débito - crédito. Ingresos se presentan en
 * acreedor (positivo = ganancia); costos y gastos en deudor.
 */
class ReporteComparativoController extends Controller
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
            return view('admin.reportes.comparativo-mensual', [
                'sinDatos' => true, 'periodos' => $periodos, 'anio' => now()->year,
                'meses' => collect(), 'secciones' => collect(),
                'utilidadBruta' => [], 'utilidadNeta' => [],
            ]);
        }

        $anio = (int) $request->input('anio', $periodos->first()->anio);
        if (! $periodos->contains(fn ($p) => $p->anio == $anio)) {
            $anio = (int) $periodos->first()->anio;
        }

        $meses = $periodos->where('anio', $anio)->pluck('mes')->sort()->values();

        $filas = DB::table('cgl_saldos as s')
            ->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
            ->join('cgl_cuentas as c', 'c.id', '=', 's.cuenta_id')
            ->join('cgl_tipos_cuenta as t', 't.id', '=', 'c.tipo_cuenta_id')
            ->where('s.compania_id', $companiaId)
            ->where('p.anio', $anio)
            ->whereIn('t.codigo', ['INGRESO', 'COSTO', 'GASTO'])
            ->groupBy('t.codigo', 'c.codigo', 'c.nombre', 'p.mes')
            ->select([
                't.codigo as tipo',
                'c.codigo',
                'c.nombre',
                'p.mes',
                DB::raw('SUM(s.debito - s.credito) as deudor'),
            ])
            ->get();

        $grupos = DB::table('cgl_cuentas')
            ->where('compania_id', $companiaId)
            ->whereRaw('LENGTH(codigo) = 3')
            ->pluck('nombre', 'codigo');

        $vacio = $meses->mapWithKeys(fn ($m) => [$m => 0.0])->all();

        $seccion = function (string $tipo, int $signo) use ($filas, $grupos, $vacio): Collection {
            return $filas->where('tipo', $tipo)
                ->groupBy('codigo')
                ->map(function (Collection $porMes) use ($signo, $vacio) {
                    $valores = $vacio;
                    foreach ($porMes as $f) {
                        $valores[$f->mes] = round($signo * (float) $f->deudor, 2);
                    }

                    return [
                        'codigo' => $porMes->first()->codigo,
                        'nombre' => $porMes->first()->nombre,
                        'valores' => $valores,
                        'total' => round(array_sum($valores), 2),
                    ];
                })
                ->filter(fn ($c) => collect($c['valores'])->contains(fn ($v) => abs($v) >= 0.01))
                ->sortBy('codigo')
                ->groupBy(fn ($c) => substr($c['codigo'], 0, 3))
                ->map(fn ($cuentas, $cod) => [
                    'grupo' => $grupos[$cod] ?? "Grupo {$cod}",
                    'cuentas' => $cuentas->values(),
                    'valores' => $this->sumarValores($cuentas, $vacio),
                    'total' => round((float) $cuentas->sum('total'), 2),
                ])
                ->sortKeys()
                ->values();
        };

        $totales = fn (Collection $grupos) => [
            'valores' => $this->sumarValores($grupos, $vacio),
            'total' => round((float) $grupos->sum('total'), 2),
        ];

        $ingresosGrupos = $seccion('INGRESO', -1);
        $costosGrupos = $seccion('COSTO', 1);
        $gastosGrupos = $seccion('GASTO', 1);

        $ingresos = $totales($ingresosGrupos);
        $costos = $totales($costosGrupos);
        $gastos = $totales($gastosGrupos);

        $resta = fn (array $a, array $b) => [
            'valores' => collect($a['valores'])
                ->map(fn ($v, $m) => round($v - $b['valores'][$m], 2))
                ->all(),
            'total' => round($a['total'] - $b['total'], 2),
        ];

        $utilidadBruta = $resta($ingresos, $costos);
        $utilidadNeta = $resta($utilidadBruta, $gastos);

        $secciones = collect([
            ['titulo' => 'Ingresos', 'grupos' => $ingresosGrupos, 'total' => $ingresos, 'color' => 'blue'],
            ['titulo' => 'Costos', 'grupos' => $costosGrupos, 'total' => $costos, 'color' => 'red', 'subtotal' => ['label' => 'UTILIDAD BRUTA', 'valores' => $utilidadBruta]],
            ['titulo' => 'Gastos', 'grupos' => $gastosGrupos, 'total' => $gastos, 'color' => 'red'],
        ]);

        return view('admin.reportes.comparativo-mensual', [
            'sinDatos' => false,
            'periodos' => $periodos,
            'anio' => $anio,
            'meses' => $meses,
            'secciones' => $secciones,
            'utilidadBruta' => $utilidadBruta,
            'utilidadNeta' => $utilidadNeta,
        ]);
    }

    private function sumarValores(Collection $items, array $vacio): array
    {
        $suma = $vacio;
        foreach ($items as $item) {
            foreach ($item['valores'] as $mes => $valor) {
                $suma[$mes] = round($suma[$mes] + $valor, 2);
            }
        }

        return $suma;
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
