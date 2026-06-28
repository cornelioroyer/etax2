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
        ]);

        $ordenes = CompraOrden::query()
            ->with('proveedor')
            ->where('compania_id', $companiaId)
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($filtros['proveedor_id'] ?? null, fn ($q, $v) => $q->where('proveedor_id', $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
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
     * Genera una factura de compra (CxpDocumento) + asiento desde la orden.
     *
     * Modelo GRNI: los BIENES ya entraron al inventario en la recepción contra
     * la cuenta puente «Mercancía recibida no facturada» (GRNI); al facturar NO
     * se vuelve a mover inventario, solo se RECLASIFICA el puente a CxP:
     *   Db GRNI (bienes) + Db Gasto (servicios) + Db ITBMS / Cr CxP (o Banco).
     *
     * Soporta facturación PARCIAL y VARIAS facturas por orden (1:N): se factura
     * por línea hasta lo facturable (bienes: lo recibido no facturado; servicios:
     * lo ordenado no facturado), se acumula cantidad_facturada y se recalcula el
     * estado de la orden (PARCIALMENTE_FACTURADA / FACTURADA).
     */
    public function facturar(Request $request, CompraOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('compras.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        if (! $orden->esFacturable()) {
            return back()->withErrors(['orden' => 'La orden no tiene cantidades pendientes de facturar en su estado actual.']);
        }

        $companiaId = $orden->compania_id;

        $data = $request->validate([
            'numero'            => ['required', 'string', 'max:50'],
            'fecha'             => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha'],
            'forma_pago'        => ['nullable', Rule::in(['CREDITO', 'CONTADO', 'TARJETA'])],
            'cuenta_pago_id'    => [
                Rule::requiredIf(fn () => in_array($request->input('forma_pago'), ['CONTADO', 'TARJETA'], true)),
                'nullable', 'integer', Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
            'lineas'                     => ['nullable', 'array'],
            'lineas.*.orden_detalle_id'  => ['required_with:lineas', 'integer'],
            'lineas.*.cantidad'          => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
            'lineas.*.precio_unitario'   => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
            'autorizar_diferencia'       => ['nullable', 'boolean'],
            'motivo_diferencia'          => ['nullable', 'string', 'max:500'],
        ]);

        $usuario = $request->user();
        $orden->load(['detalle.impuesto', 'proveedor']);

        $cuentaCxpId   = CuentaDefault::idPara($companiaId, 'CXP');
        $cuentaItbmsId = CuentaDefault::idPara($companiaId, 'ITBMS_CREDITO');
        $cuentaGastoId = CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT');
        $cuentaGrniId  = CuentaDefault::idPara($companiaId, CuentaDefault::CLAVE_GRNI);

        if (! $cuentaCxpId) {
            return back()->withErrors(['orden' => 'La compañía no tiene configurada la cuenta default CXP.']);
        }

        // Forma de pago: la elegida o la predeterminada del proveedor.
        $formaPago = $data['forma_pago'] ?? ($orden->proveedor->forma_pago === Contacto::FORMA_PAGO_CONTADO ? 'CONTADO' : 'CREDITO');
        $contado   = in_array($formaPago, ['CONTADO', 'TARJETA'], true);

        // Ítems tipo PRODUCTO (sus bienes entraron al GRNI en la recepción).
        $cuentaInvDefaultId = CuentaDefault::idPara($companiaId, 'INVENTARIO');
        $itemIds  = $orden->detalle->pluck('item_id')->filter()->unique();
        $itemsMap = $itemIds->isNotEmpty()
            ? ItemProducto::whereIn('id', $itemIds)->get(['id', 'tipo', 'cuenta_inventario_id'])->keyBy('id')
            : collect();

        // Almacén donde entraron los bienes (para ajustar el valor del inventario
        // si el costo facturado difiere del de la orden): primer almacén de una
        // recepción vigente de la orden, o el primero activo de la compañía.
        $almacenVar = $orden->recepciones()->where('estado', '!=', \App\Models\CompraRecepcion::ESTADO_ANULADO)
            ->orderBy('fecha')->value('almacen_id')
            ?? InvAlmacen::where('compania_id', $companiaId)->where('activo', true)->orderBy('codigo')->value('id');

        // Cantidades y costos a facturar: los indicados por línea o, por defecto,
        // todo lo facturable al costo de la orden.
        $facturable  = $orden->facturablePorLinea();
        $pedidoCant  = [];
        $pedidoCosto = [];
        if (! empty($data['lineas'])) {
            foreach ($data['lineas'] as $l) {
                $id = (int) $l['orden_detalle_id'];
                $pedidoCant[$id] = round((float) ($l['cantidad'] ?? 0), 4);
                if (isset($l['precio_unitario']) && $l['precio_unitario'] !== '') {
                    $pedidoCosto[$id] = round((float) $l['precio_unitario'], 4);
                }
            }
        } else {
            $pedidoCant = $facturable;
        }

        $autorizado = (bool) ($data['autorizar_diferencia'] ?? false);

        // Construcción de líneas: valida topes, arma detalle/asiento, detecta
        // diferencias de costo OC↔factura y acumula totales y ajustes de inventario.
        $subtotal = 0.0; $impuestoTotal = 0.0;
        $detalleFactura = []; $lineasAsiento = []; $incrFacturado = [];
        $ajustesInv = []; $diferencias = [];
        $numLinea = 0;

        foreach ($orden->detalle as $linea) {
            $cant = round((float) ($pedidoCant[$linea->id] ?? 0), 4);
            if ($cant <= 0) {
                continue;
            }
            $tope = (float) ($facturable[$linea->id] ?? 0);
            if ($cant - 0.0001 > $tope) {
                throw ValidationException::withMessages([
                    'lineas' => "«{$linea->descripcion}»: se intenta facturar {$cant} pero solo hay {$tope} facturable (recibido no facturado).",
                ]);
            }

            $costoOC  = round((float) $linea->precio_unitario, 4);
            $costoInv = array_key_exists($linea->id, $pedidoCosto) ? $pedidoCosto[$linea->id] : $costoOC;

            $tasa     = (float) ($linea->impuesto->porcentaje ?? 0);
            $baseOC   = round($cant * $costoOC, 2);
            $baseInv  = round($cant * $costoInv, 2);
            $variance = round($baseInv - $baseOC, 2);
            $imp      = round($baseInv * $tasa / 100, 2);

            if (abs($costoInv - $costoOC) > 0.0001) {
                $diferencias[] = "«{$linea->descripcion}»: OC B/. ".number_format($costoOC, 2)." vs factura B/. ".number_format($costoInv, 2);
            }

            $item       = $linea->item_id ? $itemsMap->get($linea->item_id) : null;
            $esProducto = $item && $item->tipo === ItemProducto::TIPO_PRODUCTO;

            if ($imp > 0 && ! $cuentaItbmsId) {
                throw ValidationException::withMessages(['orden' => 'La compañía no tiene configurada la cuenta default ITBMS_CREDITO.']);
            }

            if ($esProducto) {
                // Bienes: reclasifica el GRNI por el costo de la ORDEN (lo que la
                // recepción acreditó) y lleva la DIFERENCIA de costo al inventario,
                // ajustando además el valor de existencias (kárdex = mayor).
                if (! $cuentaGrniId) {
                    throw ValidationException::withMessages([
                        'orden' => 'La compañía no tiene configurada la cuenta default '.CuentaDefault::CLAVE_GRNI.' (Mercancía recibida no facturada).',
                    ]);
                }
                $cuentaInvId = $item->cuenta_inventario_id ?? $cuentaInvDefaultId;
                if ($variance != 0.0 && ! $cuentaInvId) {
                    throw ValidationException::withMessages([
                        'orden' => "Hay diferencia de costo en «{$linea->descripcion}» pero no hay cuenta de inventario para registrarla.",
                    ]);
                }

                $lineasAsiento[] = [
                    'cuenta_id'   => $cuentaGrniId,
                    'descripcion' => $linea->descripcion,
                    'debito'      => $baseOC,
                    'credito'     => 0,
                ];
                if ($variance > 0) {
                    $lineasAsiento[] = ['cuenta_id' => $cuentaInvId, 'descripcion' => "Dif. costo {$linea->descripcion}", 'debito' => $variance, 'credito' => 0];
                } elseif ($variance < 0) {
                    $lineasAsiento[] = ['cuenta_id' => $cuentaInvId, 'descripcion' => "Dif. costo {$linea->descripcion}", 'debito' => 0, 'credito' => -$variance];
                }
                if ($variance != 0.0 && $almacenVar) {
                    $ajustesInv[] = ['item_id' => (int) $linea->item_id, 'delta' => $variance];
                }
                $cuentaDb = $cuentaGrniId;
            } else {
                // Servicios/otros: gasto por el costo facturado (no usan GRNI ni inventario).
                $cuentaDb = $linea->cuenta_id ?? $cuentaGastoId;
                if (! $cuentaDb) {
                    throw ValidationException::withMessages([
                        'orden' => "La línea «{$linea->descripcion}» no tiene cuenta contable y no hay GASTO_DEFAULT configurado.",
                    ]);
                }
                $lineasAsiento[] = [
                    'cuenta_id'   => $cuentaDb,
                    'descripcion' => $linea->descripcion,
                    'debito'      => $baseInv,
                    'credito'     => 0,
                ];
            }

            $subtotal      += $baseInv;
            $impuestoTotal += $imp;
            $numLinea++;

            $detalleFactura[] = [
                'linea'           => $numLinea,
                'orden_detalle_id'=> $linea->id,
                'item_id'         => $linea->item_id,
                'descripcion'     => $linea->descripcion,
                'cantidad'        => $cant,
                'precio_unitario' => $costoInv,
                'descuento'       => 0,
                'impuesto_monto'  => $imp,
                'total_linea'     => round($baseInv + $imp, 2),
                'cuenta_id'       => $cuentaDb,
            ];
            $incrFacturado[$linea->id] = $cant;
        }

        if (empty($detalleFactura)) {
            throw ValidationException::withMessages(['lineas' => 'No hay cantidades a facturar.']);
        }

        // Diferencias de costo OC↔factura: requieren autorización explícita.
        if (! empty($diferencias) && ! $autorizado) {
            throw ValidationException::withMessages([
                'autorizar_diferencia' => 'Hay diferencias de costo respecto a la orden: '.implode('; ', $diferencias)
                    .'. Marca "autorizar diferencia" e indica el motivo para continuar.',
            ]);
        }
        $referenciaDif = (! empty($diferencias) && $autorizado)
            ? 'Dif. costo OC autorizada por '.$usuario->email.($data['motivo_diferencia'] ?? '' ? ': '.$data['motivo_diferencia'] : '')
            : null;

        $subtotal      = round($subtotal, 2);
        $impuestoTotal = round($impuestoTotal, 2);
        $total         = round($subtotal + $impuestoTotal, 2);

        // Vencimiento: contado vence hoy; crédito usa la fecha indicada o los días del proveedor.
        if ($contado) {
            $vencimiento = $data['fecha'];
        } elseif (! empty($data['fecha_vencimiento'])) {
            $vencimiento = $data['fecha_vencimiento'];
        } else {
            $dias = (int) ($orden->proveedor->dias_credito ?: 30);
            $vencimiento = \Carbon\Carbon::parse($data['fecha'])->addDays($dias)->format('Y-m-d');
        }

        // Número único por proveedor (excluye ANULADO, igual que el índice parcial de BD).
        $duplicada = CxpDocumento::where('compania_id', $companiaId)
            ->where('proveedor_id', $orden->proveedor_id)
            ->where('tipo_documento', CxpDocumento::TIPO_FACTURA)
            ->where('numero', $data['numero'])
            ->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages(['numero' => "Ya existe la factura {$data['numero']} de ese proveedor."]);
        }

        $factura = DB::transaction(function () use (
            $orden, $data, $companiaId, $usuario, $cuentaCxpId, $cuentaItbmsId,
            $subtotal, $impuestoTotal, $total, $detalleFactura, $lineasAsiento,
            $incrFacturado, $contado, $formaPago, $vencimiento, $referenciaDif, $ajustesInv, $almacenVar
        ) {
            $factura = CxpDocumento::create([
                'compania_id'       => $companiaId,
                'proveedor_id'      => $orden->proveedor_id,
                'orden_id'          => $orden->id,
                'tipo_documento'    => CxpDocumento::TIPO_FACTURA,
                'numero'            => $data['numero'],
                'fecha'             => $data['fecha'],
                'fecha_vencimiento' => $vencimiento,
                'referencia'        => $referenciaDif,
                'subtotal'          => $subtotal,
                'descuento'         => 0,
                'impuesto'          => $impuestoTotal,
                'total'             => $total,
                'saldo'             => $contado ? 0 : $total,
                'estado'            => $contado ? CxpDocumento::ESTADO_PAGADO : CxpDocumento::ESTADO_PENDIENTE,
                'cuenta_pago_id'    => $contado ? ($data['cuenta_pago_id'] ?? null) : null,
                'created_by'        => $usuario->email,
            ]);

            foreach ($detalleFactura as $d) {
                CxpDocumentoDetalle::create($d + ['documento_id' => $factura->id, 'created_by' => $usuario->email]);
            }

            // Asiento: Db (GRNI bienes / Gasto servicios) + Db ITBMS / Cr CxP o Banco.
            if ($impuestoTotal > 0) {
                $lineasAsiento[] = [
                    'cuenta_id'   => $cuentaItbmsId,
                    'descripcion' => "ITBMS factura {$factura->numero}",
                    'debito'      => $impuestoTotal,
                    'credito'     => 0,
                ];
            }
            $cuentaCredito = $contado ? (int) $data['cuenta_pago_id'] : $cuentaCxpId;
            $lineasAsiento[] = [
                'cuenta_id'   => $cuentaCredito,
                'contacto_id' => $contado ? null : $orden->proveedor_id,
                'descripcion' => "Factura {$factura->numero} — ".$orden->proveedor->nombre,
                'debito'      => 0,
                'credito'     => $total,
            ];

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId, $data['fecha'],
                "Factura de compra {$factura->numero} — ".$orden->proveedor->nombre,
                $factura->numero, $lineasAsiento, 'CXP', 'cxp_documentos', $factura->id, $usuario,
            );

            $factura->update(['asiento_id' => $asiento->id]);

            // Ajuste de valor de inventario por diferencia de costo OC↔factura,
            // para que el kárdex siga cuadrando con el mayor (la cuenta de
            // inventario ya recibió el débito/crédito de la varianza en el asiento).
            if (! empty($ajustesInv) && $almacenVar) {
                $invSvc = app(InventarioCompras::class);
                foreach ($ajustesInv as $aj) {
                    $invSvc->ajustarValorExistencia($companiaId, (int) $almacenVar, $aj['item_id'], (float) $aj['delta'], $usuario);
                }
            }

            // Acumula lo facturado por línea y recalcula el estado de la orden.
            foreach ($incrFacturado as $detId => $cant) {
                CompraOrdenDetalle::where('id', $detId)
                    ->update(['cantidad_facturada' => DB::raw('cantidad_facturada + '.(float) $cant)]);
            }
            // Compat: deja el enlace al primer documento si aún no hay uno.
            if (! $orden->cxp_documento_id) {
                $orden->update(['cxp_documento_id' => $factura->id]);
            }
            $orden->load('detalle');
            $orden->refrescarEstadoFacturacion();

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
