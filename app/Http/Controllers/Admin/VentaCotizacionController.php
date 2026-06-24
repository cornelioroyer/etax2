<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Mail\CotizacionMail;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\TaxImpuesto;
use App\Models\CuentaDefault;
use App\Models\CxcDocumento;
use App\Models\CxcDocumentoDetalle;
use App\Models\VentaCotizacion;
use App\Models\VentaCotizacionDetalle;
use App\Models\VentaFactura;
use App\Models\VentaFacturaDetalle;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class VentaCotizacionController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    public function index(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'estado'     => ['nullable', Rule::in(['BORRADOR', 'ENVIADA', 'ACEPTADA', 'RECHAZADA', 'FACTURADA', 'ANULADA'])],
            'cliente_id' => ['nullable', 'integer'],
            'desde'      => ['nullable', 'date'],
            'hasta'      => ['nullable', 'date'],
        ]);

        $consulta = VentaCotizacion::query()
            ->with('cliente')
            ->where('compania_id', $companiaId)
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($filtros['cliente_id'] ?? null, fn ($q, $v) => $q->where('cliente_id', $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->orderByDesc('fecha')
            ->orderByDesc('numero');

        if ($request->query('export')) {
            $todas = (clone $consulta)->get();
            if ($export = $this->exportarReporte($request, 'admin.exports.listado', [
                'titulo' => 'Cotizaciones de venta',
                'compania' => Compania::find($companiaId)?->nombre ?? '',
                'subtitulo' => 'Listado al '.now()->format('d/m/Y').' — '.$todas->count().' cotizaciones',
                'encabezados' => [
                    ['titulo' => 'Número'], ['titulo' => 'Fecha'], ['titulo' => 'Válida hasta'],
                    ['titulo' => 'Cliente'], ['titulo' => 'Total', 'num' => true], ['titulo' => 'Estado'],
                ],
                'filas' => $todas->map(fn ($c) => [
                    $c->numero, $c->fecha->format('d/m/Y'),
                    $c->fecha_validez?->format('d/m/Y') ?? '',
                    $c->cliente->nombre ?? '',
                    number_format((float) $c->total, 2),
                    ucfirst(strtolower($c->estado)),
                ])->all(),
                'totales' => ['TOTAL', '', '', '', number_format((float) $todas->sum('total'), 2), ''],
            ], 'cotizaciones_'.now()->format('Y-m-d'))) {
                return $export;
            }
        }

        return view('admin.ventas.cotizaciones.index', [
            'cotizaciones' => $consulta->paginate(25)->withQueryString(),
            'filtros'      => $filtros,
            'clientes'     => $this->clientes($companiaId),
        ]);
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        return view('admin.ventas.cotizaciones.create', [
            'clientes'  => $this->clientes($companiaId),
            'impuestos' => TaxImpuesto::itbmsGlobales(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);

        $impuestosValidos = TaxImpuesto::itbmsGlobales()->pluck('id')->all();

        $data = $request->validate([
            'cliente_id'          => ['required', 'integer', Rule::exists('contact_contactos', 'id')->where('compania_id', $companiaId)],
            'fecha'               => ['required', 'date'],
            'fecha_validez'       => ['nullable', 'date', 'after_or_equal:fecha'],
            'notas'               => ['nullable', 'string', 'max:1000'],
            'lineas'              => ['required', 'array', 'min:1'],
            'lineas.*.descripcion'    => ['required', 'string', 'max:500'],
            'lineas.*.cantidad'       => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lineas.*.precio_unitario'=> ['required', 'numeric', 'gte:0', 'max:999999999'],
            'lineas.*.impuesto_id'    => ['required', 'integer', Rule::in($impuestosValidos)],
        ]);

        $impuestosMap = TaxImpuesto::itbmsGlobales()->keyBy('id');

        $lineas  = [];
        $subtotal = 0.0;
        $itbms   = 0.0;

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
                'descuento'       => 0,
                'impuesto_id'     => (int) $linea['impuesto_id'],
                'impuesto_monto'  => $impMonto,
                'total_linea'     => round($base + $impMonto, 2),
            ];
        }

        $subtotal = round($subtotal, 2);
        $itbms    = round($itbms, 2);
        $total    = round($subtotal + $itbms, 2);

        if ($total <= 0) {
            throw ValidationException::withMessages(['lineas' => 'El total de la cotización debe ser mayor que cero.']);
        }

        $cotizacion = DB::transaction(function () use ($companiaId, $data, $lineas, $subtotal, $itbms, $total, $request) {
            $cot = VentaCotizacion::create([
                'compania_id'   => $companiaId,
                'cliente_id'    => $data['cliente_id'],
                'numero'        => VentaCotizacion::siguienteNumero($companiaId),
                'fecha'         => $data['fecha'],
                'fecha_validez' => $data['fecha_validez'] ?? null,
                'subtotal'      => $subtotal,
                'descuento'     => 0,
                'itbms'         => $itbms,
                'total'         => $total,
                'estado'        => VentaCotizacion::ESTADO_BORRADOR,
                'extra'         => array_filter(['notas' => $data['notas'] ?? null]),
                'created_by'    => $request->user()->email,
            ]);

            foreach ($lineas as $linea) {
                VentaCotizacionDetalle::create($linea + ['cotizacion_id' => $cot->id, 'created_by' => $request->user()->email]);
            }

            return $cot;
        });

        return redirect()->route('admin.ventas.cotizaciones.show', $cotizacion)
            ->with('status', "Cotización {$cotizacion->numero} creada.");
    }

    public function show(Request $request, VentaCotizacion $cotizacion): View
    {
        abort_unless($cotizacion->compania_id === $this->companiaActivaId($request), 404);

        $cotizacion->load(['cliente', 'detalle.impuesto']);

        return view('admin.ventas.cotizaciones.show', ['cotizacion' => $cotizacion]);
    }

    /** Avanza el estado: BORRADOR→ENVIADA, o cambia a ACEPTADA/RECHAZADA. */
    public function cambiarEstado(Request $request, VentaCotizacion $cotizacion): RedirectResponse
    {
        abort_unless($cotizacion->compania_id === $this->companiaActivaId($request), 404);

        $transiciones = [
            VentaCotizacion::ESTADO_BORRADOR  => [VentaCotizacion::ESTADO_ENVIADA],
            VentaCotizacion::ESTADO_ENVIADA   => [VentaCotizacion::ESTADO_ACEPTADA, VentaCotizacion::ESTADO_RECHAZADA],
            VentaCotizacion::ESTADO_ACEPTADA  => [VentaCotizacion::ESTADO_RECHAZADA],
            VentaCotizacion::ESTADO_RECHAZADA => [VentaCotizacion::ESTADO_ACEPTADA],
        ];

        $permitidos = $transiciones[$cotizacion->estado] ?? [];

        $data = $request->validate([
            'estado' => ['required', Rule::in($permitidos ?: ['__ninguno__'])],
        ]);

        $cotizacion->update(['estado' => $data['estado'], 'updated_by' => $request->user()->email]);

        $etiqueta = [
            VentaCotizacion::ESTADO_ENVIADA   => 'enviada al cliente',
            VentaCotizacion::ESTADO_ACEPTADA  => 'marcada como aceptada',
            VentaCotizacion::ESTADO_RECHAZADA => 'marcada como rechazada',
        ][$data['estado']] ?? strtolower($data['estado']);

        return back()->with('status', "Cotización {$cotizacion->numero} {$etiqueta}.");
    }

    public function anular(Request $request, VentaCotizacion $cotizacion): RedirectResponse
    {
        abort_unless($cotizacion->compania_id === $this->companiaActivaId($request), 404);

        if ($cotizacion->estado === VentaCotizacion::ESTADO_FACTURADA) {
            return back()->withErrors(['cotizacion' => 'La cotización ya fue facturada.']);
        }

        if ($cotizacion->estado === VentaCotizacion::ESTADO_ANULADA) {
            return back()->withErrors(['cotizacion' => 'La cotización ya está anulada.']);
        }

        $cotizacion->update(['estado' => VentaCotizacion::ESTADO_ANULADA, 'updated_by' => $request->user()->email]);

        return back()->with('status', "Cotización {$cotizacion->numero} anulada.");
    }

    /**
     * Convierte la cotización en ventas_facturas + cxc_documentos + asiento.
     * Sólo se puede facturar desde BORRADOR, ENVIADA o ACEPTADA.
     */
    public function facturar(Request $request, VentaCotizacion $cotizacion): RedirectResponse
    {
        abort_unless($cotizacion->compania_id === $this->companiaActivaId($request), 404);

        if (! $cotizacion->esFacturable()) {
            return back()->withErrors(['cotizacion' => "La cotización está {$cotizacion->estado} y no se puede facturar."]);
        }

        // Verificar que no tenga ya una factura
        $yaFacturada = VentaFactura::where('cotizacion_id', $cotizacion->id)->exists();
        if ($yaFacturada) {
            return back()->withErrors(['cotizacion' => 'Esta cotización ya tiene una factura generada.']);
        }

        $data = $request->validate([
            'fecha'             => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha'],
        ]);

        $companiaId = $cotizacion->compania_id;
        $usuario    = $request->user();

        $cuentaCxcId   = CuentaDefault::idPara($companiaId, 'CXC');
        $cuentaItbmsId = CuentaDefault::idPara($companiaId, 'ITBMS_POR_PAGAR');
        $cuentaVentasId = CuentaDefault::idPara($companiaId, 'VENTAS');

        if (! $cuentaCxcId) {
            return back()->withErrors(['cotizacion' => 'La compañía no tiene configurada la cuenta default CXC.']);
        }

        $cotizacion->load('detalle.impuesto');

        $factura = DB::transaction(function () use ($cotizacion, $data, $companiaId, $usuario, $cuentaCxcId, $cuentaItbmsId, $cuentaVentasId) {
            $numero = VentaFactura::siguienteNumero($companiaId);

            // 1. ventas_facturas
            $factura = VentaFactura::create([
                'compania_id'       => $companiaId,
                'cliente_id'        => $cotizacion->cliente_id,
                'numero'            => $numero,
                'fecha'             => $data['fecha'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'subtotal'          => $cotizacion->subtotal,
                'descuento'         => $cotizacion->descuento,
                'itbms'             => $cotizacion->itbms,
                'total'             => $cotizacion->total,
                'saldo'             => $cotizacion->total,
                'estado'            => VentaFactura::ESTADO_EMITIDA,
                'cotizacion_id'     => $cotizacion->id,
                'created_by'        => $usuario->email,
            ]);

            // 2. ventas_facturas_detalle
            foreach ($cotizacion->detalle as $linea) {
                VentaFacturaDetalle::create([
                    'factura_id'       => $factura->id,
                    'linea'            => $linea->linea,
                    'descripcion'      => $linea->descripcion,
                    'cantidad'         => $linea->cantidad,
                    'precio_unitario'  => $linea->precio_unitario,
                    'descuento'        => $linea->descuento,
                    'impuesto_id'      => $linea->impuesto_id,
                    'impuesto_monto'   => $linea->impuesto_monto,
                    'total_linea'      => $linea->total_linea,
                    'cuenta_ingreso_id'=> $cuentaVentasId,
                    'created_by'       => $usuario->email,
                ]);
            }

            // 3. cxc_documentos (para cobros y antigüedad)
            $cxc = CxcDocumento::create([
                'compania_id'       => $companiaId,
                'cliente_id'        => $cotizacion->cliente_id,
                'tipo_documento'    => CxcDocumento::TIPO_FACTURA,
                'numero'            => $numero,
                'fecha'             => $data['fecha'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'subtotal'          => $cotizacion->subtotal,
                'descuento'         => 0,
                'impuesto'          => $cotizacion->itbms,
                'total'             => $cotizacion->total,
                'saldo'             => $cotizacion->total,
                'estado'            => CxcDocumento::ESTADO_PENDIENTE,
                'created_by'        => $usuario->email,
            ]);

            foreach ($cotizacion->detalle as $linea) {
                $base = round((float) $linea->total_linea - (float) $linea->impuesto_monto, 2);
                CxcDocumentoDetalle::create([
                    'documento_id'    => $cxc->id,
                    'linea'           => $linea->linea,
                    'descripcion'     => $linea->descripcion,
                    'cantidad'        => $linea->cantidad,
                    'precio_unitario' => $linea->precio_unitario,
                    'descuento'       => 0,
                    'impuesto_monto'  => $linea->impuesto_monto,
                    'total_linea'     => $linea->total_linea,
                    'cuenta_id'       => $cuentaVentasId,
                    'created_by'      => $usuario->email,
                ]);
            }

            // 4. Asiento contable
            $lineasAsiento = [[
                'cuenta_id'   => $cuentaCxcId,
                'contacto_id' => $cotizacion->cliente_id,
                'descripcion' => "Factura {$numero}",
                'debito'      => (float) $cotizacion->total,
                'credito'     => 0,
            ]];

            foreach ($cotizacion->detalle as $linea) {
                $base = round((float) $linea->total_linea - (float) $linea->impuesto_monto, 2);
                $lineasAsiento[] = [
                    'cuenta_id'   => $cuentaVentasId,
                    'descripcion' => $linea->descripcion,
                    'debito'      => 0,
                    'credito'     => $base,
                ];
            }

            if ((float) $cotizacion->itbms > 0 && $cuentaItbmsId) {
                $lineasAsiento[] = [
                    'cuenta_id'   => $cuentaItbmsId,
                    'descripcion' => "ITBMS factura {$numero}",
                    'debito'      => 0,
                    'credito'     => (float) $cotizacion->itbms,
                ];
            }

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId, $data['fecha'],
                "Factura de venta {$numero} — ".$cotizacion->cliente->nombre,
                $numero, $lineasAsiento, 'CXC', 'ventas_facturas', $factura->id, $usuario,
            );

            // 5. Vincular todo
            $factura->update(['cxc_documento_id' => $cxc->id, 'asiento_id' => $asiento->id]);
            $cxc->update(['asiento_id' => $asiento->id]);
            $cotizacion->update(['estado' => VentaCotizacion::ESTADO_FACTURADA, 'updated_by' => $usuario->email]);

            return $factura;
        });

        return redirect()->route('admin.ventas.facturas.show', $factura)
            ->with('status', "Factura {$factura->numero} emitida desde cotización {$cotizacion->numero}.");
    }

    public function imprimir(Request $request, VentaCotizacion $cotizacion): View
    {
        abort_unless($cotizacion->compania_id === $this->companiaActivaId($request), 404);

        $cotizacion->load(['cliente', 'detalle.impuesto']);
        $compania = Compania::find($cotizacion->compania_id);

        return view('admin.ventas.cotizaciones.print', compact('cotizacion', 'compania'));
    }

    public function enviarEmail(Request $request, VentaCotizacion $cotizacion): RedirectResponse
    {
        abort_unless($cotizacion->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'destinatario' => ['required', 'email', 'max:255'],
            'mensaje'      => ['nullable', 'string', 'max:1000'],
        ]);

        $cotizacion->load(['cliente', 'detalle.impuesto']);
        $compania = Compania::find($cotizacion->compania_id);

        Mail::to($data['destinatario'])->send(new CotizacionMail($cotizacion, $compania, $data['mensaje'] ?? null));

        return back()->with('status', "Cotización enviada a {$data['destinatario']}.");
    }

    private function clientes(int $companiaId)
    {
        return Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);
    }
}
