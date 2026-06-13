<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Liquidación mensual de ITBMS (Panamá).
 *
 * Columnas por período: ITBMS cobrado en ventas (CxC facturas),
 * ITBMS crédito en compras (CxP facturas), diferencia a pagar/favor.
 * Datos desde cxc_documentos y cxp_documentos (documentos no anulados).
 */
class ReporteLiquidacionItbmsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $anios = DB::table('cxc_documentos')
            ->where('compania_id', $companiaId)
            ->where('estado', '!=', 'ANULADO')
            ->whereIn('tipo_documento', ['FACTURA', 'NOTA_DEBITO'])
            ->where('impuesto', '>', 0)
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM fecha)::int AS anio')
            ->union(
                DB::table('cxp_documentos')
                    ->where('compania_id', $companiaId)
                    ->where('estado', '!=', 'ANULADO')
                    ->whereIn('tipo_documento', ['FACTURA', 'NOTA_DEBITO'])
                    ->where('impuesto', '>', 0)
                    ->selectRaw('DISTINCT EXTRACT(YEAR FROM fecha)::int AS anio')
            )
            ->orderByDesc('anio')
            ->pluck('anio')
            ->unique()
            ->concat([now()->year])
            ->unique()
            ->sortDesc()
            ->values();

        $anio = (int) $request->input('anio', $anios->first() ?? now()->year);

        $mesesNombre = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];

        // ITBMS cobrado en ventas CxC (facturas + notas débito, excluye NC)
        $ventasPorMes = DB::table('cxc_documentos')
            ->where('compania_id', $companiaId)
            ->where('estado', '!=', 'ANULADO')
            ->whereIn('tipo_documento', ['FACTURA', 'NOTA_DEBITO'])
            ->whereYear('fecha', $anio)
            ->selectRaw('EXTRACT(MONTH FROM fecha)::int AS mes, SUM(subtotal) AS base, SUM(impuesto) AS itbms, COUNT(*) AS docs')
            ->groupBy(DB::raw('EXTRACT(MONTH FROM fecha)::int'))
            ->get()
            ->keyBy('mes');

        // NC de CxC reduce el ITBMS cobrado
        $ncCxcPorMes = DB::table('cxc_documentos')
            ->where('compania_id', $companiaId)
            ->where('estado', '!=', 'ANULADO')
            ->where('tipo_documento', 'NOTA_CREDITO')
            ->whereYear('fecha', $anio)
            ->selectRaw('EXTRACT(MONTH FROM fecha)::int AS mes, SUM(impuesto) AS itbms')
            ->groupBy(DB::raw('EXTRACT(MONTH FROM fecha)::int'))
            ->get()
            ->keyBy('mes');

        // ITBMS crédito en compras CxP
        $comprasPorMes = DB::table('cxp_documentos')
            ->where('compania_id', $companiaId)
            ->where('estado', '!=', 'ANULADO')
            ->whereIn('tipo_documento', ['FACTURA', 'NOTA_DEBITO'])
            ->whereYear('fecha', $anio)
            ->selectRaw('EXTRACT(MONTH FROM fecha)::int AS mes, SUM(subtotal) AS base, SUM(impuesto) AS itbms, COUNT(*) AS docs')
            ->groupBy(DB::raw('EXTRACT(MONTH FROM fecha)::int'))
            ->get()
            ->keyBy('mes');

        // NC CxP reduce el ITBMS crédito
        $ncCxpPorMes = DB::table('cxp_documentos')
            ->where('compania_id', $companiaId)
            ->where('estado', '!=', 'ANULADO')
            ->where('tipo_documento', 'NOTA_CREDITO')
            ->whereYear('fecha', $anio)
            ->selectRaw('EXTRACT(MONTH FROM fecha)::int AS mes, SUM(impuesto) AS itbms')
            ->groupBy(DB::raw('EXTRACT(MONTH FROM fecha)::int'))
            ->get()
            ->keyBy('mes');

        $periodos = [];
        $totVentasBase = 0.0;
        $totItbmsCobrado = 0.0;
        $totComprasBase = 0.0;
        $totItbmsCredito = 0.0;
        $totNeto = 0.0;

        foreach (range(1, 12) as $mes) {
            $ventasBase  = round((float) ($ventasPorMes[$mes]->base ?? 0), 2);
            $itbmsCobrado = round(
                (float) ($ventasPorMes[$mes]->itbms ?? 0) - (float) ($ncCxcPorMes[$mes]->itbms ?? 0),
                2
            );
            $comprasBase  = round((float) ($comprasPorMes[$mes]->base ?? 0), 2);
            $itbmsCredito = round(
                (float) ($comprasPorMes[$mes]->itbms ?? 0) - (float) ($ncCxpPorMes[$mes]->itbms ?? 0),
                2
            );
            $neto = round($itbmsCobrado - $itbmsCredito, 2);

            $totVentasBase   += $ventasBase;
            $totItbmsCobrado += $itbmsCobrado;
            $totComprasBase  += $comprasBase;
            $totItbmsCredito += $itbmsCredito;
            $totNeto         += $neto;

            $periodos[$mes] = [
                'mes'           => $mes,
                'nombre'        => $mesesNombre[$mes],
                'ventas_base'   => $ventasBase,
                'itbms_cobrado' => $itbmsCobrado,
                'compras_base'  => $comprasBase,
                'itbms_credito' => $itbmsCredito,
                'neto'          => $neto,
                'tiene_datos'   => $ventasBase > 0 || $itbmsCobrado > 0 || $comprasBase > 0 || $itbmsCredito != 0,
            ];
        }

        $totales = [
            'ventas_base'   => round($totVentasBase, 2),
            'itbms_cobrado' => round($totItbmsCobrado, 2),
            'compras_base'  => round($totComprasBase, 2),
            'itbms_credito' => round($totItbmsCredito, 2),
            'neto'          => round($totNeto, 2),
        ];

        return view('admin.reportes.liquidacion-itbms', compact('anios', 'anio', 'periodos', 'totales'));
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
