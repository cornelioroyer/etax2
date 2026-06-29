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
use Illuminate\Validation\ValidationException;
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

                $cuentaInvId   = $item->cuenta_inventario_id ?? $cuentaInventarioDefault;
                $cuentaCostoId = $item->cuenta_costo_venta_id ?? $cuentaCostoVentaDefault;

                $existencia = InvExistencia::firstOrCreate(
                    ['almacen_id' => $data['almacen_id'], 'item_id' => $linea['item_id']],
                    ['compania_id' => $companiaId, 'cantidad' => 0, 'costo_promedio' => $costo, 'updated_by' => $usuario->email]
                );

                // Snapshot de la existencia ANTES de aplicar esta línea: deja la base
                // para reversar el movimiento (imprescindible para el AJUSTE, que fija
                // valores absolutos y no conserva el estado previo).
                $cantAntes  = (float) $existencia->cantidad;
                $costoAntes = (float) $existencia->costo_promedio;

                InvMovimientoDetalle::create([
                    'movimiento_id'     => $mov->id,
                    'item_id'           => $linea['item_id'],
                    'cantidad'          => $cantidad,
                    'costo_unitario'    => $costo,
                    'total'             => $total,
                    'cantidad_anterior' => $cantAntes,
                    'costo_anterior'    => $costoAntes,
                    'created_by'        => $usuario->email,
                ]);

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

        $movimiento->load(['almacen', 'detalle.item', 'asiento', 'reversaDe', 'reversadoPor']);

        return view('admin.inventario.movimientos.show', ['movimiento' => $movimiento]);
    }

    /**
     * Reversa un movimiento manual mediante una TRANSACCIÓN de compensación
     * (no cambia el estado a ANULADO): crea un movimiento de reverso enlazado al
     * original + el asiento exactamente inverso (swap Dr/Cr). Original y reverso
     * quedan ambos vigentes en el kárdex → pista de auditoría completa.
     *
     * Representación (para que el reverso se re-derive en el Kardex idéntico a lo
     * que se escribe en inv_existencias):
     *   - ENTRADA → movimiento ENTRADA con cantidad NEGATIVA (el Kardex la suma → resta).
     *   - SALIDA  → movimiento ENTRADA con +cantidad al costo de la salida (repone).
     *   - AJUSTE  → movimiento AJUSTE al snapshot previo (cantidad/costo anterior);
     *               solo si es el último movimiento del par (item, almacén).
     */
    public function reversar(Request $request, InvMovimiento $movimiento): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        abort_unless($movimiento->compania_id === $companiaId, 404);
        abort_unless($request->user()->can('inventario.gestionar'), 403);

        $usuario = $request->user();

        if ($movimiento->estado === 'ANULADO') {
            return back()->withErrors(['movimiento' => 'El movimiento está anulado; no se puede reversar.']);
        }
        if ($movimiento->esReverso()) {
            return back()->withErrors(['movimiento' => 'No se puede reversar un movimiento que ya es un reverso.']);
        }
        if (InvMovimiento::where('reversa_de_id', $movimiento->id)->where('estado', '!=', 'ANULADO')->exists()) {
            return back()->withErrors(['movimiento' => 'Este movimiento ya fue reversado.']);
        }

        $movimiento->load(['detalle', 'asiento.detalle']);
        $tipo = $movimiento->tipo_movimiento;

        if (! in_array($tipo, ['ENTRADA', 'SALIDA', 'AJUSTE'], true)) {
            return back()->withErrors(['movimiento' => 'Solo se pueden reversar movimientos de Entrada, Salida o Ajuste.']);
        }

        // El AJUSTE fija valores absolutos: solo se puede reversar si conserva el
        // snapshot previo y si es el ÚLTIMO movimiento del par (item, almacén); de
        // lo contrario restaurarlo pisaría los movimientos intermedios.
        if ($tipo === 'AJUSTE') {
            foreach ($movimiento->detalle as $d) {
                if ($d->cantidad_anterior === null || $d->costo_anterior === null) {
                    return back()->withErrors(['movimiento' => 'Este ajuste es anterior a la función de reverso (sin estado previo registrado); corríjalo con un ajuste nuevo.']);
                }
                $hayPosterior = InvMovimiento::where('compania_id', $companiaId)
                    ->where('almacen_id', $movimiento->almacen_id)
                    ->where('estado', '!=', 'ANULADO')
                    ->where('id', '!=', $movimiento->id)
                    ->whereHas('detalle', fn ($q) => $q->where('item_id', $d->item_id))
                    ->where(function ($q) use ($movimiento) {
                        $q->whereDate('fecha', '>', $movimiento->fecha)
                            ->orWhere(fn ($q2) => $q2->whereDate('fecha', $movimiento->fecha)->where('id', '>', $movimiento->id));
                    })
                    ->exists();
                if ($hayPosterior) {
                    return back()->withErrors(['movimiento' => 'Hay movimientos posteriores sobre este ítem; reverse primero los más recientes o registre un ajuste nuevo.']);
                }
            }
        }

        $fecha = now()->toDateString();

        try {
            DB::transaction(function () use ($movimiento, $usuario, $companiaId, $tipo, $fecha) {
                $rev = InvMovimiento::create([
                    'compania_id'     => $companiaId,
                    'almacen_id'      => $movimiento->almacen_id,
                    'fecha'           => $fecha,
                    'tipo_movimiento' => $tipo === 'AJUSTE' ? 'AJUSTE' : 'ENTRADA',
                    'descripcion'     => 'Reverso de '.ucfirst(strtolower($tipo)).' #'.$movimiento->id
                        .($movimiento->descripcion ? ' — '.$movimiento->descripcion : ''),
                    'estado'          => 'CONFIRMADO',
                    'reversa_de_id'   => $movimiento->id,
                    'created_by'      => $usuario->email,
                ]);

                foreach ($movimiento->detalle as $d) {
                    $itemId = (int) $d->item_id;
                    $q      = (float) $d->cantidad;
                    $c      = (float) $d->costo_unitario;

                    $existencia = InvExistencia::firstOrCreate(
                        ['almacen_id' => $movimiento->almacen_id, 'item_id' => $itemId],
                        ['compania_id' => $companiaId, 'cantidad' => 0, 'costo_promedio' => 0, 'updated_by' => $usuario->email],
                    );
                    $curCant  = (float) $existencia->cantidad;
                    $curCosto = (float) $existencia->costo_promedio;

                    if ($tipo === 'ENTRADA') {
                        $lineaCant  = -$q;          // ENTRADA negativa = deshace la entrada
                        $lineaCosto = $c;
                        $newCant    = round($curCant - $q, 4);
                        $newValor   = round($curCant * $curCosto - $q * $c, 4);
                    } elseif ($tipo === 'SALIDA') {
                        $lineaCant  = $q;           // repone lo que salió, a su costo
                        $lineaCosto = $c;
                        $newCant    = round($curCant + $q, 4);
                        $newValor   = round($curCant * $curCosto + $q * $c, 4);
                    } else {                        // AJUSTE → restaura snapshot previo
                        $lineaCant  = (float) $d->cantidad_anterior;
                        $lineaCosto = (float) $d->costo_anterior;
                        $newCant    = round($lineaCant, 4);
                        $newValor   = round($lineaCant * $lineaCosto, 4);
                    }

                    // Guarda de integridad: no dejar la existencia negativa (mercancía
                    // ya consumida/vendida). Consistente con la guarda de compras.
                    if ($newCant < -0.0001) {
                        throw ValidationException::withMessages([
                            'movimiento' => 'No se puede reversar: la existencia del ítem quedaría negativa (la mercancía ya fue consumida o vendida). Registre la corrección con un movimiento nuevo.',
                        ]);
                    }

                    $newCosto = abs($newCant) > 0.0001
                        ? round($newValor / $newCant, 4)
                        : ($tipo === 'AJUSTE' ? $lineaCosto : $curCosto);

                    InvMovimientoDetalle::create([
                        'movimiento_id'     => $rev->id,
                        'item_id'           => $itemId,
                        'cantidad'          => $lineaCant,
                        'costo_unitario'    => $lineaCosto,
                        'total'             => round($lineaCant * $lineaCosto, 2),
                        'cantidad_anterior' => $curCant,
                        'costo_anterior'    => $curCosto,
                        'created_by'        => $usuario->email,
                    ]);

                    $existencia->update([
                        'cantidad'       => $newCant,
                        'costo_promedio' => $newCosto,
                        'updated_by'     => $usuario->email,
                    ]);
                }

                // Asiento exactamente inverso: swap Dr/Cr de cada línea del original.
                if ($movimiento->asiento && $movimiento->asiento->detalle->isNotEmpty()) {
                    $lineas = $movimiento->asiento->detalle->map(fn ($l) => [
                        'cuenta_id'   => $l->cuenta_id,
                        'contacto_id' => $l->contacto_id,
                        'descripcion' => 'Reverso: '.($l->descripcion ?? ''),
                        'debito'      => (float) $l->credito,
                        'credito'     => (float) $l->debito,
                    ])->all();

                    $asiento = app(AsientoAutomatico::class)->postear(
                        $companiaId, $fecha,
                        'Reverso inventario — movimiento #'.$movimiento->id,
                        $rev->id, $lineas, 'INVENTARIO', 'inv_movimientos', $rev->id, $usuario,
                    );
                    $rev->update(['asiento_id' => $asiento->id]);
                }
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('admin.inventario.movimientos.show', $movimiento)
            ->with('status', 'Movimiento reversado con una transacción de compensación (queda en el historial).');
    }
}
