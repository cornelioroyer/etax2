<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AntiguedadMensual;
use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\CxpDocumento;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Antigüedad de saldos CxP: facturas de proveedor con saldo pendiente
 * agrupadas por proveedor en cubetas de meses completos vencidos
 * (corriente, 1, 2, 3, 4, +4 meses).
 */
class CxpAntiguedadController extends Controller
{
    use AntiguedadMensual;
    use ConCompaniaActiva;
    use ExportaReporte;

    public function __invoke(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $corte = $request->validate(['corte' => ['nullable', 'date']])['corte'] ?? null;
        $corte = $corte ? Carbon::parse($corte) : now();

        $facturas = CxpDocumento::query()
            ->with('proveedor')
            ->where('compania_id', $companiaId)
            ->whereIn('tipo_documento', CxpDocumento::tiposPagables())
            ->whereIn('estado', [CxpDocumento::ESTADO_PENDIENTE, CxpDocumento::ESTADO_PARCIAL])
            ->where('saldo', '>', 0)
            ->whereDate('fecha', '<=', $corte->toDateString())
            ->orderBy('fecha')
            ->get();

        $columnas = $this->columnasMensuales($corte);
        $cubetas = array_keys($columnas);

        $proveedores = [];

        foreach ($facturas as $factura) {
            $vence = ($factura->fecha_vencimiento ?? $factura->fecha)->copy()->startOfDay();
            $cubeta = $this->cubetaMensual($corte, $vence);

            $id = $factura->proveedor_id;

            if (! isset($proveedores[$id])) {
                $proveedores[$id] = ['proveedor' => $factura->proveedor, 'total' => 0.0, 'facturas' => []];
                foreach ($cubetas as $c) {
                    $proveedores[$id][$c] = 0.0;
                }
            }

            $saldo = (float) $factura->saldo;
            $proveedores[$id][$cubeta] += $saldo;
            $proveedores[$id]['total'] += $saldo;
            $proveedores[$id]['facturas'][] = ['doc' => $factura, 'cubeta' => $cubeta];
        }

        usort($proveedores, fn ($a, $b) => $b['total'] <=> $a['total']);

        $totales = ['total' => array_sum(array_column($proveedores, 'total'))];
        foreach ($cubetas as $c) {
            $totales[$c] = array_sum(array_column($proveedores, $c));
        }

        if ($export = $this->exportarReporte($request, 'admin.exports.antiguedad', [
            'titulo' => 'Antigüedad de saldos — Cuentas por Pagar',
            'compania' => Compania::find($companiaId)?->nombre ?? '',
            'entidadLabel' => 'Proveedor',
            'columnas' => $columnas,
            // La vista compartida itera $clientes y usa la clave 'cliente'.
            'clientes' => array_map(fn ($r) => $r + ['cliente' => $r['proveedor']], $proveedores),
            'totales' => $totales,
            'corte' => $corte,
        ], 'antiguedad_cxp_'.$corte->format('Y-m-d'))) {
            return $export;
        }

        return view('admin.cxp.antiguedad', [
            'columnas' => $columnas,
            'proveedores' => $proveedores,
            'totales' => $totales,
            'corte' => $corte,
        ]);
    }
}
