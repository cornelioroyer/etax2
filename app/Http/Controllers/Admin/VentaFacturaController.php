<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\EmparejaContactos;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Exports\VentasPlantillaExport;
use App\Imports\VentasFacturasImport;
use App\Imports\VentasGenericoImport;
use App\Jobs\ProcesarImportacionVentasFel;
use App\Models\Compania;
use App\Models\Contacto;
use App\Services\CalculoDocumento;
use App\Models\TipoContacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\FelConfiguracion;
use App\Models\FelDocumento;
use App\Models\FelDocumentoDetalle;
use App\Models\ItemProducto;
use App\Models\CxcDocumento;
use App\Models\CxcDocumentoDetalle;
use App\Models\TaxImpuesto;
use App\Models\VentaCotizacion;
use App\Models\VentaFactura;
use App\Models\VentaFacturaDetalle;
use App\Models\VentaNotaCredito;
use App\Models\VentasImportacion;
use App\Services\AsientoAutomatico;
use App\Services\DgiFepConsulta;
use App\Services\FelDocumentoBuilder;
use App\Services\FelService;
use App\Services\InventarioVentas;
use App\Services\RucDigitoVerificador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class VentaFacturaController extends Controller
{
    use ConCompaniaActiva;
    use EmparejaContactos;
    use ExportaReporte;

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        return view('admin.ventas.facturas.create', [
            'clientes'       => $this->clientes($companiaId),
            'impuestos'      => TaxImpuesto::itbmsGlobales(),
            'numeroPreview'  => VentaFactura::siguienteNumero($companiaId),
            'cuentasIngreso' => $this->cuentasIngreso($companiaId),
            'cuentaVentasId' => CuentaDefault::idPara($companiaId, 'VENTAS'),
            'items'          => $this->itemsVenta($companiaId),
            // Para el formulario de nota de crédito integrado (selector de tipo):
            'facturasAbiertas' => VentaFactura::where('compania_id', $companiaId)
                ->whereIn('estado', [VentaFactura::ESTADO_EMITIDA, VentaFactura::ESTADO_PARCIAL])
                ->where('saldo', '>', 0)
                ->with('cliente:id,nombre')
                ->orderBy('numero')
                ->get(['id', 'numero', 'saldo', 'cliente_id'])
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'numero' => $f->numero,
                    'saldo' => (float) $f->saldo,
                    'cliente_id' => $f->cliente_id,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'cliente_id'              => ['required', 'integer', 'exists:contact_contactos,id'],
            'fecha'                   => ['required', 'date'],
            'fecha_vencimiento'       => ['nullable', 'date', 'after_or_equal:fecha'],
            'notas'                   => ['nullable', 'string', 'max:1000'],
            'descuento_general'       => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lineas'                  => ['required', 'array', 'min:1'],
            'lineas.*.item_id'           => ['nullable', 'integer', 'exists:item_productos_servicios,id'],
            'lineas.*.descripcion'       => ['required', 'string', 'max:500'],
            'lineas.*.cantidad'          => ['required', 'numeric', 'min:0.0001'],
            'lineas.*.precio_unitario'   => ['required', 'numeric', 'min:0'],
            'lineas.*.descuento'         => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lineas.*.impuesto_id'       => ['required', 'integer', Rule::in(TaxImpuesto::itbmsGlobales()->pluck('id')->all())],
            'lineas.*.cuenta_ingreso_id' => ['nullable', 'integer', 'exists:cgl_cuentas,id'],
        ]);

        $usuario = $request->user();
        $accion  = $request->input('accion', 'emitir');

        // Vencimiento automático: si el cliente es a crédito y no se indicó una
        // fecha de vencimiento, se calcula como fecha + días de crédito del cliente.
        $cliente     = Contacto::where('compania_id', $companiaId)->findOrFail($data['cliente_id']);
        $vencimiento = $data['fecha_vencimiento']
            ?? ($cliente->esCredito() ? $cliente->calcularVencimiento($data['fecha']) : null);

        // Numeración: automática (siguienteNumero) o manual escrita por el usuario.
        // Si es manual se valida tanto al emitir como al guardar borrador (en el
        // borrador el número queda reservado en extra y se aplica al emitir).
        $numeracion   = $request->input('numeracion') === 'manual' ? 'manual' : 'auto';
        $numeroManual = trim((string) $request->input('numero_manual', ''));
        if ($numeracion === 'manual') {
            $request->validate(['numero_manual' => ['required', 'string', 'max:50']]);
            if ($this->numeroExiste($companiaId, $numeroManual)) {
                return back()->withInput()->withErrors(['numero_manual' => 'Ya existe un documento con el número '.$numeroManual.'.']);
            }
        }

        $impuestos = TaxImpuesto::whereIn('id', collect($data['lineas'])->pluck('impuesto_id')->unique())->get()->keyBy('id');

        $entradas = [];
        foreach ($data['lineas'] as $linea) {
            $entradas[] = array_merge($linea, [
                'tasa' => (float) ($impuestos[$linea['impuesto_id']]->porcentaje ?? 0),
            ]);
        }
        $calc       = CalculoDocumento::calcular($entradas, (float) ($data['descuento_general'] ?? 0));
        $lineasCalc = $calc['lineas'];
        $subtotal   = $calc['subtotal'];
        $descuento  = $calc['descuento'];
        $itbms      = $calc['itbms'];
        $total      = $calc['total'];

        if ($accion === 'borrador') {
            if (VentaFactura::where('compania_id', $companiaId)->where('estado', VentaFactura::ESTADO_BORRADOR)->exists()) {
                return back()->withInput()->withErrors(['factura' => 'Ya existe un borrador para esta compañía. Edítalo o emítelo antes de crear otro.']);
            }

            $factura = DB::transaction(function () use ($companiaId, $data, $usuario, $lineasCalc, $subtotal, $descuento, $itbms, $total, $numeracion, $numeroManual, $vencimiento) {
                $factura = VentaFactura::create([
                    'compania_id'       => $companiaId,
                    'cliente_id'        => $data['cliente_id'],
                    'numero'            => 'BORRADOR',
                    'fecha'             => $data['fecha'],
                    'fecha_vencimiento' => $vencimiento,
                    'subtotal'          => $subtotal,
                    'descuento'         => $descuento,
                    'itbms'             => $itbms,
                    'total'             => $total,
                    'saldo'             => $total,
                    'estado'            => VentaFactura::ESTADO_BORRADOR,
                    'notas'             => $data['notas'] ?? null,
                    'extra'             => $numeracion === 'manual' ? ['numero_manual' => $numeroManual] : [],
                    'created_by'        => $usuario->email,
                ]);
                foreach ($lineasCalc as $linea) {
                    VentaFacturaDetalle::create([
                        'factura_id'        => $factura->id,
                        'linea'             => $linea['linea'],
                        'item_id'           => $linea['item_id'] ?? null,
                        'descripcion'       => $linea['descripcion'],
                        'cantidad'          => $linea['cantidad'],
                        'precio_unitario'   => $linea['precio_unitario'],
                        'descuento'         => $linea['descuento'] ?? 0,
                        'impuesto_id'       => $linea['impuesto_id'],
                        'impuesto_monto'    => $linea['impuesto_monto'],
                        'total_linea'       => $linea['total_linea'],
                        'cuenta_ingreso_id' => $linea['cuenta_ingreso_id'] ?? null,
                        'created_by'        => $usuario->email,
                    ]);
                }
                return $factura;
            });

            return redirect()->route('admin.ventas.facturas.show', $factura)
                ->with('status', 'Borrador guardado. Puedes emitirlo cuando esté listo.');
        }

        $cuentaCxcId    = CuentaDefault::idPara($companiaId, 'CXC');
        $cuentaItbmsId  = CuentaDefault::idPara($companiaId, 'ITBMS_POR_PAGAR');
        $cuentaVentasId = CuentaDefault::idPara($companiaId, 'VENTAS');

        if (! $cuentaCxcId) {
            return back()->withInput()->withErrors(['cliente_id' => 'La compañía no tiene configurada la cuenta default CXC.']);
        }

        $invVentas = app(InventarioVentas::class);
        $almacenId = $invVentas->almacenPorDefecto($companiaId);

        $factura = DB::transaction(function () use ($companiaId, $data, $usuario, $lineasCalc, $subtotal, $descuento, $itbms, $total, $cuentaCxcId, $cuentaItbmsId, $cuentaVentasId, $numeracion, $numeroManual, $invVentas, $almacenId, $vencimiento) {
            $numero = $numeracion === 'manual' ? $numeroManual : VentaFactura::siguienteNumero($companiaId);

            $factura = VentaFactura::create([
                'compania_id'       => $companiaId,
                'cliente_id'        => $data['cliente_id'],
                'numero'            => $numero,
                'fecha'             => $data['fecha'],
                'fecha_vencimiento' => $vencimiento,
                'subtotal'          => $subtotal,
                'descuento'         => $descuento,
                'itbms'             => $itbms,
                'total'             => $total,
                'saldo'             => $total,
                'estado'            => VentaFactura::ESTADO_EMITIDA,
                'notas'             => $data['notas'] ?? null,
                'created_by'        => $usuario->email,
            ]);

            foreach ($lineasCalc as $linea) {
                VentaFacturaDetalle::create([
                    'factura_id'        => $factura->id,
                    'linea'             => $linea['linea'],
                    'item_id'           => $linea['item_id'] ?? null,
                    'descripcion'       => $linea['descripcion'],
                    'cantidad'          => $linea['cantidad'],
                    'precio_unitario'   => $linea['precio_unitario'],
                    'descuento'         => $linea['descuento'] ?? 0,
                    'impuesto_id'       => $linea['impuesto_id'],
                    'impuesto_monto'    => $linea['impuesto_monto'],
                    'total_linea'       => $linea['total_linea'],
                    'cuenta_ingreso_id' => $linea['cuenta_ingreso_id'] ?? $cuentaVentasId,
                    'created_by'        => $usuario->email,
                ]);
            }

            $cxc = CxcDocumento::create([
                'compania_id'       => $companiaId,
                'cliente_id'        => $data['cliente_id'],
                'tipo_documento'    => CxcDocumento::TIPO_FACTURA,
                'numero'            => $numero,
                'fecha'             => $data['fecha'],
                'fecha_vencimiento' => $vencimiento,
                'subtotal'          => $subtotal,
                'descuento'         => $descuento,
                'impuesto'          => $itbms,
                'total'             => $total,
                'saldo'             => $total,
                'estado'            => CxcDocumento::ESTADO_PENDIENTE,
                'created_by'        => $usuario->email,
            ]);

            foreach ($lineasCalc as $linea) {
                CxcDocumentoDetalle::create([
                    'documento_id'    => $cxc->id,
                    'linea'           => $linea['linea'],
                    'descripcion'     => $linea['descripcion'],
                    'cantidad'        => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'descuento'       => $linea['descuento'] ?? 0,
                    'impuesto_monto'  => $linea['impuesto_monto'],
                    'total_linea'     => $linea['total_linea'],
                    'cuenta_id'       => $linea['cuenta_ingreso_id'] ?? $cuentaVentasId,
                    'created_by'      => $usuario->email,
                ]);
            }

            $lineasAsiento = [[
                'cuenta_id'   => $cuentaCxcId,
                'contacto_id' => $data['cliente_id'],
                'descripcion' => "Factura {$numero}",
                'debito'      => $total,
                'credito'     => 0,
            ]];

            foreach ($lineasCalc as $linea) {
                $lineasAsiento[] = [
                    'cuenta_id'   => $linea['cuenta_ingreso_id'] ?? $cuentaVentasId,
                    'descripcion' => $linea['descripcion'],
                    'debito'      => 0,
                    'credito'     => $linea['base'],
                ];
            }

            if ($itbms > 0 && $cuentaItbmsId) {
                $lineasAsiento[] = [
                    'cuenta_id'   => $cuentaItbmsId,
                    'descripcion' => "ITBMS factura {$numero}",
                    'debito'      => 0,
                    'credito'     => $itbms,
                ];
            }

            // Costo de ventas + salida de inventario para líneas de productos
            // inventariables (Dr Costo / Cr Inventario en el MISMO asiento).
            $cogs = $invVentas->calcular($companiaId, $almacenId, $lineasCalc);
            $lineasAsiento = array_merge($lineasAsiento, $cogs['lineasAsiento']);

            $cliente = Contacto::find($data['cliente_id']);
            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId, $data['fecha'],
                "Factura de venta {$numero} — ".($cliente->nombre ?? ''),
                $numero, $lineasAsiento, 'CXC', 'ventas_facturas', $factura->id, $usuario,
            );

            $factura->update(['cxc_documento_id' => $cxc->id, 'asiento_id' => $asiento->id]);
            $cxc->update(['asiento_id' => $asiento->id]);

            if ($almacenId && ! empty($cogs['detalle'])) {
                $invVentas->registrar($companiaId, $almacenId, $data['fecha'], $cogs['detalle'], $asiento->id, InventarioVentas::ORIGEN_VENTAS, $factura->id, $usuario);
            }

            return $factura;
        });

        return redirect()->route('admin.ventas.facturas.show', $factura)
            ->with('status', "Factura {$factura->numero} creada.");
    }

    public function index(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'tipo'       => ['nullable', Rule::in(['FACTURA', 'NOTA_CREDITO', 'NOTA_DEBITO'])],
            'estado'     => ['nullable', Rule::in(['BORRADOR', 'EMITIDA', 'PARCIAL', 'PAGADA', 'APLICADA', 'ANULADA'])],
            'cliente_id' => ['nullable', 'integer'],
            'desde'      => ['nullable', 'date'],
            'hasta'      => ['nullable', 'date'],
            'q'          => ['nullable', 'string', 'max:100'],
            'sort'       => ['nullable', Rule::in(['numero', 'fecha', 'fecha_vencimiento', 'total', 'saldo', 'estado'])],
            'dir'        => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $filtros['sort'] ?? 'fecha';
        $dir  = $filtros['dir']  ?? 'desc';

        // Listado unificado: facturas y notas de crédito viven en ventas_facturas,
        // distinguidas por tipo_documento. Se quita el global scope del modelo
        // VentaFactura para incluir ambos tipos.
        $consulta = VentaFactura::withoutGlobalScope('tipoFactura')
            ->with('cliente')
            ->where('compania_id', $companiaId)
            ->when($filtros['tipo'] ?? null, fn ($q, $v) => $q->where('tipo_documento', $v))
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($filtros['cliente_id'] ?? null, fn ($q, $v) => $q->where('cliente_id', $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->when($filtros['q'] ?? null, function ($q, $texto) {
                $b = '%'.mb_strtolower($texto).'%';
                $q->where(fn ($q) => $q
                    ->whereRaw('LOWER(numero) LIKE ?', [$b])
                    ->orWhereHas('cliente', fn ($c) => $c->whereRaw('LOWER(nombre) LIKE ?', [$b]))
                );
            })
            ->orderBy($sort, $dir)
            ->when($sort !== 'numero', fn ($q) => $q->orderBy('numero', $dir));

        if ($request->query('export')) {
            $todas = (clone $consulta)->get();
            if ($export = $this->exportarReporte($request, 'admin.exports.listado', [
                'titulo' => 'Facturas y notas de crédito de venta',
                'compania' => Compania::find($companiaId)?->nombre ?? '',
                'subtitulo' => 'Listado al '.now()->format('d/m/Y').' — '.$todas->count().' documentos',
                'encabezados' => [
                    ['titulo' => 'Número'], ['titulo' => 'Tipo'], ['titulo' => 'Fecha'], ['titulo' => 'Vence'],
                    ['titulo' => 'Cliente'], ['titulo' => 'Total', 'num' => true],
                    ['titulo' => 'Saldo', 'num' => true], ['titulo' => 'Estado'],
                ],
                'filas' => $todas->map(function ($f) {
                    $esNc = $f->tipo_documento === 'NOTA_CREDITO';
                    $etiqueta = match ($f->tipo_documento) {
                        'NOTA_CREDITO' => 'Nota crédito',
                        'NOTA_DEBITO'  => 'Nota débito',
                        'REEMBOLSO'    => 'Reembolso',
                        default        => 'Factura',
                    };

                    return [
                        $f->numero,
                        $etiqueta,
                        $f->fecha->format('d/m/Y'),
                        $f->fecha_vencimiento?->format('d/m/Y') ?? '',
                        $f->cliente->nombre ?? '',
                        number_format(($esNc ? -1 : 1) * (float) $f->total, 2),
                        $esNc ? '' : number_format((float) $f->saldo, 2),
                        ucfirst(strtolower($f->estado)),
                    ];
                })->all(),
                'totales' => ['TOTAL', '', '', '', '',
                    number_format($todas->sum(fn ($f) => ($f->tipo_documento === 'NOTA_CREDITO' ? -1 : 1) * (float) $f->total), 2),
                    number_format($todas->whereIn('tipo_documento', [VentaFactura::TIPO_DOCUMENTO, 'NOTA_DEBITO', 'REEMBOLSO'])->sum(fn ($f) => (float) $f->saldo), 2), ''],
            ], 'documentos_venta_'.now()->format('Y-m-d'))) {
                return $export;
            }
        }

        // Saldo por cobrar: facturas + notas de débito (cargos). Las NC ya
        // reducen el saldo de la factura a la que se aplican.
        $saldoTotal = VentaFactura::withoutGlobalScope('tipoFactura')
            ->where('compania_id', $companiaId)
            ->whereIn('tipo_documento', [VentaFactura::TIPO_DOCUMENTO, 'NOTA_DEBITO', 'REEMBOLSO'])
            ->whereNotIn('estado', [VentaFactura::ESTADO_ANULADA, VentaFactura::ESTADO_PAGADA])
            ->sum('saldo');

        return view('admin.ventas.facturas.index', [
            'facturas'   => $consulta->paginate(25)->withQueryString(),
            'filtros'    => $filtros,
            'clientes'   => $this->clientes($companiaId),
            'saldoTotal' => (float) $saldoTotal,
            'sort'       => $sort,
            'dir'        => $dir,
        ]);
    }

    public function show(Request $request, VentaFactura $factura): View
    {
        abort_unless($factura->compania_id === $this->companiaActivaId($request), 404);

        $factura->load(['cliente', 'detalle.impuesto', 'detalle.cuentaIngreso', 'asiento.detalle.cuenta', 'cotizacion', 'cxcDocumento']);

        return view('admin.ventas.facturas.show', ['factura' => $factura]);
    }

    public function imprimir(Request $request, VentaFactura $factura): View
    {
        abort_unless($factura->compania_id === $this->companiaActivaId($request), 404);

        $factura->load(['cliente', 'detalle.impuesto']);
        $compania = Compania::find($factura->compania_id);

        return view('admin.ventas.facturas.print', compact('factura', 'compania'));
    }

    public function actualizarNotas(Request $request, VentaFactura $factura): RedirectResponse
    {
        abort_unless($factura->compania_id === $this->companiaActivaId($request), 404);

        if ($factura->esAnulada()) {
            return back()->withErrors(['notas' => 'No se pueden editar las notas de una factura anulada.']);
        }

        $data = $request->validate([
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $factura->update([
            'notas'      => $data['notas'] ?? null,
            'updated_by' => $request->user()->email,
        ]);

        return back()->with('status', 'Notas actualizadas.');
    }

    /**
     * Emite una factura de venta YA existente como Factura Electrónica (FEL)
     * ante el PAC (The Factory HKA) / DGI, reutilizando sus líneas e impuestos.
     * Al autorizar, vincula de regreso el documento FEL y el CUFE a la factura.
     *
     * Es el puente cotización → factura → factura electrónica: no se re-teclea
     * nada y la factura legal y el CAFE quedan trazados (fel_documento_id, cufe).
     */
    public function emitirFel(Request $request, VentaFactura $factura): RedirectResponse
    {
        abort_unless($factura->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($request->user()->can('fel.gestionar'), 403);

        if ($factura->fel_documento_id) {
            return back()->withErrors(['fel' => 'Esta factura ya tiene un documento electrónico emitido.']);
        }

        if (! in_array($factura->estado, [VentaFactura::ESTADO_EMITIDA, VentaFactura::ESTADO_PARCIAL, VentaFactura::ESTADO_PAGADA], true)) {
            return back()->withErrors(['fel' => 'Solo se pueden enviar a la DGI facturas emitidas (no borradores ni anuladas).']);
        }

        $config = FelConfiguracion::firstWhere('compania_id', $factura->compania_id);
        if (! $config || ! $config->token_empresa) {
            return redirect()->route('admin.fel.configuracion')
                ->withErrors(['fel' => 'Configura primero los tokens de The Factory HKA.']);
        }

        $factura->load(['detalle.impuesto', 'cliente']);

        if ($factura->detalle->isEmpty()) {
            return back()->withErrors(['fel' => 'La factura no tiene líneas de detalle para facturar.']);
        }

        $compania = Compania::findOrFail($factura->compania_id);
        $cliente  = $factura->cliente;
        $usuario  = $request->user()->email;

        // ── Mapear líneas de la factura a ítems FEL (tasa contable → código DGI) ──
        $items = [];
        foreach ($factura->detalle as $linea) {
            $porcentaje = $linea->impuesto ? (int) round((float) $linea->impuesto->porcentaje) : 0;
            $tasa = TaxImpuesto::DGI_CODIGO_POR_PORCENTAJE[$porcentaje] ?? '00';
            $items[] = [
                'descripcion' => $linea->descripcion,
                'cantidad'    => (float) $linea->cantidad,
                'precio'      => (float) $linea->precio_unitario,
                'descuento'   => (float) $linea->descuento,
                'tasa'        => $tasa,
            ];
        }

        $data = [
            'tipo_documento'      => '01', // Factura de operación interna
            'forma_pago'          => ($cliente?->forma_pago === 'CREDITO') ? '01' : '02',
            'informacion_interes' => 'Factura '.$factura->numero,
            'items'               => $items,
        ];

        $builder = new FelDocumentoBuilder();

        // Salvaguarda contable: el total electrónico debe coincidir con la
        // factura legal. Si hay descuentos por línea/cabecera o redondeos que el
        // builder aún no replica, NO se emite un CAFE descuadrado: se detiene.
        $preview  = $builder->facturaInterna($compania, $config, $cliente, $data, 0);
        $totalFel = (float) $preview['totalesSubTotales']['totalFactura'];
        if (abs($totalFel - (float) $factura->total) > 0.02) {
            return back()->withErrors(['fel' => sprintf(
                'El total electrónico (B/. %s) no coincide con el de la factura (B/. %s). '
                .'Suele deberse a descuentos por línea o de cabecera que el documento electrónico aún no replica. '
                .'Emítela manualmente en el módulo FEL mientras se cubre ese caso.',
                number_format($totalFel, 2), number_format((float) $factura->total, 2)
            )]);
        }

        // Número fiscal con bloqueo (consecutivo único del PAC).
        $numeroFiscal = DB::transaction(fn () => $config->siguienteNumeroFiscal());
        $documento    = $builder->facturaInterna($compania, $config, $cliente, $data, $numeroFiscal);
        $totales      = $documento['totalesSubTotales'];

        $fel = DB::transaction(function () use ($factura, $numeroFiscal, $cliente, $totales, $usuario) {
            $fel = FelDocumento::create([
                'compania_id'      => $factura->compania_id,
                'tipo_documento'   => '01',
                'documento_origen' => 'venta_factura',
                'documento_id'     => $factura->id,
                'numero'           => (string) $numeroFiscal,
                'fecha'            => now()->toDateString(),
                'cliente_id'       => $cliente?->id,
                'subtotal'         => $totales['totalPrecioNeto'],
                'itbms'            => $totales['totalITBMS'],
                'total'            => $totales['totalFactura'],
                'estado_fel'       => 'PENDIENTE',
                'created_by'       => $usuario,
            ]);

            foreach ($factura->detalle->values() as $i => $linea) {
                FelDocumentoDetalle::create([
                    'fel_documento_id' => $fel->id,
                    'linea'            => $i + 1,
                    'descripcion'      => $linea->descripcion,
                    'cantidad'         => $linea->cantidad,
                    'precio_unitario'  => $linea->precio_unitario,
                    'impuesto_monto'   => $linea->impuesto_monto,
                    'total_linea'      => $linea->total_linea,
                    'created_by'       => $usuario,
                ]);
            }

            return $fel;
        });

        $resp = (new FelService($config))->enviar($documento);
        $this->registrarEventoFel($fel, 'ENVIO', $resp, $usuario);

        $codigo    = (string) ($resp['codigo'] ?? $resp['EnviarResult']['codigo'] ?? '');
        $resultado = $resp['EnviarResult'] ?? $resp;

        if ($codigo === '200' || ($resultado['resultado'] ?? '') === 'Procesado') {
            $cufe = $resultado['cufe'] ?? null;

            DB::transaction(function () use ($fel, $factura, $resp, $resultado, $cufe, $usuario) {
                $fel->update([
                    'estado_fel'    => 'AUTORIZADO',
                    'cufe'          => $cufe,
                    'qr'            => $resultado['qr'] ?? null,
                    'respuesta_dgi' => $resp,
                    'fecha_envio'   => now(),
                    'updated_by'    => $usuario,
                ]);

                // Vínculo de regreso: la factura legal queda trazada al CAFE.
                $factura->update([
                    'fel_documento_id' => $fel->id,
                    'cufe'             => $cufe,
                    'updated_by'       => $usuario,
                ]);
            });

            return redirect()->route('admin.ventas.facturas.show', $factura)
                ->with('status', "Factura {$factura->numero} autorizada por la DGI (FEL {$numeroFiscal}). CUFE: ".substr((string) $cufe, 0, 40).'…');
        }

        $fel->update([
            'estado_fel'    => 'RECHAZADO',
            'respuesta_dgi' => $resp,
            'fecha_envio'   => now(),
            'updated_by'    => $usuario,
        ]);

        $mensaje = $resultado['mensaje'] ?? $resp['mensaje'] ?? 'Sin detalle';

        return back()->withErrors(['fel' => "La DGI rechazó la factura {$factura->numero} (FEL {$numeroFiscal}): {$mensaje}"]);
    }

    /** Registra un evento de auditoría del documento FEL (espejo de FacturaFelController). */
    private function registrarEventoFel(FelDocumento $fel, string $evento, array $respuesta, string $usuario): void
    {
        DB::table('fel_eventos')->insert([
            'fel_documento_id' => $fel->id,
            'evento'           => $evento,
            'descripcion'      => $respuesta['mensaje'] ?? null,
            'respuesta'        => json_encode($respuesta, JSON_UNESCAPED_UNICODE),
            'created_at'       => now(),
            'updated_at'       => now(),
            'created_by'       => $usuario,
        ]);
    }

    /**
     * Anula el documento electrónico (CAFE) de la factura ante la DGI/PAC. Debe
     * hacerse ANTES de anular la factura legal, para no dejar un CAFE vivo en la
     * DGI sin respaldo contable. Si el FEL no estaba AUTORIZADO (rechazado/
     * pendiente) no hay CAFE válido y solo se marca ANULADO localmente.
     */
    public function anularFel(Request $request, VentaFactura $factura): RedirectResponse
    {
        abort_unless($factura->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($request->user()->can('fel.gestionar'), 403);

        $fel = $factura->fel_documento_id ? FelDocumento::find($factura->fel_documento_id) : null;
        if (! $fel) {
            return back()->withErrors(['fel' => 'La factura no tiene un documento electrónico.']);
        }
        if ($fel->estado_fel === 'ANULADO') {
            return back()->withErrors(['fel' => 'El documento electrónico ya está anulado.']);
        }

        $usuario = $request->user()->email;

        // Sin CAFE autorizado no hay nada que anular en la DGI: se marca local.
        if ($fel->estado_fel !== 'AUTORIZADO') {
            $fel->update(['estado_fel' => 'ANULADO', 'updated_by' => $usuario]);

            return back()->with('status', 'Documento electrónico marcado como anulado (no estaba autorizado en la DGI).');
        }

        $config = FelConfiguracion::firstWhere('compania_id', $factura->compania_id);
        if (! $config || ! $config->token_empresa) {
            return back()->withErrors(['fel' => 'No hay configuración FEL (tokens) para anular ante la DGI.']);
        }

        $motivo = trim((string) $request->input('motivo_anulacion', 'Anulación de la factura '.$factura->numero));

        $datos = ['datosDocumento' => [
            'codigoSucursalEmisor'   => $config->codigo_sucursal ?: '0000',
            'numeroDocumentoFiscal'  => $fel->numero,
            'puntoFacturacionFiscal' => $config->punto_facturacion ?: '001',
            'tipoDocumento'          => $fel->tipo_documento ?: '01',
            'tipoEmision'            => '01',
        ]];

        $resp = (new FelService($config))->anulacionDocumento($datos, $motivo);
        $this->registrarEventoFel($fel, 'ANULACION', $resp, $usuario);

        $codigo    = (string) ($resp['codigo'] ?? $resp['AnulacionResult']['codigo'] ?? '');
        $resultado = $resp['AnulacionResult'] ?? $resp;
        $exito = $codigo === '200'
            || in_array(strtolower((string) ($resultado['resultado'] ?? '')), ['procesado', 'anulado'], true);

        if (! $exito) {
            $mensaje = $resultado['mensaje'] ?? $resp['mensaje'] ?? 'Sin detalle';

            return back()->withErrors(['fel' => "La DGI no pudo anular el documento electrónico: {$mensaje}"]);
        }

        $fel->update(['estado_fel' => 'ANULADO', 'respuesta_dgi' => $resp, 'updated_by' => $usuario]);

        return back()->with('status', "Documento electrónico (FEL {$fel->numero}) anulado en la DGI. Para reversar la factura emite una Nota de Crédito.");
    }

    public function edit(Request $request, VentaFactura $factura): View
    {
        abort_unless($factura->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($factura->estado === VentaFactura::ESTADO_BORRADOR, 403);

        $companiaId = $factura->compania_id;
        $factura->load('detalle');

        return view('admin.ventas.facturas.create', [
            'clientes'       => $this->clientes($companiaId),
            'impuestos'      => TaxImpuesto::itbmsGlobales(),
            'factura'        => $factura,
            'numeroPreview'  => VentaFactura::siguienteNumero($companiaId),
            'cuentasIngreso' => $this->cuentasIngreso($companiaId),
            'cuentaVentasId' => CuentaDefault::idPara($companiaId, 'VENTAS'),
            'items'          => $this->itemsVenta($companiaId),
        ]);
    }

    public function update(Request $request, VentaFactura $factura): RedirectResponse
    {
        abort_unless($factura->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($factura->estado === VentaFactura::ESTADO_BORRADOR, 403);

        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'cliente_id'                 => ['required', 'integer', 'exists:contact_contactos,id'],
            'fecha'                      => ['required', 'date'],
            'fecha_vencimiento'          => ['nullable', 'date', 'after_or_equal:fecha'],
            'notas'                      => ['nullable', 'string', 'max:1000'],
            'descuento_general'          => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lineas'                     => ['required', 'array', 'min:1'],
            'lineas.*.item_id'           => ['nullable', 'integer', 'exists:item_productos_servicios,id'],
            'lineas.*.descripcion'       => ['required', 'string', 'max:500'],
            'lineas.*.cantidad'          => ['required', 'numeric', 'min:0.0001'],
            'lineas.*.precio_unitario'   => ['required', 'numeric', 'min:0'],
            'lineas.*.descuento'         => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lineas.*.impuesto_id'       => ['required', 'integer', Rule::in(TaxImpuesto::itbmsGlobales()->pluck('id')->all())],
            'lineas.*.cuenta_ingreso_id' => ['nullable', 'integer', 'exists:cgl_cuentas,id'],
        ]);

        $usuario   = $request->user();
        $accion    = $request->input('accion', 'borrador');

        $numeracion   = $request->input('numeracion') === 'manual' ? 'manual' : 'auto';
        $numeroManual = trim((string) $request->input('numero_manual', ''));
        if ($numeracion === 'manual') {
            $request->validate(['numero_manual' => ['required', 'string', 'max:50']]);
            if ($this->numeroExiste($companiaId, $numeroManual)) {
                return back()->withInput()->withErrors(['numero_manual' => 'Ya existe un documento con el número '.$numeroManual.'.']);
            }
        }

        $impuestos = TaxImpuesto::whereIn('id', collect($data['lineas'])->pluck('impuesto_id')->unique())->get()->keyBy('id');

        $entradas = [];
        foreach ($data['lineas'] as $linea) {
            $entradas[] = array_merge($linea, [
                'tasa' => (float) ($impuestos[$linea['impuesto_id']]->porcentaje ?? 0),
            ]);
        }
        $calc       = CalculoDocumento::calcular($entradas, (float) ($data['descuento_general'] ?? 0));
        $lineasCalc = $calc['lineas'];
        $subtotal   = $calc['subtotal'];
        $descuento  = $calc['descuento'];
        $itbms      = $calc['itbms'];
        $total      = $calc['total'];

        $cliente     = Contacto::where('compania_id', $companiaId)->findOrFail($data['cliente_id']);
        $vencimiento = $data['fecha_vencimiento']
            ?? ($cliente->esCredito() ? $cliente->calcularVencimiento($data['fecha']) : null);

        DB::transaction(function () use ($factura, $data, $usuario, $lineasCalc, $subtotal, $descuento, $itbms, $total, $numeracion, $numeroManual, $vencimiento) {
            $factura->update([
                'cliente_id'        => $data['cliente_id'],
                'fecha'             => $data['fecha'],
                'fecha_vencimiento' => $vencimiento,
                'subtotal'          => $subtotal,
                'descuento'         => $descuento,
                'itbms'             => $itbms,
                'total'             => $total,
                'saldo'             => $total,
                'notas'             => $data['notas'] ?? null,
                'extra'             => $numeracion === 'manual' ? ['numero_manual' => $numeroManual] : [],
                'updated_by'        => $usuario->email,
            ]);

            $factura->detalle()->delete();

            foreach ($lineasCalc as $linea) {
                VentaFacturaDetalle::create([
                    'factura_id'        => $factura->id,
                    'linea'             => $linea['linea'],
                    'item_id'           => $linea['item_id'] ?? null,
                    'descripcion'       => $linea['descripcion'],
                    'cantidad'          => $linea['cantidad'],
                    'precio_unitario'   => $linea['precio_unitario'],
                    'descuento'         => $linea['descuento'] ?? 0,
                    'impuesto_id'       => $linea['impuesto_id'],
                    'impuesto_monto'    => $linea['impuesto_monto'],
                    'total_linea'       => $linea['total_linea'],
                    'cuenta_ingreso_id' => $linea['cuenta_ingreso_id'] ?? null,
                    'created_by'        => $usuario->email,
                ]);
            }
        });

        if ($accion === 'emitir') {
            return $this->emitir($request, $factura->fresh());
        }

        return redirect()->route('admin.ventas.facturas.show', $factura)
            ->with('status', 'Borrador actualizado.');
    }

    public function emitir(Request $request, VentaFactura $factura): RedirectResponse
    {
        abort_unless($factura->compania_id === $this->companiaActivaId($request), 404);

        if ($factura->estado !== VentaFactura::ESTADO_BORRADOR) {
            return back()->withErrors(['factura' => 'La factura ya fue emitida.']);
        }

        $companiaId     = $factura->compania_id;
        $cuentaCxcId    = CuentaDefault::idPara($companiaId, 'CXC');
        $cuentaItbmsId  = CuentaDefault::idPara($companiaId, 'ITBMS_POR_PAGAR');
        $cuentaVentasId = CuentaDefault::idPara($companiaId, 'VENTAS');

        if (! $cuentaCxcId) {
            return back()->withErrors(['factura' => 'La compañía no tiene configurada la cuenta default CXC.']);
        }

        // Numeración manual opcional al emitir un borrador. Si la petición trae
        // los campos del formulario (emisión desde editar) se usan esos; si no
        // (botón "Emitir" del detalle) se respeta el número reservado en el borrador.
        if ($request->has('numeracion')) {
            $numeracion   = $request->input('numeracion') === 'manual' ? 'manual' : 'auto';
            $numeroManual = trim((string) $request->input('numero_manual', ''));
        } else {
            $guardado     = data_get($factura->extra, 'numero_manual');
            $numeracion   = $guardado ? 'manual' : 'auto';
            $numeroManual = (string) ($guardado ?? '');
        }
        if ($numeracion === 'manual') {
            if ($numeroManual === '') {
                return back()->withErrors(['numero_manual' => 'El número manual es obligatorio.']);
            }
            if ($this->numeroExiste($companiaId, $numeroManual)) {
                return back()->withInput()->withErrors(['numero_manual' => 'Ya existe un documento con el número '.$numeroManual.'.']);
            }
        }

        $usuario = $request->user();
        $factura->load(['detalle', 'cliente']);

        $subtotal = (float) $factura->subtotal;
        $itbms    = (float) $factura->itbms;
        $total    = (float) $factura->total;

        $lineasCalc = $factura->detalle->map(fn ($d) => [
            'linea'             => $d->linea,
            'item_id'           => $d->item_id,
            'descripcion'       => $d->descripcion,
            'cantidad'          => $d->cantidad,
            'precio_unitario'   => $d->precio_unitario,
            'descuento'         => (float) $d->descuento,
            'base'              => round((float) $d->total_linea - (float) $d->impuesto_monto, 2),
            'impuesto_id'       => $d->impuesto_id,
            'impuesto_monto'    => (float) $d->impuesto_monto,
            'total_linea'       => (float) $d->total_linea,
            'cuenta_ingreso_id' => $d->cuenta_ingreso_id,
        ])->all();

        $invVentas = app(InventarioVentas::class);
        $almacenId = $invVentas->almacenPorDefecto($companiaId);

        DB::transaction(function () use ($factura, $companiaId, $usuario, $lineasCalc, $subtotal, $itbms, $total, $cuentaCxcId, $cuentaItbmsId, $cuentaVentasId, $numeracion, $numeroManual, $invVentas, $almacenId) {
            $numero = $numeracion === 'manual' ? $numeroManual : VentaFactura::siguienteNumero($companiaId);
            $factura->update(['numero' => $numero, 'estado' => VentaFactura::ESTADO_EMITIDA, 'extra' => [], 'updated_by' => $usuario->email]);

            foreach ($factura->detalle as $d) {
                if (! $d->cuenta_ingreso_id) {
                    $d->update(['cuenta_ingreso_id' => $cuentaVentasId]);
                }
            }

            $cxc = CxcDocumento::create([
                'compania_id'       => $companiaId,
                'cliente_id'        => $factura->cliente_id,
                'tipo_documento'    => CxcDocumento::TIPO_FACTURA,
                'numero'            => $numero,
                'fecha'             => $factura->fecha,
                'fecha_vencimiento' => $factura->fecha_vencimiento,
                'subtotal'          => $subtotal,
                'descuento'         => (float) $factura->descuento,
                'impuesto'          => $itbms,
                'total'             => $total,
                'saldo'             => $total,
                'estado'            => CxcDocumento::ESTADO_PENDIENTE,
                'created_by'        => $usuario->email,
            ]);

            foreach ($lineasCalc as $linea) {
                CxcDocumentoDetalle::create([
                    'documento_id'    => $cxc->id,
                    'linea'           => $linea['linea'],
                    'descripcion'     => $linea['descripcion'],
                    'cantidad'        => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'descuento'       => $linea['descuento'] ?? 0,
                    'impuesto_monto'  => $linea['impuesto_monto'],
                    'total_linea'     => $linea['total_linea'],
                    'cuenta_id'       => $linea['cuenta_ingreso_id'] ?? $cuentaVentasId,
                    'created_by'      => $usuario->email,
                ]);
            }

            $lineasAsiento = [[
                'cuenta_id'   => $cuentaCxcId,
                'contacto_id' => $factura->cliente_id,
                'descripcion' => "Factura {$numero}",
                'debito'      => $total,
                'credito'     => 0,
            ]];

            foreach ($lineasCalc as $linea) {
                $lineasAsiento[] = ['cuenta_id' => $linea['cuenta_ingreso_id'] ?? $cuentaVentasId, 'descripcion' => $linea['descripcion'], 'debito' => 0, 'credito' => $linea['base']];
            }

            if ($itbms > 0 && $cuentaItbmsId) {
                $lineasAsiento[] = ['cuenta_id' => $cuentaItbmsId, 'descripcion' => "ITBMS factura {$numero}", 'debito' => 0, 'credito' => $itbms];
            }

            // Costo de ventas + salida de inventario para productos inventariables.
            $cogs = $invVentas->calcular($companiaId, $almacenId, $lineasCalc);
            $lineasAsiento = array_merge($lineasAsiento, $cogs['lineasAsiento']);

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId, $factura->fecha,
                "Factura de venta {$numero} — ".($factura->cliente->nombre ?? ''),
                $numero, $lineasAsiento, 'CXC', 'ventas_facturas', $factura->id, $usuario,
            );

            $factura->update(['cxc_documento_id' => $cxc->id, 'asiento_id' => $asiento->id]);
            $cxc->update(['asiento_id' => $asiento->id]);

            if ($almacenId && ! empty($cogs['detalle'])) {
                $invVentas->registrar($companiaId, $almacenId, $factura->fecha, $cogs['detalle'], $asiento->id, InventarioVentas::ORIGEN_VENTAS, $factura->id, $usuario);
            }
        });

        return redirect()->route('admin.ventas.facturas.show', $factura)
            ->with('status', "Factura {$factura->numero} emitida.");
    }

    /** Convierte una celda de monto (número o texto con separador de miles) a float. */
    private function montoImport(mixed $valor): float
    {
        if (is_numeric($valor)) {
            return round((float) $valor, 2);
        }

        $limpio = str_replace([',', ' ', 'B/.', 'B/'], '', trim((string) $valor));

        return round((float) $limpio, 2);
    }

    public function importar(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario    = $request->user();

        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']]);

        if (! CuentaDefault::idPara($companiaId, 'CXC')) {
            return back()->withErrors(['archivo' => 'La compañía no tiene configurada la cuenta default CXC.']);
        }

        // Validación rápida del formato antes de encolar
        $import = new VentasFacturasImport;
        Excel::import($import, $request->file('archivo'));

        if (empty($import->filas)) {
            return back()->withErrors(['archivo' => 'El archivo no contiene filas válidas o no tiene el formato esperado.']);
        }

        $archivo = $request->file('archivo');
        $ext     = strtolower($archivo->getClientOriginalExtension() ?: 'xlsx');
        $ruta    = $archivo->storeAs('imports/ventas', Str::uuid().'.'.$ext);

        $importacion = VentasImportacion::create([
            'compania_id' => $companiaId,
            'usuario'     => $usuario->email,
            'archivo'     => $archivo->getClientOriginalName(),
            'ruta'        => $ruta,
            'estado'      => VentasImportacion::ESTADO_PENDIENTE,
            'total'       => count($import->filas),
        ]);

        ProcesarImportacionVentasFel::dispatch($importacion->id);

        return redirect()->route('admin.ventas.facturas.importar.progreso', $importacion);
    }

    public function importarProgreso(Request $request, VentasImportacion $importacion): View
    {
        abort_unless($importacion->compania_id === $this->companiaActivaId($request), 404);

        return view('admin.ventas.facturas.importar-progreso', compact('importacion'));
    }

    public function importarEstado(Request $request, VentasImportacion $importacion): JsonResponse
    {
        abort_unless($importacion->compania_id === $this->companiaActivaId($request), 404);

        return response()->json([
            'estado'        => $importacion->estado,
            'total'         => $importacion->total,
            'procesadas'    => $importacion->procesadas,
            'creadas'       => $importacion->creadas,
            'con_detalle'   => $importacion->con_detalle,
            'omitidas'      => $importacion->omitidas,
            'errores'       => $importacion->errores ?? [],
            'mensaje_error' => $importacion->mensaje_error,
            'porcentaje'    => $importacion->porcentaje(),
            'terminada'     => $importacion->terminada(),
        ]);
    }

    /**
     * Descarga la plantilla .xlsx para importar ventas propias (no DGI), con un
     * par de cuentas de ingreso reales de la compañía como ejemplo.
     */
    public function importarGenericoPlantilla(Request $request): Response
    {
        abort_unless($request->user()->can('ventas.gestionar'), 403);

        $companiaId = $this->companiaActivaId($request);

        $cuentas = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->where('activa', true)
            ->where('naturaleza', 'CREDITO')
            ->where('codigo', 'like', '4%') // cuentas de ingreso como muestra
            ->orderBy('codigo')
            ->limit(2)
            ->get(['codigo', 'nombre'])
            ->map(fn ($c) => [$c->codigo, $c->nombre])
            ->all();

        return Excel::download(new VentasPlantillaExport($cuentas), 'plantilla_ventas.xlsx');
    }

    /**
     * Importa ventas propias (no DGI) desde un Excel/CSV. A diferencia de Compras
     * —que puede dejar borradores— Ventas solo admite un borrador por compañía,
     * así que cada factura se crea EMITIDA con su número del Excel y se contabiliza
     * (Dr CXC / Cr Ventas / Cr ITBMS) vía AsientoAutomatico. Idempotente por número.
     * Síncrono y tolerante: un documento que falle (período cerrado, etc.) se reporta
     * y NO aborta el resto del lote.
     */
    public function importarGenerico(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('ventas.gestionar'), 403);

        $companiaId = $this->companiaActivaId($request);
        $usuario = $request->user();

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $import = new VentasGenericoImport;
        Excel::import($import, $request->file('archivo'));

        if ($import->filas === []) {
            return back()->withErrors(['archivo_generico' => 'El archivo no tiene filas con datos. La primera fila deben ser los encabezados (cliente, numero, fecha, subtotal…).']);
        }

        $cuentaCxcId    = CuentaDefault::idPara($companiaId, 'CXC');
        $cuentaItbmsId  = CuentaDefault::idPara($companiaId, 'ITBMS_POR_PAGAR');
        $cuentaVentasId = CuentaDefault::idPara($companiaId, 'VENTAS');

        if (! $cuentaCxcId || ! $cuentaVentasId) {
            return back()->withErrors(['archivo_generico' => 'La compañía no tiene configuradas las cuentas default CXC y/o VENTAS. Configúralas antes de importar.']);
        }

        $catalogo = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->get(['id', 'codigo'])
            ->keyBy(fn ($c) => trim((string) $c->codigo));

        $impuestosGlobales = TaxImpuesto::itbmsGlobales();

        // Índice de contactos para emparejar clientes con tolerancia (sin duplicar):
        // por RUC exacto, por código exacto y por nombre NORMALIZADO (sin tildes,
        // mayúsculas, puntuación ni espacios extra). Se construye una sola vez y los
        // clientes nuevos se agregan dentro del bucle para que las filas siguientes
        // los reconozcan.
        $indiceClientes = ['ruc' => [], 'codigo' => [], 'nombre' => []];
        foreach (Contacto::where('compania_id', $companiaId)->get(['id', 'codigo', 'nombre', 'identificacion']) as $c) {
            $this->indexarContacto($indiceClientes, $c);
        }

        $errores = [];
        $documentos = []; // clave cliente|numero => cabecera + líneas

        foreach ($import->filas as $f) {
            $fila = $f['fila'];

            if ($f['cliente'] === '' && $f['ruc'] === '') {
                $errores[] = "Fila {$fila}: falta el cliente (nombre o RUC).";

                continue;
            }
            if ($f['numero'] === '') {
                $errores[] = "Fila {$fila}: falta el número de documento.";

                continue;
            }
            if (! $f['fecha']) {
                $errores[] = "Fila {$fila}: falta la fecha o tiene un formato no reconocido (usa dd/mm/aaaa).";

                continue;
            }
            if ($f['subtotal'] <= 0) {
                $errores[] = "Fila {$fila}: el subtotal debe ser mayor que cero.";

                continue;
            }

            $cliente = $this->resolverOCrearClienteGenerico($f, $companiaId, $usuario, $indiceClientes);

            // Cuenta de ingreso: por código del Excel, o la default VENTAS.
            $cuentaId = null;
            if ($f['cuenta'] !== '') {
                $cuentaId = $catalogo[$f['cuenta']]->id ?? null;
                if (! $cuentaId) {
                    $errores[] = "Fila {$fila}: la cuenta '{$f['cuenta']}' no existe o no permite movimiento; se usó la cuenta de ventas por defecto.";
                }
            }
            $cuentaId ??= $cuentaVentasId;

            $base = round($f['subtotal'], 2);
            $itbms = $f['itbms'] > 0
                ? round($f['itbms'], 2)
                : ($f['tasa'] > 0 ? round($base * $f['tasa'] / 100, 2) : 0.0);
            $tasaEfectiva = $f['tasa'] > 0
                ? (int) round($f['tasa'])
                : ($base > 0 && $itbms > 0 ? (int) round($itbms / $base * 100) : 0);
            $impuesto = $this->resolverImpuestoVenta($tasaEfectiva, $impuestosGlobales);

            $clave = $cliente->id.'|'.$f['numero'];
            $documentos[$clave] ??= [
                'cliente'     => $cliente,
                'numero'      => $f['numero'],
                'fecha'       => $f['fecha'],
                'vencimiento' => $f['vencimiento'],
                'lineas'      => [],
            ];
            $documentos[$clave]['lineas'][] = [
                'descripcion'       => $f['concepto'] !== '' ? substr($f['concepto'], 0, 500) : 'Venta '.$f['numero'],
                'cantidad'          => 1,
                'precio'            => $base,
                'itbms'             => $itbms,
                'total'             => round($base + $itbms, 2),
                'cuenta_ingreso_id' => (int) $cuentaId,
                'impuesto_id'       => $impuesto?->id,
            ];
        }

        $creadas = 0;
        $omitidas = 0;

        foreach ($documentos as $doc) {
            if ($this->numeroExiste($companiaId, $doc['numero'])) {
                $omitidas++;
                $errores[] = "Documento {$doc['numero']} de {$doc['cliente']->nombre}: ya existe; se omitió.";

                continue;
            }

            $subtotal = round(array_sum(array_column($doc['lineas'], 'precio')), 2);
            $itbms = round(array_sum(array_column($doc['lineas'], 'itbms')), 2);
            $total = round($subtotal + $itbms, 2);

            try {
                DB::transaction(function () use ($companiaId, $doc, $subtotal, $itbms, $total, $usuario, $cuentaCxcId, $cuentaItbmsId) {
                    $numero = $doc['numero'];

                    $factura = VentaFactura::create([
                        'compania_id'       => $companiaId,
                        'cliente_id'        => $doc['cliente']->id,
                        'numero'            => $numero,
                        'fecha'             => $doc['fecha'],
                        'fecha_vencimiento' => $doc['vencimiento'],
                        'subtotal'          => $subtotal,
                        'descuento'         => 0,
                        'itbms'             => $itbms,
                        'total'             => $total,
                        'saldo'             => $total,
                        'estado'            => VentaFactura::ESTADO_EMITIDA,
                        'created_by'        => $usuario->email,
                    ]);

                    foreach ($doc['lineas'] as $n => $linea) {
                        VentaFacturaDetalle::create([
                            'factura_id'        => $factura->id,
                            'linea'             => $n + 1,
                            'item_id'           => null,
                            'descripcion'       => $linea['descripcion'],
                            'cantidad'          => $linea['cantidad'],
                            'precio_unitario'   => $linea['precio'],
                            'descuento'         => 0,
                            'impuesto_id'       => $linea['impuesto_id'],
                            'impuesto_monto'    => $linea['itbms'],
                            'total_linea'       => $linea['total'],
                            'cuenta_ingreso_id' => $linea['cuenta_ingreso_id'],
                            'created_by'        => $usuario->email,
                        ]);
                    }

                    $cxc = CxcDocumento::create([
                        'compania_id'       => $companiaId,
                        'cliente_id'        => $doc['cliente']->id,
                        'tipo_documento'    => CxcDocumento::TIPO_FACTURA,
                        'numero'            => $numero,
                        'fecha'             => $doc['fecha'],
                        'fecha_vencimiento' => $doc['vencimiento'],
                        'subtotal'          => $subtotal,
                        'descuento'         => 0,
                        'impuesto'          => $itbms,
                        'total'             => $total,
                        'saldo'             => $total,
                        'estado'            => CxcDocumento::ESTADO_PENDIENTE,
                        'created_by'        => $usuario->email,
                    ]);

                    foreach ($doc['lineas'] as $n => $linea) {
                        CxcDocumentoDetalle::create([
                            'documento_id'    => $cxc->id,
                            'linea'           => $n + 1,
                            'descripcion'     => $linea['descripcion'],
                            'cantidad'        => $linea['cantidad'],
                            'precio_unitario' => $linea['precio'],
                            'descuento'       => 0,
                            'impuesto_monto'  => $linea['itbms'],
                            'total_linea'     => $linea['total'],
                            'cuenta_id'       => $linea['cuenta_ingreso_id'],
                            'created_by'      => $usuario->email,
                        ]);
                    }

                    $lineasAsiento = [[
                        'cuenta_id'   => $cuentaCxcId,
                        'contacto_id' => $doc['cliente']->id,
                        'descripcion' => "Factura {$numero}",
                        'debito'      => $total,
                        'credito'     => 0,
                    ]];
                    foreach ($doc['lineas'] as $linea) {
                        $lineasAsiento[] = [
                            'cuenta_id'   => $linea['cuenta_ingreso_id'],
                            'descripcion' => $linea['descripcion'],
                            'debito'      => 0,
                            'credito'     => $linea['precio'],
                        ];
                    }
                    if ($itbms > 0 && $cuentaItbmsId) {
                        $lineasAsiento[] = [
                            'cuenta_id'   => $cuentaItbmsId,
                            'descripcion' => "ITBMS factura {$numero}",
                            'debito'      => 0,
                            'credito'     => $itbms,
                        ];
                    }

                    $asiento = app(AsientoAutomatico::class)->postear(
                        $companiaId, $doc['fecha'],
                        "Factura de venta {$numero} — ".($doc['cliente']->nombre ?? ''),
                        $numero, $lineasAsiento, 'CXC', 'ventas_facturas', $factura->id, $usuario,
                    );

                    $factura->update(['cxc_documento_id' => $cxc->id, 'asiento_id' => $asiento->id]);
                    $cxc->update(['asiento_id' => $asiento->id]);
                });

                $creadas++;
            } catch (\Throwable $e) {
                $errores[] = "Documento {$doc['numero']} de {$doc['cliente']->nombre}: no se pudo registrar ({$e->getMessage()}).";
            }
        }

        $resumen = "Importación de ventas: {$creadas} factura(s) emitida(s) y contabilizada(s)";
        if ($omitidas > 0) {
            $resumen .= ", {$omitidas} omitida(s) por estar ya registradas";
        }
        $resumen .= '.';

        return redirect()->route('admin.ventas.facturas.index')
            ->with('status', $resumen)
            ->with('import_ventas_errores', array_slice($errores, 0, 50));
    }

    /**
     * Resuelve el cliente contra el índice (RUC exacto → código exacto → nombre
     * NORMALIZADO, tolerante a tildes/mayúsculas/puntuación/espacios); si no existe
     * lo crea y lo agrega al índice para las filas siguientes. $indice se pasa por
     * referencia.
     */
    private function resolverOCrearClienteGenerico(array $f, int $companiaId, $usuario, array &$indice): Contacto
    {
        $ruc = $f['ruc'] !== '' ? substr($f['ruc'], 0, 50) : null;
        $nombre = $f['cliente'];

        if ($ruc && isset($indice['ruc'][$ruc])) {
            return $indice['ruc'][$ruc];
        }
        if ($nombre !== '') {
            if (isset($indice['codigo'][$nombre])) {
                return $indice['codigo'][$nombre];
            }
            $norm = $this->normalizarTexto($nombre);
            if ($norm !== '' && isset($indice['nombre'][$norm])) {
                return $indice['nombre'][$norm];
            }
        }

        $codigo = $ruc;
        if ($codigo && isset($indice['codigo'][$codigo])) {
            $codigo = null;
        }

        $cliente = Contacto::create([
            'compania_id'    => $companiaId,
            'codigo'         => $codigo,
            'nombre'         => substr($nombre !== '' ? $nombre : ($ruc ?? 'Cliente'), 0, 200),
            'tipo_persona'   => 'JURIDICA',
            'identificacion' => $ruc,
            'activo'         => true,
            'created_by'     => $usuario->email,
        ]);

        if ($tipoCliente = TipoContacto::where('codigo', 'CLIENTE')->first()) {
            $cliente->tipos()->attach($tipoCliente->id);
        }

        $this->indexarContacto($indice, $cliente);

        return $cliente;
    }

    /** Elige la tasa ITBMS global cuyo porcentaje coincide con $tasa (cae a 0% / primera). */
    private function resolverImpuestoVenta(int $tasa, $impuestosGlobales): ?TaxImpuesto
    {
        return $impuestosGlobales->first(fn ($i) => (int) round($i->porcentaje) === $tasa)
            ?? $impuestosGlobales->first(fn ($i) => (int) round($i->porcentaje) === 0)
            ?? $impuestosGlobales->first();
    }

    /**
     * Las facturas de venta NO se anulan directamente: es política contable/fiscal.
     * Para reversar una factura emitida se emite una Nota de Crédito (devolución/
     * anulación comercial) o, para un cargo adicional, una Nota de Débito. Así el
     * documento original y su número fiscal/CAFE quedan intactos y trazables.
     *
     * El endpoint se conserva pero RECHAZA SIEMPRE (guarda de servidor): la UI ya
     * no ofrece el botón «Anular», y esto blinda contra invocaciones directas o
     * enlaces obsoletos. La corrección "en la fuente" sigue por «Editar» (corregir)
     * para borradores y re-emisión.
     */
    public function anular(Request $request, VentaFactura $factura): RedirectResponse
    {
        abort_unless($factura->compania_id === $this->companiaActivaId($request), 404);

        return back()->withErrors(['factura' => "Las facturas de venta no se anulan. Para reversar la factura {$factura->numero} emite una Nota de Crédito (o una Nota de Débito para un cargo adicional)."]);
    }

    /**
     * "Editar" una factura emitida: por dentro la anula (reversa asiento + CxC +
     * cotización, igual que anular()) y crea un BORRADOR idéntico para que el
     * usuario lo ajuste y vuelva a emitir. El número se reasigna al emitir: a
     * diferencia de CxP, ventas_facturas no tiene índice único parcial, así que
     * no se puede reusar el número de la factura anulada. Nada se borra.
     */
    public function corregir(Request $request, VentaFactura $factura): RedirectResponse
    {
        abort_unless($factura->compania_id === $this->companiaActivaId($request), 404);

        if ($factura->estado === VentaFactura::ESTADO_BORRADOR) {
            return redirect()->route('admin.ventas.facturas.edit', $factura);
        }

        if ($factura->esAnulada()) {
            return back()->withErrors(['factura' => 'La factura ya está anulada.']);
        }

        // Con un CAFE autorizado en la DGI, primero hay que anularlo (FEL) para no
        // dejar un documento electrónico vivo sin respaldo contable.
        if ($factura->fel_documento_id) {
            $felDoc = FelDocumento::find($factura->fel_documento_id);
            if ($felDoc && $felDoc->estado_fel === 'AUTORIZADO') {
                return back()->withErrors(['factura' => 'Esta factura tiene un documento electrónico AUTORIZADO en la DGI. Anula primero el FEL (botón «Anular FEL»).']);
            }
        }

        if ($factura->cxcDocumento && $factura->cxcDocumento->aplicacionesComoDestino()->exists()) {
            return back()->withErrors(['factura' => 'La factura tiene cobros aplicados; anula primero los cobros en CxC.']);
        }

        if (VentaFactura::where('compania_id', $factura->compania_id)->where('estado', VentaFactura::ESTADO_BORRADOR)->exists()) {
            return back()->withErrors(['factura' => 'Ya existe una factura en borrador; emítela o elimínala antes de editar otra.']);
        }

        $usuario = $request->user();
        $factura->load('detalle');

        $borrador = DB::transaction(function () use ($factura, $usuario) {
            // 1) Anular la actual (mismos pasos que anular()).
            if ($factura->asiento) {
                app(AsientoAutomatico::class)->anular($factura->asiento, $usuario);
            }
            app(InventarioVentas::class)->reversarPorDocumento(InventarioVentas::ORIGEN_VENTAS, $factura->id, $usuario);
            if ($factura->cxcDocumento) {
                $factura->cxcDocumento->update(['estado' => 'ANULADO', 'saldo' => 0, 'updated_by' => $usuario->email]);
            }
            $factura->update(['estado' => VentaFactura::ESTADO_ANULADA, 'saldo' => 0, 'updated_by' => $usuario->email]);
            if ($factura->cotizacion_id) {
                $factura->cotizacion->update(['estado' => VentaCotizacion::ESTADO_ACEPTADA, 'updated_by' => $usuario->email]);
            }

            // 2) Clonar como BORRADOR (número placeholder; se asigna al emitir).
            $borrador = $factura->replicate([
                'estado', 'numero', 'cufe', 'cxc_documento_id', 'asiento_id',
                'fel_documento_id', 'cotizacion_id', 'saldo', 'extra', 'created_by', 'updated_by',
            ]);
            $borrador->estado = VentaFactura::ESTADO_BORRADOR;
            // Placeholder único hasta emitir (el índice (compania,numero) es global,
            // así no choca con otro borrador). El número real se asigna al emitir.
            $borrador->numero = 'BORRADOR-'.$factura->id;
            $borrador->saldo = $factura->total;
            $borrador->extra = [];
            $borrador->created_by = $borrador->updated_by = $usuario->email;
            $borrador->save();

            // 3) Clonar las líneas.
            foreach ($factura->detalle as $linea) {
                $copia = $linea->replicate(['factura_id', 'created_by']);
                $copia->factura_id = $borrador->id;
                $copia->created_by = $usuario->email;
                $copia->save();
            }

            return $borrador;
        });

        return redirect()->route('admin.ventas.facturas.edit', $borrador)
            ->with('status', "Editando una nueva versión borrador de {$factura->numero} (la anterior quedó anulada). Ajústala y emítela; se asignará un número nuevo.");
    }

    /**
     * ¿El número manual ya está en uso (en facturas de venta o en CxC) para la compañía?
     */
    private function numeroExiste(int $companiaId, string $numero): bool
    {
        return VentaFactura::where('compania_id', $companiaId)->where('numero', $numero)->exists()
            || CxcDocumento::where('compania_id', $companiaId)->where('numero', $numero)->exists();
    }

    private function clientes(int $companiaId)
    {
        return Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);
    }

    private function cuentasIngreso(int $companiaId)
    {
        return CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->where('activa', true)
            ->where('naturaleza', 'CREDITO')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);
    }

    private function itemsVenta(int $companiaId)
    {
        return ItemProducto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'descripcion', 'precio_venta', 'impuesto_id', 'cuenta_ingreso_id']);
    }
}
