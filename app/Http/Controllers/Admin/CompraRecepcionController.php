<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Asiento;
use App\Models\CompraOrden;
use App\Models\CompraRecepcion;
use App\Models\CompraRecepcionDetalle;
use App\Models\CuentaDefault;
use App\Models\InvAlmacen;
use App\Models\ItemProducto;
use App\Services\AsientoAutomatico;
use App\Services\InventarioCompras;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompraRecepcionController extends Controller
{
    use ConCompaniaActiva;

    public function show(Request $request, CompraOrden $orden, CompraRecepcion $recepcion): View
    {
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($recepcion->orden_id === $orden->id, 404);

        $recepcion->load(['detalle', 'proveedor']);
        $orden->load('proveedor');

        return view('admin.compras.recepciones.show', [
            'orden'     => $orden,
            'recepcion' => $recepcion,
        ]);
    }

    /**
     * Registra una recepción de mercancía contra una orden de compra.
     * Recibe cantidades por línea; valida que no exceda lo pendiente y
     * recalcula el estado de recepción de la orden.
     */
    public function store(Request $request, CompraOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('compras.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        if (! $orden->esRecibible()) {
            return back()->withErrors(['recepcion' => 'La orden debe estar aprobada (y no completamente recibida) para registrar una recepción.']);
        }

        $data = $request->validate([
            'fecha'               => ['required', 'date'],
            'almacen_id'          => ['nullable', 'integer', Rule::exists('inv_almacenes', 'id')->where('compania_id', $orden->compania_id)],
            'lineas'              => ['required', 'array', 'min:1'],
            'lineas.*.orden_detalle_id' => ['required', 'integer'],
            'lineas.*.cantidad'   => ['required', 'numeric', 'gte:0', 'max:999999999'],
        ]);

        $orden->load(['detalle', 'recepciones']);
        $detallePorId = $orden->detalle->keyBy('id');

        // Cantidad ya recibida por línea (excluye recepciones anuladas).
        $recibido = CompraRecepcionDetalle::query()
            ->whereIn('recepcion_id', $orden->recepciones->where('estado', '!=', CompraRecepcion::ESTADO_ANULADO)->pluck('id'))
            ->selectRaw('orden_detalle_id, SUM(cantidad) AS recibido')
            ->groupBy('orden_detalle_id')
            ->pluck('recibido', 'orden_detalle_id');

        $aRecibir = [];

        foreach ($data['lineas'] as $linea) {
            $detId = (int) $linea['orden_detalle_id'];
            $cant  = round((float) $linea['cantidad'], 4);

            if ($cant <= 0) {
                continue;
            }

            $det = $detallePorId->get($detId);
            if (! $det) {
                throw ValidationException::withMessages(['lineas' => 'Una línea no pertenece a esta orden.']);
            }

            $pendiente = round((float) $det->cantidad - (float) ($recibido[$detId] ?? 0), 4);
            if ($cant - 0.0001 > $pendiente) {
                throw ValidationException::withMessages([
                    'lineas' => "La cantidad recibida de «{$det->descripcion}» excede lo pendiente ({$pendiente}).",
                ]);
            }

            $aRecibir[] = ['det' => $det, 'cantidad' => $cant];
        }

        if (empty($aRecibir)) {
            throw ValidationException::withMessages(['lineas' => 'Indica al menos una cantidad a recibir.']);
        }

        $companiaId = $orden->compania_id;

        // Almacén destino: el indicado o el primer almacén activo de la compañía.
        $almacenId = $data['almacen_id'] ?? InvAlmacen::where('compania_id', $companiaId)
            ->where('activo', true)->orderBy('codigo')->value('id');

        // Items de las líneas a recibir, para distinguir inventariables (PRODUCTO)
        // y su cuenta de inventario. Solo los bienes mueven stock y GRNI.
        $itemIds = collect($aRecibir)->pluck('det.item_id')->filter()->unique();
        $items = $itemIds->isEmpty() ? collect() : ItemProducto::whereIn('id', $itemIds)
            ->where('compania_id', $companiaId)
            ->get(['id', 'tipo', 'cuenta_inventario_id'])->keyBy('id');

        // Entradas de inventario (solo PRODUCTO) y su valor para el GRNI.
        $cuentaInvDefault = CuentaDefault::idPara($companiaId, 'INVENTARIO');
        $entradas = [];
        $debitosInv = []; // cuenta_id => monto
        foreach ($aRecibir as $r) {
            $det = $r['det'];
            $item = $det->item_id ? $items->get($det->item_id) : null;
            if (! $item || $item->tipo !== ItemProducto::TIPO_PRODUCTO) {
                continue; // servicios u otros no mueven inventario ni GRNI
            }
            $costoUnit = round((float) $det->precio_unitario, 4);
            $valor = round($r['cantidad'] * $costoUnit, 2);
            if ($valor <= 0) {
                continue;
            }
            $cuentaInv = $item->cuenta_inventario_id ?? $cuentaInvDefault;
            if (! $cuentaInv) {
                throw ValidationException::withMessages([
                    'lineas' => "El artículo «{$det->descripcion}» no tiene cuenta de inventario y la compañía no tiene cuenta default INVENTARIO.",
                ]);
            }
            $entradas[] = ['item_id' => (int) $det->item_id, 'cantidad' => $r['cantidad'], 'costo_unitario' => $costoUnit];
            $debitosInv[$cuentaInv] = round(($debitosInv[$cuentaInv] ?? 0) + $valor, 2);
        }

        // Si hay bienes a inventariar se requiere la cuenta puente GRNI y un almacén.
        $cuentaGrniId = CuentaDefault::idPara($companiaId, CuentaDefault::CLAVE_GRNI);
        if (! empty($entradas)) {
            if (! $almacenId) {
                throw ValidationException::withMessages(['almacen_id' => 'No hay un almacén activo para recibir la mercancía.']);
            }
            if (! $cuentaGrniId) {
                throw ValidationException::withMessages([
                    'recepcion' => 'La compañía no tiene configurada la cuenta default '.CuentaDefault::CLAVE_GRNI.' (Mercancía recibida no facturada). Configúrala para recibir mercancía con efecto contable.',
                ]);
            }
        }

        $recepcion = DB::transaction(function () use ($orden, $companiaId, $data, $aRecibir, $request, $almacenId, $entradas, $debitosInv, $cuentaGrniId) {
            $recepcion = CompraRecepcion::create([
                'compania_id'  => $companiaId,
                'orden_id'     => $orden->id,
                'proveedor_id' => $orden->proveedor_id,
                'almacen_id'   => $almacenId,
                'numero'       => CompraRecepcion::siguienteNumero($companiaId),
                'fecha'        => $data['fecha'],
                'estado'       => CompraRecepcion::ESTADO_RECIBIDO,
                'created_by'   => $request->user()->email,
            ]);

            foreach ($aRecibir as $item) {
                CompraRecepcionDetalle::create([
                    'recepcion_id'     => $recepcion->id,
                    'orden_detalle_id' => $item['det']->id,
                    'item_id'          => $item['det']->item_id,
                    'descripcion'      => $item['det']->descripcion,
                    'cantidad'         => $item['cantidad'],
                    'costo'            => $item['det']->precio_unitario,
                    'created_by'       => $request->user()->email,
                ]);
            }

            // Efecto contable + inventario solo si hay bienes inventariables:
            // Db Inventario / Cr Mercancía recibida no facturada (GRNI).
            if (! empty($entradas)) {
                $totalInv = round(array_sum($debitosInv), 2);
                $lineasAsiento = [];
                foreach ($debitosInv as $cuentaId => $monto) {
                    $lineasAsiento[] = [
                        'cuenta_id'   => (int) $cuentaId,
                        'descripcion' => "Recepción {$recepcion->numero} OC {$orden->numero}",
                        'debito'      => $monto,
                        'credito'     => 0,
                    ];
                }
                $lineasAsiento[] = [
                    'cuenta_id'   => $cuentaGrniId,
                    'contacto_id' => $orden->proveedor_id,
                    'descripcion' => "Mercancía recibida no facturada — recepción {$recepcion->numero}",
                    'debito'      => 0,
                    'credito'     => $totalInv,
                ];

                $asiento = app(AsientoAutomatico::class)->postear(
                    $companiaId,
                    $data['fecha'],
                    "Recepción de mercancía {$recepcion->numero} — OC {$orden->numero}",
                    $recepcion->numero,
                    $lineasAsiento,
                    'COMPRAS',
                    'compras_recepciones',
                    $recepcion->id,
                    $request->user(),
                );

                $recepcion->update(['asiento_id' => $asiento->id]);

                app(InventarioCompras::class)->registrarEntrada(
                    $companiaId,
                    $almacenId,
                    $data['fecha'],
                    $entradas,
                    $asiento->id,
                    'compras_recepciones',
                    $recepcion->id,
                    $request->user(),
                );
            }

            $orden->refresh();
            $orden->refrescarEstadoRecepcion();

            return $recepcion;
        });

        return redirect()->route('admin.compras.ordenes.show', $orden)
            ->with('status', "Recepción {$recepcion->numero} registrada.");
    }

    /**
     * Anula una recepción de mercancía. Marca la recepción como ANULADO (sus
     * cantidades dejan de contar como recibidas) y recalcula el estado de
     * recepción de la orden. No se permite si la orden ya fue facturada.
     */
    public function anular(Request $request, CompraOrden $orden, CompraRecepcion $recepcion): RedirectResponse
    {
        abort_unless($request->user()->can('compras.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($recepcion->orden_id === $orden->id, 404);

        if ($recepcion->estado === CompraRecepcion::ESTADO_ANULADO) {
            return back()->withErrors(['recepcion' => 'La recepción ya está anulada.']);
        }

        if (in_array($orden->estado, [CompraOrden::ESTADO_ANULADA, CompraOrden::ESTADO_CERRADA], true)) {
            return back()->withErrors(['recepcion' => 'La orden está anulada o cerrada; no se puede anular la recepción.']);
        }
        // Si algo de la orden ya fue facturado, anular una recepción podría dejar
        // facturado > recibido (GRNI descuadrado). Se bloquea.
        $orden->loadMissing('detalle');
        if ($orden->detalle->sum(fn ($d) => (float) $d->cantidad_facturada) > 0.0001) {
            return back()->withErrors(['recepcion' => 'La orden ya tiene facturas emitidas; anula primero las facturas en CxP para poder anular la recepción.']);
        }

        DB::transaction(function () use ($orden, $recepcion, $request) {
            // Reversa el efecto contable y de inventario del GRNI (si la recepción
            // lo generó). El asiento se anula (revierte saldos vía trigger) y las
            // existencias bajan por el costo de entrada.
            if ($recepcion->asiento_id) {
                app(AsientoAutomatico::class)->anular(
                    Asiento::find($recepcion->asiento_id),
                    $request->user(),
                );
            }
            app(InventarioCompras::class)->reversarPorDocumento(
                'compras_recepciones',
                $recepcion->id,
                $request->user(),
            );

            $recepcion->update([
                'estado'     => CompraRecepcion::ESTADO_ANULADO,
                'updated_by' => $request->user()->email,
            ]);

            $orden->refresh();
            $orden->refrescarEstadoRecepcion();
        });

        return redirect()->route('admin.compras.ordenes.show', $orden)
            ->with('status', "Recepción {$recepcion->numero} anulada; las cantidades volvieron a quedar pendientes.");
    }
}
