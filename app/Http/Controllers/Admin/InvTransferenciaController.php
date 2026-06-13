<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\InvAlmacen;
use App\Models\InvExistencia;
use App\Models\InvMovimiento;
use App\Models\InvMovimientoDetalle;
use App\Models\InvTransferencia;
use App\Models\ItemProducto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvTransferenciaController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);
        $desde = $request->query('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->query('hasta', now()->toDateString());

        $transferencias = InvTransferencia::with(['almacenOrigen', 'almacenDestino'])
            ->where('compania_id', $companiaId)
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderByDesc('fecha')
            ->paginate(20)->withQueryString();

        return view('admin.inventario.transferencias.index', compact('transferencias', 'desde', 'hasta'));
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);
        $almacenes  = InvAlmacen::where('compania_id', $companiaId)->where('activo', true)->orderBy('codigo')->get();
        $items      = ItemProducto::where('compania_id', $companiaId)->where('activo', true)->orderBy('codigo')->get();

        return view('admin.inventario.transferencias.create', compact('almacenes', 'items'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('inventario.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'almacen_origen_id'  => ['required', 'integer', 'exists:inv_almacenes,id', 'different:almacen_destino_id'],
            'almacen_destino_id' => ['required', 'integer', 'exists:inv_almacenes,id'],
            'fecha'              => ['required', 'date'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.item_id'    => ['required', 'integer', 'exists:item_productos,id'],
            'items.*.cantidad'   => ['required', 'numeric', 'min:0.0001'],
            'items.*.costo'      => ['nullable', 'numeric', 'min:0'],
        ]);

        $origen  = InvAlmacen::where('compania_id', $companiaId)->findOrFail($data['almacen_origen_id']);
        $destino = InvAlmacen::where('compania_id', $companiaId)->findOrFail($data['almacen_destino_id']);

        DB::transaction(function () use ($data, $companiaId, $origen, $destino, $request) {
            $transferencia = InvTransferencia::create([
                'compania_id'        => $companiaId,
                'almacen_origen_id'  => $origen->id,
                'almacen_destino_id' => $destino->id,
                'fecha'              => $data['fecha'],
                'estado'             => InvTransferencia::ESTADO_APLICADA,
                'created_by'         => $request->user()->email,
            ]);

            // Movimiento salida del origen
            $movSalida = InvMovimiento::create([
                'compania_id'     => $companiaId,
                'almacen_id'      => $origen->id,
                'fecha'           => $data['fecha'],
                'tipo_movimiento' => 'SALIDA',
                'documento_origen' => 'TRANSFERENCIA',
                'documento_id'    => $transferencia->id,
                'descripcion'     => "Transferencia a {$destino->nombre}",
                'estado'          => 'APLICADO',
                'created_by'      => $request->user()->email,
            ]);

            // Movimiento entrada al destino
            $movEntrada = InvMovimiento::create([
                'compania_id'     => $companiaId,
                'almacen_id'      => $destino->id,
                'fecha'           => $data['fecha'],
                'tipo_movimiento' => 'ENTRADA',
                'documento_origen' => 'TRANSFERENCIA',
                'documento_id'    => $transferencia->id,
                'descripcion'     => "Transferencia desde {$origen->nombre}",
                'estado'          => 'APLICADO',
                'created_by'      => $request->user()->email,
            ]);

            foreach ($data['items'] as $linea) {
                $itemId   = $linea['item_id'];
                $cantidad = $linea['cantidad'];
                $costo    = $linea['costo'] ?? 0;

                // Detalle salida
                InvMovimientoDetalle::create([
                    'movimiento_id' => $movSalida->id,
                    'item_id'       => $itemId,
                    'cantidad'      => $cantidad,
                    'costo_unitario' => $costo,
                    'total'         => $cantidad * $costo,
                ]);

                // Detalle entrada
                InvMovimientoDetalle::create([
                    'movimiento_id' => $movEntrada->id,
                    'item_id'       => $itemId,
                    'cantidad'      => $cantidad,
                    'costo_unitario' => $costo,
                    'total'         => $cantidad * $costo,
                ]);

                // Actualizar existencias: restar del origen, sumar al destino
                InvExistencia::where('almacen_id', $origen->id)->where('item_id', $itemId)
                    ->decrement('cantidad', $cantidad);
                $existDest = InvExistencia::firstOrCreate(
                    ['almacen_id' => $destino->id, 'item_id' => $itemId],
                    ['compania_id' => $companiaId, 'cantidad' => 0, 'costo_promedio' => $costo]
                );
                $existDest->increment('cantidad', $cantidad);
            }
        });

        return redirect()->route('admin.inventario.transferencias.index')
            ->with('status', "Transferencia de {$origen->nombre} → {$destino->nombre} registrada.");
    }

    public function show(Request $request, InvTransferencia $transferencia): View
    {
        abort_unless($transferencia->compania_id === $this->companiaActivaId($request), 404);
        $transferencia->load(['almacenOrigen', 'almacenDestino', 'movimientos.detalle.item']);

        return view('admin.inventario.transferencias.show', compact('transferencia'));
    }
}
