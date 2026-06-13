<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\InvAlmacen;
use App\Models\InvExistencia;
use App\Models\InvMovimiento;
use App\Models\InvMovimientoDetalle;
use App\Models\ItemProducto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvMovimientoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'almacen_id' => ['nullable', 'integer'],
            'tipo'       => ['nullable', Rule::in(['ENTRADA', 'SALIDA', 'AJUSTE', 'TRANSFERENCIA'])],
            'desde'      => ['nullable', 'date'],
            'hasta'      => ['nullable', 'date'],
        ]);

        $movimientos = InvMovimiento::with('almacen')
            ->where('compania_id', $companiaId)
            ->when($filtros['almacen_id'] ?? null, fn ($q, $v) => $q->where('almacen_id', $v))
            ->when($filtros['tipo'] ?? null, fn ($q, $v) => $q->where('tipo_movimiento', $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.inventario.movimientos.index', [
            'movimientos' => $movimientos,
            'filtros'     => $filtros,
            'almacenes'   => InvAlmacen::where('compania_id', $companiaId)->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        return view('admin.inventario.movimientos.create', [
            'almacenes' => InvAlmacen::where('compania_id', $companiaId)->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'items'     => ItemProducto::where('compania_id', $companiaId)->where('activo', true)->where('tipo', 'PRODUCTO')->orderBy('codigo')->get(['id', 'codigo', 'nombre', 'costo']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'almacen_id'     => ['required', 'integer', Rule::exists('inv_almacenes', 'id')->where('compania_id', $companiaId)],
            'fecha'          => ['required', 'date'],
            'tipo_movimiento' => ['required', Rule::in(['ENTRADA', 'SALIDA', 'AJUSTE'])],
            'descripcion'    => ['nullable', 'string', 'max:500'],
            'lineas'         => ['required', 'array', 'min:1'],
            'lineas.*.item_id'        => ['required', 'integer', Rule::exists('item_productos_servicios', 'id')->where('compania_id', $companiaId)],
            'lineas.*.cantidad'       => ['required', 'numeric', 'min:0.0001'],
            'lineas.*.costo_unitario' => ['required', 'numeric', 'min:0'],
        ]);

        $usuario = $request->user();

        DB::transaction(function () use ($data, $companiaId, $usuario) {
            $mov = InvMovimiento::create([
                'compania_id'     => $companiaId,
                'almacen_id'      => $data['almacen_id'],
                'fecha'           => $data['fecha'],
                'tipo_movimiento' => $data['tipo_movimiento'],
                'descripcion'     => $data['descripcion'] ?? null,
                'estado'          => 'CONFIRMADO',
                'created_by'      => $usuario->email,
            ]);

            foreach ($data['lineas'] as $linea) {
                $cantidad = (float) $linea['cantidad'];
                $costo    = (float) $linea['costo_unitario'];
                $total    = round($cantidad * $costo, 2);

                InvMovimientoDetalle::create([
                    'movimiento_id'  => $mov->id,
                    'item_id'        => $linea['item_id'],
                    'cantidad'       => $cantidad,
                    'costo_unitario' => $costo,
                    'total'          => $total,
                    'created_by'     => $usuario->email,
                ]);

                // Actualizar existencias (costo promedio ponderado para entradas)
                $existencia = InvExistencia::firstOrCreate(
                    ['almacen_id' => $data['almacen_id'], 'item_id' => $linea['item_id']],
                    ['cantidad' => 0, 'costo_promedio' => $costo, 'updated_by' => $usuario->email]
                );

                if ($data['tipo_movimiento'] === 'ENTRADA') {
                    $cantidadAnterior  = (float) $existencia->cantidad;
                    $costoAnterior     = (float) $existencia->costo_promedio;
                    $nuevaCantidad     = $cantidadAnterior + $cantidad;
                    $nuevoCosto        = $nuevaCantidad > 0
                        ? round(($cantidadAnterior * $costoAnterior + $cantidad * $costo) / $nuevaCantidad, 4)
                        : $costo;
                    $existencia->update(['cantidad' => $nuevaCantidad, 'costo_promedio' => $nuevoCosto, 'updated_by' => $usuario->email]);
                } elseif ($data['tipo_movimiento'] === 'SALIDA') {
                    $existencia->update(['cantidad' => max(0, (float) $existencia->cantidad - $cantidad), 'updated_by' => $usuario->email]);
                } else {
                    // AJUSTE: establece la cantidad directamente
                    $existencia->update(['cantidad' => $cantidad, 'costo_promedio' => $costo, 'updated_by' => $usuario->email]);
                }
            }
        });

        return redirect()->route('admin.inventario.movimientos.index')
            ->with('status', 'Movimiento de inventario registrado.');
    }

    public function show(Request $request, InvMovimiento $movimiento): View
    {
        abort_unless($movimiento->compania_id === $this->companiaActivaId($request), 404);

        $movimiento->load(['almacen', 'detalle.item']);

        return view('admin.inventario.movimientos.show', ['movimiento' => $movimiento]);
    }
}
