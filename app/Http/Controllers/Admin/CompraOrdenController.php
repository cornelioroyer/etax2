<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\CompraOrden;
use App\Models\CompraOrdenDetalle;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxpDocumento;
use App\Models\CxpDocumentoDetalle;
use App\Models\InvAlmacen;
use App\Models\ItemProducto;
use App\Models\TaxImpuesto;
use App\Services\AsientoAutomatico;
use App\Services\InventarioCompras;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompraOrdenController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'estado'       => ['nullable', Rule::in([
                CompraOrden::ESTADO_BORRADOR, CompraOrden::ESTADO_APROBADA,
                CompraOrden::ESTADO_RECIBIDA_PARCIAL, CompraOrden::ESTADO_RECIBIDA,
                CompraOrden::ESTADO_FACTURADA, CompraOrden::ESTADO_ANULADA,
            ])],
            'proveedor_id' => ['nullable', 'integer'],
            'desde'        => ['nullable', 'date'],
            'hasta'        => ['nullable', 'date'],
            'q'            => ['nullable', 'string', 'max:100'],
        ]);

        $ordenes = CompraOrden::query()
            ->with('proveedor')
            ->where('compania_id', $companiaId)
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($filtros['proveedor_id'] ?? null, fn ($q, $v) => $q->where('proveedor_id', $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->when($filtros['q'] ?? null, function ($q, $texto) {
                $b = '%'.mb_strtolower($texto).'%';
                $q->where(fn ($q) => $q
                    ->whereRaw('LOWER(numero) LIKE ?', [$b])
                    ->orWhereHas('proveedor', fn ($c) => $c->whereRaw('LOWER(nombre) LIKE ?', [$b]))
                );
            })
            ->orderByDesc('fecha')
            ->orderByDesc('numero')
            ->paginate(25)
            ->withQueryString();

        return view('admin.compras.ordenes.index', [
            'ordenes'     => $ordenes,
            'filtros'     => $filtros,
            'proveedores' => $this->proveedores($companiaId),
        ]);
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        return view('admin.compras.ordenes.create', [
            'proveedores' => $this->proveedores($companiaId),
            'impuestos'   => TaxImpuesto::itbmsGlobales(),
            'cuentas'     => $this->cuentasGasto($companiaId),
            'items'       => $this->itemsCompra($companiaId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('compras.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $impuestosValidos = TaxImpuesto::itbmsGlobales()->pluck('id')->all();
        $cuentasValidas   = $this->cuentasGasto($companiaId)->pluck('id')->all();

        $data = $request->validate([
            'proveedor_id'             => ['required', 'integer', Rule::exists('contact_contactos', 'id')->where('compania_id', $companiaId)],
            'fecha'                    => ['required', 'date'],
            'observaciones'            => ['nullable', 'string', 'max:2000'],
            'lineas'                   => ['required', 'array', 'min:1'],
            'lineas.*.descripcion'     => ['required', 'string', 'max:500'],
            'lineas.*.cantidad'        => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'gte:0', 'max:999999999'],
            'lineas.*.impuesto_id'     => ['required', 'integer', Rule::in($impuestosValidos)],
            'lineas.*.cuenta_id'       => ['nullable', 'integer', Rule::in($cuentasValidas)],
            'lineas.*.item_id'         => ['nullable', 'integer'],
        ]);

        $impuestosMap = TaxImpuesto::itbmsGlobales()->keyBy('id');

        $lineas   = [];
        $subtotal = 0.0;
        $itbms    = 0.0;

        foreach (array_values($data['lineas']) as $i => $linea) {
            $cantidad = round((float) $linea['cantidad'], 4);
            $precio   = round((float) $linea['precio_unitario'], 4);
            $base     = round($cantidad * $precio, 2);
            $tasa     = (float) ($impuestosMap[(int) $linea['impuesto_id']]->porcentaje ?? 0);
            $impMonto = round($base * $tasa / 100, 2);

            $subtotal += $base;
            $itbms    += $impMonto;

            $lineas[] = [
                'linea'           => $i + 1,
                'item_id'         => ! empty($linea['item_id']) ? (int) $linea['item_id'] : null,
                'descripcion'     => $linea['descripcion'],
                'cantidad'        => $cantidad,
                'precio_unitario' => $precio,
                'impuesto_id'     => (int) $linea['impuesto_id'],
                'cuenta_id'       => ! empty($linea['cuenta_id']) ? (int) $linea['cuenta_id'] : null,
                'total_linea'     => round($base + $impMonto, 2),
            ];
        }

        $subtotal = round($subtotal, 2);
        $itbms    = round($itbms, 2);
        $total    = round($subtotal + $itbms, 2);

        if ($total <= 0) {
            throw ValidationException::withMessages(['lineas' => 'El total de la orden debe ser mayor que cero.']);
        }

        $orden = DB::transaction(function () use ($companiaId, $data, $lineas, $subtotal, $itbms, $total, $request) {
            $orden = CompraOrden::create([
                'compania_id'   => $companiaId,
                'proveedor_id'  => $data['proveedor_id'],
                'numero'        => CompraOrden::siguienteNumero($companiaId),
                'fecha'         => $data['fecha'],
                'estado'        => CompraOrden::ESTADO_BORRADOR,
                'subtotal'      => $subtotal,
                'itbms'         => $itbms,
                'total'         => $total,
                'observaciones' => $data['observaciones'] ?? null,
                'created_by'    => $request->user()->email,
            ]);

            foreach ($lineas as $linea) {
                CompraOrdenDetalle::create($linea + ['orden_id' => $orden->id, 'created_by' => $request->user()->email]);
            }

            return $orden;
        });

        return redirect()->route('admin.compras.ordenes.show', $orden)
            ->with('status', "Orden de compra {$orden->numero} creada.");
    }

    public function show(Request $request, CompraOrden $orden): View
    {
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        $orden->load(['proveedor', 'detalle.impuesto', 'detalle.cuenta', 'recepciones.detalle', 'cxpDocumento']);

        $recibido = \App\Models\CompraRecepcionDetalle::query()
            ->whereIn('recepcion_id', $orden->recepciones->pluck('id'))
            ->selectRaw('orden_detalle_id, SUM(cantidad) AS recibido')
            ->groupBy('orden_detalle_id')
            ->pluck('recibido', 'orden_detalle_id');

        // ¿La orden tiene líneas inventariables (ítem tipo PRODUCTO)?  Solo en
        // ese caso ofrecemos elegir almacén al facturar (entrada a inventario).
        $itemIds          = $orden->detalle->pluck('item_id')->filter()->unique();
        $tieneInventariables = $itemIds->isNotEmpty() && ItemProducto::whereIn('id', $itemIds)
            ->where('tipo', ItemProducto::TIPO_PRODUCTO)
            ->exists();
        $almacenes = $tieneInventariables
            ? InvAlmacen::where('compania_id', $orden->compania_id)->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre'])
            : collect();

        return view('admin.compras.ordenes.show', [
            'orden'     => $orden,
            'recibido'  => $recibido,
            'almacenes' => $almacenes,
        ]);
    }

    public function edit(Request $request, CompraOrden $orden): View
    {
        abort_unless($request->user()->can('compras.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        if ($orden->estado !== CompraOrden::ESTADO_BORRADOR) {
            abort(403, 'Solo se puede editar una orden en borrador.');
        }

        $orden->load(['detalle.impuesto']);

        return view('admin.compras.ordenes.edit', [
            'orden'       => $orden,
            'proveedores' => $this->proveedores($orden->compania_id),
            'impuestos'   => TaxImpuesto::itbmsGlobales(),
            'cuentas'     => $this->cuentasGasto($orden->compania_id),
            'items'       => $this->itemsCompra($orden->compania_id),
        ]);
    }

    public function update(Request $request, CompraOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('compras.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        if ($orden->estado !== CompraOrden::ESTADO_BORRADOR) {
            return back()->withErrors(['orden' => 'Solo se puede editar una orden en borrador.']);
        }

        $companiaId     = $orden->compania_id;
        $impuestosValidos = TaxImpuesto::itbmsGlobales()->pluck('id')->all();
        $cuentasValidas   = $this->cuentasGasto($companiaId)->pluck('id')->all();

        $data = $request->validate([
            'proveedor_id'             => ['required', 'integer', Rule::exists('contact_contactos', 'id')->where('compania_id', $companiaId)],
            'fecha'                    => ['required', 'date'],
            'observaciones'            => ['nullable', 'string', 'max:2000'],
            'lineas'                   => ['required', 'array', 'min:1'],
            'lineas.*.descripcion'     => ['required', 'string', 'max:500'],
            'lineas.*.cantidad'        => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'gte:0', 'max:999999999'],
            'lineas.*.impuesto_id'     => ['required', 'integer', Rule::in($impuestosValidos)],
            'lineas.*.cuenta_id'       => ['nullable', 'integer', Rule::in($cuentasValidas)],
        ]);

        $impuestosMap = TaxImpuesto::itbmsGlobales()->keyBy('id');

        $lineas   = [];
        $subtotal = 0.0;
        $itbms    = 0.0;

        foreach (array_values($data['lineas']) as $i => $linea) {
            $cantidad = round((float) $linea['cantidad'], 4);
            $precio   = round((float) $linea['precio_unitario'], 4);
            $base     = round($cantidad * $precio, 2);
            $tasa     = (float) ($impuestosMap[(int) $linea['impuesto_id']]->porcentaje ?? 0);
            $impMonto = round($base * $tasa / 100, 2);

            $subtotal += $base;
            $itbms    += $impMonto;

            $lineas[] = [
                'linea'           => $i + 1,
                'descripcion'     => $linea['descripcion'],
                'cantidad'        => $cantidad,
                'precio_unitario' => $precio,
                'impuesto_id'     => (int) $linea['impuesto_id'],
                'cuenta_id'       => ! empty($linea['cuenta_id']) ? (int) $linea['cuenta_id'] : null,
                'total_linea'     => round($base + $impMonto, 2),
            ];
        }

        $subtotal = round($subtotal, 2);
        $itbms    = round($itbms, 2);
        $total    = round($subtotal + $itbms, 2);

        if ($total <= 0) {
            throw ValidationException::withMessages(['lineas' => 'El total de la orden debe ser mayor que cero.']);
        }

        DB::transaction(function () use ($orden, $data, $lineas, $subtotal, $itbms, $total, $request) {
            $orden->update([
                'proveedor_id'  => $data['proveedor_id'],
                'fecha'         => $data['fecha'],
                'subtotal'      => $subtotal,
                'itbms'         => $itbms,
                'total'         => $total,
                'observaciones' => $data['observaciones'] ?? null,
                'updated_by'    => $request->user()->email,
            ]);

            $orden->detalle()->delete();

            foreach ($lineas as $linea) {
                CompraOrdenDetalle::create($linea + ['orden_id' => $orden->id, 'created_by' => $request->user()->email]);
            }
        });

        return redirect()->route('admin.compras.ordenes.show', $orden)
            ->with('status', "Orden {$orden->numero} actualizada.");
    }

    public function aprobar(Request $request, CompraOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('compras.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        if ($orden->estado !== CompraOrden::ESTADO_BORRADOR) {
            return back()->withErrors(['orden' => 'Sólo se puede aprobar una orden en borrador.']);
        }

        $orden->update(['estado' => CompraOrden::ESTADO_APROBADA, 'updated_by' => $request->user()->email]);

        return back()->with('status', "Orden {$orden->numero} aprobada.");
    }

    public function anular(Request $request, CompraOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('compras.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        if ($orden->estado === CompraOrden::ESTADO_FACTURADA) {
            return back()->withErrors(['orden' => 'La orden ya fue facturada; anula la factura en CxP.']);
        }
        if ($orden->estado === CompraOrden::ESTADO_ANULADA) {
            return back()->withErrors(['orden' => 'La orden ya está anulada.']);
        }

        $orden->update(['estado' => CompraOrden::ESTADO_ANULADA, 'updated_by' => $request->user()->email]);

        return back()->with('status', "Orden {$orden->numero} anulada.");
    }

    /**
     * Genera la factura de compra (CxpDocumento) + asiento desde la orden.
     * Usa la cuenta_id de cada línea; si no tiene, cae en GASTO_DEFAULT.
     */
    public function facturar(Request $request, CompraOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('compras.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        if (! $orden->esFacturable()) {
            return back()->withErrors(['orden' => 'La orden no se puede facturar en su estado actual o ya tiene factura.']);
        }

        $companiaId = $orden->compania_id;

        $data = $request->validate([
            'numero'            => ['required', 'string', 'max:50'],
            'fecha'             => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha'],
            'almacen_id'        => ['nullable', 'integer', Rule::exists('inv_almacenes', 'id')->where('compania_id', $companiaId)],
        ]);

        $usuario = $request->user();

        $cuentaCxpId        = CuentaDefault::idPara($companiaId, 'CXP');
        $cuentaItbmsId      = CuentaDefault::idPara($companiaId, 'ITBMS_CREDITO');
        $cuentaGastoId      = CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT');
        $cuentaInvDefaultId = CuentaDefault::idPara($companiaId, 'INVENTARIO');

        if (! $cuentaCxpId) {
            return back()->withErrors(['orden' => 'La compañía no tiene configurada la cuenta default CXP.']);
        }

        $orden->load('detalle.impuesto');

        // Ítems inventariables (tipo PRODUCTO) de la orden, para enrutar su
        // débito a la cuenta de inventario y subir existencias al facturar.
        $itemIds  = $orden->detalle->pluck('item_id')->filter()->unique();
        $itemsMap = $itemIds->isNotEmpty()
            ? ItemProducto::whereIn('id', $itemIds)->get(['id', 'tipo', 'cuenta_inventario_id'])->keyBy('id')
            : collect();

        // Almacén destino: el elegido, o el primero activo de la compañía.
        $almacenId = $data['almacen_id']
            ?? InvAlmacen::where('compania_id', $companiaId)->where('activo', true)->orderBy('codigo')->value('id');

        $impuesto = (float) $orden->itbms;
        if ($impuesto > 0 && ! $cuentaItbmsId) {
            throw ValidationException::withMessages(['orden' => 'La compañía no tiene configurada la cuenta default ITBMS_CREDITO.']);
        }

        // Verificar que toda línea tenga cuenta resolvible
        foreach ($orden->detalle as $linea) {
            if (! $linea->cuenta_id && ! $cuentaGastoId) {
                throw ValidationException::withMessages(['orden' =>
                    "La línea «{$linea->descripcion}» no tiene cuenta contable y la compañía no tiene GASTO_DEFAULT configurado."]);
            }
        }

        $duplicada = CxpDocumento::where('compania_id', $companiaId)
            ->where('proveedor_id', $orden->proveedor_id)
            ->where('tipo_documento', CxpDocumento::TIPO_FACTURA)
            ->where('numero', $data['numero'])
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages(['numero' => "Ya existe la factura {$data['numero']} de ese proveedor."]);
        }

        $factura = DB::transaction(function () use ($orden, $data, $companiaId, $usuario, $cuentaCxpId, $cuentaItbmsId, $cuentaGastoId, $cuentaInvDefaultId, $impuesto, $itemsMap, $almacenId) {
            $factura = CxpDocumento::create([
                'compania_id'       => $companiaId,
                'proveedor_id'      => $orden->proveedor_id,
                'tipo_documento'    => CxpDocumento::TIPO_FACTURA,
                'numero'            => $data['numero'],
                'fecha'             => $data['fecha'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'subtotal'          => $orden->subtotal,
                'descuento'         => 0,
                'impuesto'          => $orden->itbms,
                'total'             => $orden->total,
                'saldo'             => $orden->total,
                'estado'            => CxpDocumento::ESTADO_PENDIENTE,
                'created_by'        => $usuario->email,
            ]);

            $lineasAsiento  = [];
            $entradasInv    = [];

            foreach ($orden->detalle as $linea) {
                $impLinea  = round((float) $linea->cantidad * (float) $linea->precio_unitario * (float) ($linea->impuesto->porcentaje ?? 0) / 100, 2);
                $baseLinea = round((float) $linea->total_linea - $impLinea, 2);

                // ¿Línea inventariable? Ítem tipo PRODUCTO con cuenta de
                // inventario resolvible y almacén destino disponible. Si no, se
                // comporta como hasta hoy (débito a gasto, sin tocar stock).
                $item       = $linea->item_id ? $itemsMap->get($linea->item_id) : null;
                $cuentaInvId = $item && $item->tipo === ItemProducto::TIPO_PRODUCTO
                    ? ($item->cuenta_inventario_id ?? $cuentaInvDefaultId)
                    : null;
                $esInventario = $cuentaInvId && $almacenId;

                $cuentaId = $esInventario
                    ? $cuentaInvId
                    : ($linea->cuenta_id ?? $cuentaGastoId);

                if ($esInventario && (float) $linea->cantidad > 0) {
                    $entradasInv[] = [
                        'item_id'        => (int) $linea->item_id,
                        'cantidad'       => (float) $linea->cantidad,
                        'costo_unitario' => round($baseLinea / (float) $linea->cantidad, 4),
                    ];
                }

                CxpDocumentoDetalle::create([
                    'documento_id'    => $factura->id,
                    'linea'           => $linea->linea,
                    'descripcion'     => $linea->descripcion,
                    'cantidad'        => $linea->cantidad,
                    'precio_unitario' => $linea->precio_unitario,
                    'impuesto_monto'  => $impLinea,
                    'total_linea'     => $linea->total_linea,
                    'cuenta_id'       => $cuentaId,
                    'created_by'      => $usuario->email,
                ]);

                $lineasAsiento[] = [
                    'cuenta_id'   => $cuentaId,
                    'descripcion' => $linea->descripcion,
                    'debito'      => $baseLinea,
                    'credito'     => 0,
                ];
            }

            if ($impuesto > 0) {
                $lineasAsiento[] = [
                    'cuenta_id'   => $cuentaItbmsId,
                    'descripcion' => "ITBMS factura {$factura->numero}",
                    'debito'      => $impuesto,
                    'credito'     => 0,
                ];
            }

            $lineasAsiento[] = [
                'cuenta_id'   => $cuentaCxpId,
                'contacto_id' => $orden->proveedor_id,
                'descripcion' => "Factura {$factura->numero}",
                'debito'      => 0,
                'credito'     => (float) $orden->total,
            ];

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId, $data['fecha'],
                "Factura de compra {$factura->numero} — ".$orden->proveedor->nombre,
                $factura->numero, $lineasAsiento, 'CXP', 'cxp_documentos', $factura->id, $usuario,
            );

            $factura->update(['asiento_id' => $asiento->id]);
            $orden->update([
                'estado'           => CompraOrden::ESTADO_FACTURADA,
                'cxp_documento_id' => $factura->id,
                'updated_by'       => $usuario->email,
            ]);

            // Entrada a inventario de las líneas inventariables (sube stock al
            // costo de la factura; la contabilidad ya va en el asiento de arriba).
            if (! empty($entradasInv)) {
                app(InventarioCompras::class)->registrarEntrada(
                    $companiaId, $almacenId, $data['fecha'], $entradasInv,
                    $asiento->id, 'cxp_documentos', $factura->id, $usuario,
                );
            }

            return $factura;
        });

        return redirect()->route('admin.cxp.facturas.show', $factura)
            ->with('status', "Factura {$factura->numero} generada desde la orden {$orden->numero}.");
    }

    public function imprimir(Request $request, CompraOrden $orden): View
    {
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        $orden->load(['proveedor', 'detalle.impuesto']);
        $compania = Compania::find($orden->compania_id);

        return view('admin.compras.ordenes.print', compact('orden', 'compania'));
    }

    private function proveedores(int $companiaId)
    {
        return Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'PROVEEDOR'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);
    }

    private function cuentasGasto(int $companiaId)
    {
        return CuentaContable::where('compania_id', $companiaId)
            ->where('activa', true)
            ->where('permite_movimiento', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);
    }

    private function itemsCompra(int $companiaId)
    {
        return ItemProducto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'costo', 'impuesto_id', 'cuenta_gasto_id']);
    }
}
