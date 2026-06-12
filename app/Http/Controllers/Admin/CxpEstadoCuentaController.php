<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\CxpDocumento;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Estado de cuenta CxP: movimientos de un proveedor (facturas como cargo,
 * pagos como abono) en un rango de fechas, con saldo inicial calculado
 * a partir de los documentos anteriores al rango y saldo corrido.
 */
class CxpEstadoCuentaController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    public function __invoke(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'proveedor_id' => [
                'nullable', 'integer',
                Rule::exists('contact_contactos', 'id')->where('compania_id', $companiaId),
            ],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $desde = isset($data['desde']) ? Carbon::parse($data['desde']) : now()->startOfYear();
        $hasta = isset($data['hasta']) ? Carbon::parse($data['hasta']) : now();

        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $proveedores = Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'PROVEEDOR'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        $proveedorId = $data['proveedor_id'] ?? null;
        $proveedor = $proveedorId ? $proveedores->firstWhere('id', (int) $proveedorId) : null;

        $saldoInicial = 0.0;
        $movimientos = [];
        $totalCargos = 0.0;
        $totalAbonos = 0.0;

        if ($proveedor) {
            $base = CxpDocumento::query()
                ->where('compania_id', $companiaId)
                ->where('proveedor_id', $proveedor->id)
                ->where('estado', '!=', CxpDocumento::ESTADO_ANULADO);

            $cargables = CxpDocumento::tiposPagables();
            $placeholders = implode(',', array_fill(0, count($cargables), '?'));

            $previos = (clone $base)
                ->whereDate('fecha', '<', $desde->toDateString())
                ->selectRaw(
                    "coalesce(sum(case when tipo_documento in ($placeholders) then total else 0 end), 0) as cargos,
                     coalesce(sum(case when tipo_documento not in ($placeholders) then total else 0 end), 0) as abonos",
                    [...$cargables, ...$cargables]
                )
                ->first();

            $saldoInicial = (float) $previos->cargos - (float) $previos->abonos;

            $documentos = (clone $base)
                ->whereDate('fecha', '>=', $desde->toDateString())
                ->whereDate('fecha', '<=', $hasta->toDateString())
                ->orderBy('fecha')
                ->orderBy('id')
                ->get();

            $saldo = $saldoInicial;

            foreach ($documentos as $doc) {
                $esCargo = $doc->esCargo();
                $cargo = $esCargo ? (float) $doc->total : 0.0;
                $abono = $esCargo ? 0.0 : (float) $doc->total;
                $saldo += $cargo - $abono;
                $totalCargos += $cargo;
                $totalAbonos += $abono;

                $movimientos[] = [
                    'doc' => $doc,
                    'cargo' => $cargo,
                    'abono' => $abono,
                    'saldo' => $saldo,
                ];
            }
        }

        if ($proveedor && $export = $this->exportarReporte($request, 'admin.exports.estado_cuenta', [
            'titulo' => 'Estado de cuenta — Cuentas por Pagar',
            'compania' => Compania::find($companiaId)?->nombre ?? '',
            'entidad' => $proveedor->nombre,
            'tipoCargo' => 'Factura',
            'tipoAbono' => 'Pago / NC',
            'desde' => $desde,
            'hasta' => $hasta,
            'saldoInicial' => $saldoInicial,
            'movimientos' => $movimientos,
            'totalCargos' => $totalCargos,
            'totalAbonos' => $totalAbonos,
        ], 'estado_cuenta_cxp_'.$proveedor->id)) {
            return $export;
        }

        return view('admin.cxp.estado-cuenta', [
            'proveedores' => $proveedores,
            'proveedor' => $proveedor,
            'desde' => $desde,
            'hasta' => $hasta,
            'saldoInicial' => $saldoInicial,
            'movimientos' => $movimientos,
            'totalCargos' => $totalCargos,
            'totalAbonos' => $totalAbonos,
        ]);
    }
}
