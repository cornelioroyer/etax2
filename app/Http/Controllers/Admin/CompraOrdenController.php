<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\CompraOrden;
use App\Models\CompraOrdenDetalle;
use App\Models\Contacto;
use App\Models\CuentaDefault;
use App\Models\CxpDocumento;
use App\Models\CxpDocumentoDetalle;
use App\Models\TaxImpuesto;
use App\Services\AsientoAutomatico;
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('compras.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $impuestosValidos = TaxImpuesto::itbmsGlobales()->pluck('id')->all();

        $data = $request->validate([
            'proveedor_id'             => ['required', 'integer', Rule::exists('contact_contactos', 'id')->where('compania_id', $companiaId)],
            'fecha'                    => ['required', 'date'],
            'lineas'                   => ['required', 'array', 'min:1'],
            'lineas.*.descripcion'     => ['required', 'string', 'max:500'],
            'lineas.*.cantidad'        => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'gte:0', 'max:999999999'],
            'lineas.*.impuesto_id'     => ['required', 'integer', Rule::in($impuestosValidos)],
        ]);

        $impuestosMap = TaxImpuesto::itbmsGlobales()->keyBy('id');

        $lineas = [];
        $subtotal = 0.0;
        $itbms = 0.0;

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
                'compania_id'  => $companiaId,
                'proveedor_id' => $data['proveedor_id'],
                'numero'       => CompraOrden::siguienteNumero($companiaId),
                'fecha'        => $data['fecha'],
                'estado'       => CompraOrden::ESTADO_BORRADOR,
                'subtotal'     => $subtotal,
                'itbms'        => $itbms,
                'total'        => $total,
                'created_by'   => $request->user()->email,
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

        $orden->load(['proveedor', 'detalle.impuesto', 'recepciones.detalle', 'cxpDocumento']);

        // Cantidad recibida por línea, para el formulario de recepción.
        $recibido = \App\Models\CompraRecepcionDetalle::query()
            ->whereIn('recepcion_id', $orden->recepciones->pluck('id'))
            ->selectRaw('orden_detalle_id, SUM(cantidad) AS recibido')
            ->groupBy('orden_detalle_id')
            ->pluck('recibido', 'orden_detalle_id');

        return view('admin.compras.ordenes.show', [
            'orden'    => $orden,
            'recibido' => $recibido,
        ]);
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
     * Reusa la maquinaria contable de CxP. Usa GASTO_DEFAULT por línea.
     */
    public function facturar(Request $request, CompraOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('compras.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        if (! $orden->esFacturable()) {
            return back()->withErrors(['orden' => 'La orden no se puede facturar en su estado actual o ya tiene factura.']);
        }

        $data = $request->validate([
            'numero'            => ['required', 'string', 'max:50'],
            'fecha'             => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha'],
        ]);

        $companiaId = $orden->compania_id;
        $usuario    = $request->user();

        $cuentaCxpId   = CuentaDefault::idPara($companiaId, 'CXP');
        $cuentaItbmsId = CuentaDefault::idPara($companiaId, 'ITBMS_CREDITO');
        $cuentaGastoId = CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT');

        if (! $cuentaCxpId) {
            return back()->withErrors(['orden' => 'La compañía no tiene configurada la cuenta default CXP.']);
        }
        if (! $cuentaGastoId) {
            return back()->withErrors(['orden' => 'La compañía no tiene configurada la cuenta default GASTO_DEFAULT para la contrapartida de compras.']);
        }

        $duplicada = CxpDocumento::where('compania_id', $companiaId)
            ->where('proveedor_id', $orden->proveedor_id)
            ->where('tipo_documento', CxpDocumento::TIPO_FACTURA)
            ->where('numero', $data['numero'])
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages(['numero' => "Ya existe la factura {$data['numero']} de ese proveedor."]);
        }

        $orden->load('detalle.impuesto');

        $impuesto = (float) $orden->itbms;
        if ($impuesto > 0 && ! $cuentaItbmsId) {
            throw ValidationException::withMessages(['orden' => 'La compañía no tiene configurada la cuenta default ITBMS_CREDITO.']);
        }

        $factura = DB::transaction(function () use ($orden, $data, $companiaId, $usuario, $cuentaCxpId, $cuentaItbmsId, $cuentaGastoId, $impuesto) {
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

            $lineasAsiento = [];

            foreach ($orden->detalle as $linea) {
                $impLinea = round((float) $linea->cantidad * (float) $linea->precio_unitario * (float) ($linea->impuesto->porcentaje ?? 0) / 100, 2);
                $baseLinea = round((float) $linea->total_linea - $impLinea, 2);

                CxpDocumentoDetalle::create([
                    'documento_id'   => $factura->id,
                    'linea'          => $linea->linea,
                    'descripcion'    => $linea->descripcion,
                    'cantidad'       => $linea->cantidad,
                    'precio_unitario'=> $linea->precio_unitario,
                    'impuesto_monto' => $impLinea,
                    'total_linea'    => $linea->total_linea,
                    'cuenta_id'      => $cuentaGastoId,
                    'created_by'     => $usuario->email,
                ]);

                $lineasAsiento[] = [
                    'cuenta_id'   => $cuentaGastoId,
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

            return $factura;
        });

        return redirect()->route('admin.cxp.facturas.show', $factura)
            ->with('status', "Factura {$factura->numero} generada desde la orden {$orden->numero}.");
    }

    private function proveedores(int $companiaId)
    {
        return Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'PROVEEDOR'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);
    }
}
