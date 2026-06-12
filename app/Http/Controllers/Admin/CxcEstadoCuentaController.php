<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\CxcDocumento;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Estado de cuenta CxC: movimientos de un cliente (facturas como cargo,
 * cobros como abono) en un rango de fechas, con saldo inicial calculado
 * a partir de los documentos anteriores al rango y saldo corrido.
 */
class CxcEstadoCuentaController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    public function __invoke(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'cliente_id' => [
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

        $clientes = Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        $clienteId = $data['cliente_id'] ?? null;
        $cliente = $clienteId ? $clientes->firstWhere('id', (int) $clienteId) : null;

        $saldoInicial = 0.0;
        $movimientos = [];
        $totalCargos = 0.0;
        $totalAbonos = 0.0;

        if ($cliente) {
            $base = CxcDocumento::query()
                ->where('compania_id', $companiaId)
                ->where('cliente_id', $cliente->id)
                ->where('estado', '!=', CxcDocumento::ESTADO_ANULADO);

            $cargables = CxcDocumento::tiposCobrables();
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

        if ($cliente && $export = $this->exportarReporte($request, 'admin.exports.estado_cuenta', [
            'titulo' => 'Estado de cuenta — Cuentas por Cobrar',
            'compania' => Compania::find($companiaId)?->nombre ?? '',
            'entidad' => $cliente->nombre,
            'tipoCargo' => 'Factura',
            'tipoAbono' => 'Cobro / NC',
            'desde' => $desde,
            'hasta' => $hasta,
            'saldoInicial' => $saldoInicial,
            'movimientos' => $movimientos,
            'totalCargos' => $totalCargos,
            'totalAbonos' => $totalAbonos,
        ], 'estado_cuenta_cxc_'.$cliente->id)) {
            return $export;
        }

        return view('admin.cxc.estado-cuenta', [
            'clientes' => $clientes,
            'cliente' => $cliente,
            'desde' => $desde,
            'hasta' => $hasta,
            'saldoInicial' => $saldoInicial,
            'movimientos' => $movimientos,
            'totalCargos' => $totalCargos,
            'totalAbonos' => $totalAbonos,
        ]);
    }
}
