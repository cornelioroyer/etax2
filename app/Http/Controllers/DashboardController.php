<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboard "Estado Financiero": balance y resultados reales desde
 * cgl_saldos (alimentada por los asientos POSTEADOS vía trigger).
 *
 * Convención: saldo deudor = débito - crédito.
 *  - Activos    = saldo deudor de cuentas ACTIVO
 *  - Pasivos    = -saldo deudor de cuentas PASIVO
 *  - Patrimonio = -saldo deudor PATRIMONIO + resultados acumulados + utilidad del año
 *  - Utilidad   = -(saldo deudor de INGRESO + COSTO + GASTO)
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $companiaId = session('compania_activa_id');

        $anios = collect();
        $saldos = collect();
        $anio = now()->year;

        if ($companiaId) {
            $anios = DB::table('cgl_saldos as s')
                ->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
                ->where('s.compania_id', $companiaId)
                ->distinct()
                ->orderByDesc('p.anio')
                ->pluck('p.anio');

            $anioPedido = (int) $request->input('anio', 0);
            $anio = $anios->contains($anioPedido) ? $anioPedido : (int) ($anios->first() ?? now()->year);

            $saldos = DB::table('cgl_saldos as s')
                ->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
                ->join('cgl_cuentas as c', 'c.id', '=', 's.cuenta_id')
                ->join('cgl_tipos_cuenta as t', 't.id', '=', 'c.tipo_cuenta_id')
                ->where('s.compania_id', $companiaId)
                ->where('p.anio', '<=', $anio)
                ->groupBy('t.codigo', 'c.codigo', 'c.nombre')
                ->select([
                    't.codigo as tipo',
                    'c.codigo',
                    'c.nombre',
                    DB::raw("SUM(CASE WHEN p.anio = {$anio} THEN s.debito - s.credito ELSE 0 END) as deudor_anio"),
                    DB::raw('SUM(s.debito - s.credito) as deudor_total'),
                ])
                ->get();
        }

        $sinDatos = $saldos->isEmpty();

        // Nombres de los grupos (cuentas título de 3 dígitos)
        $grupos = $companiaId
            ? DB::table('cgl_cuentas')
                ->where('compania_id', $companiaId)
                ->whereRaw('LENGTH(codigo) = 3')
                ->pluck('nombre', 'codigo')
            : collect();

        $deudor = fn (string $tipo, string $col = 'deudor_total') => (float) $saldos->where('tipo', $tipo)->sum($col);
        $porGrupo = function (string $tipo, string $col, int $signo) use ($saldos, $grupos): Collection {
            return $saldos->where('tipo', $tipo)
                ->groupBy(fn ($f) => substr($f->codigo, 0, 3))
                ->map(fn ($filas, $cod) => [
                    'nombre' => $grupos[$cod] ?? "Grupo {$cod}",
                    'total' => round($signo * (float) $filas->sum($col), 2),
                ])
                ->filter(fn ($g) => abs($g['total']) >= 0.01)
                ->sortKeys()
                ->values();
        };

        // Balance (acumulado hasta el año seleccionado)
        $activos = $deudor('ACTIVO');
        $pasivos = -$deudor('PASIVO');
        $patrimonioContable = -$deudor('PATRIMONIO');

        // Resultados del año y acumulados de años anteriores
        $resultado = fn (string $col) => -($deudor('INGRESO', $col) + $deudor('COSTO', $col) + $deudor('GASTO', $col));
        $utilidadAnio = $resultado('deudor_anio');
        $utilidadAcumulada = $resultado('deudor_total') - $utilidadAnio;
        $patrimonio = $patrimonioContable + $utilidadAcumulada + $utilidadAnio;

        // Estado de resultados del año
        $ingresos = -$deudor('INGRESO', 'deudor_anio');
        $costos = $deudor('COSTO', 'deudor_anio');
        $utilidadBruta = $ingresos - $costos;
        $gastosGrupos = $porGrupo('GASTO', 'deudor_anio', -1); // negativos para la cascada
        $gastos = -1 * (float) $gastosGrupos->sum('total');

        $cascada = collect([['label' => 'Ingresos', 'valor' => round($ingresos, 2), 'tipo' => 'pos']])
            ->when(abs($costos) >= 0.01, fn ($c) => $c->push(['label' => 'Costos', 'valor' => round(-$costos, 2), 'tipo' => 'neg']))
            ->push(['label' => 'Utilidad bruta', 'valor' => round($utilidadBruta, 2), 'tipo' => 'sub'])
            ->concat($gastosGrupos->map(fn ($g) => ['label' => $g['nombre'], 'valor' => $g['total'], 'tipo' => 'neg']))
            ->push(['label' => 'Utilidad neta', 'valor' => round($utilidadAnio, 2), 'tipo' => 'total'])
            ->values();

        // Composición de activos y de pasivos+patrimonio (por grupo)
        $activosGrupos = $porGrupo('ACTIVO', 'deudor_total', 1);
        $pasivosGrupos = $porGrupo('PASIVO', 'deudor_total', -1);

        // Detalle financiero (tabla)
        $detalle = collect()
            ->concat($activosGrupos->map(fn ($g) => ['seccion' => 'Activos', 'grupo' => $g['nombre'], 'total' => $g['total'], 'color' => 'text-blue-700']))
            ->concat($pasivosGrupos->map(fn ($g) => ['seccion' => 'Pasivos', 'grupo' => $g['nombre'], 'total' => $g['total'], 'color' => 'text-red-600']))
            ->push(['seccion' => 'Patrimonio', 'grupo' => 'Capital y reservas', 'total' => round($patrimonioContable, 2), 'color' => 'text-emerald-600'])
            ->when(abs($utilidadAcumulada) >= 0.01, fn ($c) => $c->push(['seccion' => 'Patrimonio', 'grupo' => 'Resultados acumulados de años anteriores', 'total' => round($utilidadAcumulada, 2), 'color' => 'text-emerald-600']))
            ->push(['seccion' => 'Patrimonio', 'grupo' => "Utilidad del período {$anio}", 'total' => round($utilidadAnio, 2), 'color' => 'text-emerald-600'])
            ->values();

        return view('dashboard', [
            'sinDatos' => $sinDatos,
            'anio' => $anio,
            'anios' => $anios,
            'activos' => round($activos, 2),
            'pasivos' => round($pasivos, 2),
            'patrimonio' => round($patrimonio, 2),
            'utilidadAnio' => round($utilidadAnio, 2),
            'ingresos' => round($ingresos, 2),
            'cascada' => $cascada,
            'activosGrupos' => $activosGrupos,
            'pasivosGrupos' => $pasivosGrupos,
            'detalle' => $detalle,
        ]);
    }
}
