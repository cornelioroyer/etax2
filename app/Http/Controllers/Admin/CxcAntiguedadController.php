<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AntiguedadMensual;
use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\CxcDocumento;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Antigüedad de saldos CxC: facturas con saldo pendiente agrupadas
 * por cliente en cubetas de meses completos vencidos (corriente, 1, 2,
 * 3, 4, +4 meses). La edad se mide contra fecha_vencimiento (o fecha)
 * usando meses calendario completos.
 */
class CxcAntiguedadController extends Controller
{
    use AntiguedadMensual;
    use ConCompaniaActiva;
    use ExportaReporte;

    public function __invoke(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $corte = $request->validate(['corte' => ['nullable', 'date']])['corte'] ?? null;
        $corte = $corte ? Carbon::parse($corte) : now();

        $facturas = CxcDocumento::query()
            ->with('cliente')
            ->where('compania_id', $companiaId)
            ->whereIn('tipo_documento', CxcDocumento::tiposCobrables())
            ->whereIn('estado', [CxcDocumento::ESTADO_PENDIENTE, CxcDocumento::ESTADO_PARCIAL])
            ->where('saldo', '>', 0)
            ->whereDate('fecha', '<=', $corte->toDateString())
            ->orderBy('fecha')
            ->get();

        $columnas = $this->columnasMensuales($corte);
        $cubetas = array_keys($columnas);

        $clientes = [];

        foreach ($facturas as $factura) {
            $vence = ($factura->fecha_vencimiento ?? $factura->fecha)->copy()->startOfDay();
            $cubeta = $this->cubetaMensual($corte, $vence);

            $id = $factura->cliente_id;

            if (! isset($clientes[$id])) {
                $clientes[$id] = ['cliente' => $factura->cliente, 'total' => 0.0, 'facturas' => []];
                foreach ($cubetas as $c) {
                    $clientes[$id][$c] = 0.0;
                }
            }

            $saldo = (float) $factura->saldo;
            $clientes[$id][$cubeta] += $saldo;
            $clientes[$id]['total'] += $saldo;
            $clientes[$id]['facturas'][] = ['doc' => $factura, 'cubeta' => $cubeta];
        }

        usort($clientes, fn ($a, $b) => $b['total'] <=> $a['total']);

        $totales = ['total' => array_sum(array_column($clientes, 'total'))];
        foreach ($cubetas as $c) {
            $totales[$c] = array_sum(array_column($clientes, $c));
        }

        if ($export = $this->exportarReporte($request, 'admin.exports.antiguedad', [
            'titulo' => 'Antigüedad de saldos — Cuentas por Cobrar',
            'compania' => Compania::find($companiaId)?->nombre ?? '',
            'entidadLabel' => 'Cliente',
            'columnas' => $columnas,
            'clientes' => $clientes,
            'totales' => $totales,
            'corte' => $corte,
        ], 'antiguedad_cxc_'.$corte->format('Y-m-d'))) {
            return $export;
        }

        return view('admin.cxc.antiguedad', [
            'columnas' => $columnas,
            'clientes' => $clientes,
            'totales' => $totales,
            'corte' => $corte,
        ]);
    }
}
