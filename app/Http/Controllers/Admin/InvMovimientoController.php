<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\InvAlmacen;
use App\Models\InvExistencia;
use App\Models\InvMovimiento;
use App\Models\InvMovimientoDetalle;
use App\Models\ItemProducto;
use App\Services\AsientoAutomatico;
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
            'almacenes'       => InvAlmacen::where('compania_id', $companiaId)->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'items'           => ItemProducto::where('compania_id', $companiaId)->where('activo', true)->where('tipo', 'PRODUCTO')->orderBy('codigo')->get(['id', 'codigo', 'nombre', 'costo']),
            'cuentasContables' => CuentaContable::where('compania_id', $companiaId)->where('permite_movimiento', true)->where('activa', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'almacen_id'               => ['required', 'integer', Rule::exists('inv_almacenes', 'id')->where('compania_id', $companiaId)],
            'fecha'                    => ['required', 'date'],
            'tipo_movimiento'          => ['required', Rule::in(['ENTRADA', 'SALIDA', 'AJUSTE'])],
            'descripcion'              => ['nullable', 'string', 'max:500'],
            'cuenta_contrapartida_id'  => [
                Rule::requiredIf(fn () => $request->input('tipo_movimiento') === 'ENTRADA'),
                'nullable', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
            'lineas'                   => ['required', 'array', 'min:1'],
            'lineas.*.item_id'         => ['required', 'integer', Rule::exists('item_productos_servicios', 'id')->where('compania_id', $companiaId)],
            'lineas.*.cantidad'        => ['required', 'numeric', 'min:0.0001'],
            'lineas.*.costo_unitario'  => ['required', 'numeric', 'min:0'],
        ]);

        $usuario = $request->user();

        DB::transaction(function () use ($data, $companiaId, $usuario) {
            $tipo = $data['tipo_movimiento'];

            $mov = InvMovimiento::create([
                'compania_id'     => $companiaId,
                'almacen_id'      => $data['almacen_id'],
                'fecha'           => $data['fecha'],
                'tipo_movimiento' => $tipo,
                'descripcion'     => $data['descripcion'] ?? null,
                'estado'          => 'CONFIRMADO',
                'created_by'      => $usuario->email,
            ]);

            // Cuentas por defecto para contabilidad
            $cuentaInventarioDefault = CuentaDefault::idPara($companiaId, 'INVENTARIO');
            $cuentaCostoVentaDefault = CuentaDefault::idPara($companiaId, 'COSTO_VENTAS');
            $cuentaGastoDefault      = CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT');

            // Pre-cargar items para evitar N+1
            $itemIds  = array_unique(array_column($data['lineas'], 'item_id'));
            $itemsMap = ItemProducto::whereIn('id', $itemIds)
                ->get(['id', 'nombre', 'cuenta_inventario_id', 'cuenta_costo_venta_id'])
                ->keyBy('id');

            $lineasAsiento = [];
            $totalEntrada  = 0.0;
            $ajusteNeto    = 0.0;

            foreach ($data['lineas'] as $linea) {
                $cantidad = (float) $linea['cantidad'];
                $costo    = (float) $linea['costo_unitario'];
                $total    = round($cantidad * $costo, 2);
                $item     = $itemsMap[(int) $linea['item_id']];

                InvMovimientoDetalle::create([
                    'movimiento_id'  => $mov->id,
                    'item_id'        => $linea['item_id'],
                    'cantidad'       => $cantidad,
                    'costo_unitario' => $costo,
                    'total'          => $total,
                    'created_by'     => $usuario->email,
                ]);

                $cuentaInvId   = $item->cuenta_inventario_id ?? $cuentaInventarioDefault;
                $cuentaCostoId = $item->cuenta_costo_venta_id ?? $cuentaCostoVentaDefault;

                $existencia = InvExistencia::firstOrCreate(
                    ['almacen_id' => $data['almacen_id'], 'item_id' => $linea['item_id']],
                    ['cantidad' => 0, 'costo_promedio' => $costo, 'updated_by' => $usuario->email]
                );

                if ($tipo === 'ENTRADA') {
                    $cantAnterior  = (float) $existencia->cantidad;
                    $costoAnterior = (float) $existencia->costo_promedio;
                    $nuevaCantidad = $cantAnterior + $cantidad;
                    $nuevoCosto    = $nuevaCantidad > 0
                        ? round(($cantAnterior * $costoAnterior + $cantidad * $costo) / $nuevaCantidad, 4)
                        : $costo;
                    $existencia->update(['cantidad' => $nuevaCantidad, 'costo_promedio' => $nuevoCosto, 'updated_by' => $usuario->email]);

                    if ($cuentaInvId) {
                        $lineasAsiento[] = ['cuenta_id' => $cuentaInvId, 'descripcion' => $item->nombre, 'debito' => $total, 'credito' => 0];
                        $totalEntrada   += $total;
                    }
                } elseif ($tipo === 'SALIDA') {
                    $costoPromedio = (float) $existencia->costo_promedio;
                    $costoSalida   = round($costoPromedio * $cantidad, 2);
                    $existencia->update(['cantidad' => max(0, (float) $existencia->cantidad - $cantidad), 'updated_by' => $usuario->email]);

                    if ($cuentaInvId && $cuentaCostoId && $costoSalida > 0) {
                        $lineasAsiento[] = ['cuenta_id' => $cuentaCostoId, 'descripcion' => 'Costo: '.$item->nombre, 'debito' => $costoSalida, 'credito' => 0];
                        $lineasAsiento[] = ['cuenta_id' => $cuentaInvId,   'descripcion' => 'Inventario: '.$item->nombre, 'debito' => 0, 'credito' => $costoSalida];
                    }
                } else {
                    // AJUSTE: capturar valor viejo ANTES de actualizar
                    $oldValor = round((float) $existencia->cantidad * (float) $existencia->costo_promedio, 2);
                    $newValor = round($cantidad * $costo, 2);
                    $diff     = round($newValor - $oldValor, 2);

                    $existencia->update(['cantidad' => $cantidad, 'costo_promedio' => $costo, 'updated_by' => $usuario->email]);

                    if ($cuentaInvId && abs($diff) > 0.001) {
                        if ($diff > 0) {
                            $lineasAsiento[] = ['cuenta_id' => $cuentaInvId, 'descripcion' => 'Ajuste +: '.$item->nombre, 'debito' => $diff, 'credito' => 0];
                        } else {
                            $lineasAsiento[] = ['cuenta_id' => $cuentaInvId, 'descripcion' => 'Ajuste -: '.$item->nombre, 'debito' => 0, 'credito' => abs($diff)];
                        }
                        $ajusteNeto += $diff;
                    }
                }
            }

            // Línea de cierre del asiento
            if ($tipo === 'ENTRADA' && $totalEntrada > 0 && ($data['cuenta_contrapartida_id'] ?? null)) {
                $lineasAsiento[] = [
                    'cuenta_id'   => (int) $data['cuenta_contrapartida_id'],
                    'descripcion' => 'Entrada de inventario',
                    'debito'      => 0,
                    'credito'     => round($totalEntrada, 2),
                ];
            }

            if ($tipo === 'AJUSTE' && abs($ajusteNeto) > 0.001 && $cuentaGastoDefault) {
                $ajusteNeto = round($ajusteNeto, 2);
                if ($ajusteNeto > 0) {
                    $lineasAsiento[] = ['cuenta_id' => $cuentaGastoDefault, 'descripcion' => 'Diferencia ajuste inventario', 'debito' => 0, 'credito' => $ajusteNeto];
                } else {
                    $lineasAsiento[] = ['cuenta_id' => $cuentaGastoDefault, 'descripcion' => 'Diferencia ajuste inventario', 'debito' => abs($ajusteNeto), 'credito' => 0];
                }
            }

            if (! empty($lineasAsiento)) {
                $glosa = match ($tipo) {
                    'ENTRADA' => 'Entrada inventario — '.($data['descripcion'] ?? $data['fecha']),
                    'SALIDA'  => 'Salida inventario — '.($data['descripcion'] ?? $data['fecha']),
                    default   => 'Ajuste inventario — '.($data['descripcion'] ?? $data['fecha']),
                };
                $asiento = app(AsientoAutomatico::class)->postear(
                    $companiaId, $data['fecha'], $glosa, $mov->id,
                    $lineasAsiento, 'INVENTARIO', 'inv_movimientos', $mov->id, $usuario,
                );
                $mov->update(['asiento_id' => $asiento->id]);
            }
        });

        return redirect()->route('admin.inventario.movimientos.index')
            ->with('status', 'Movimiento de inventario registrado.');
    }

    public function show(Request $request, InvMovimiento $movimiento): View
    {
        abort_unless($movimiento->compania_id === $this->companiaActivaId($request), 404);

        $movimiento->load(['almacen', 'detalle.item', 'asiento']);

        return view('admin.inventario.movimientos.show', ['movimiento' => $movimiento]);
    }
}
