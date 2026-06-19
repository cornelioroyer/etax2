<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\TipoContacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\ItemProducto;
use App\Models\CxcDocumento;
use App\Models\CxcDocumentoDetalle;
use App\Models\TaxImpuesto;
use App\Models\VentaCotizacion;
use App\Models\VentaFactura;
use App\Models\VentaFacturaDetalle;
use App\Models\VentaNotaCredito;
use App\Services\AsientoAutomatico;
use App\Services\DgiFepConsulta;
use App\Services\RucDigitoVerificador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class VentaFacturaController extends Controller
{
    use ConCompaniaActiva;
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
            'lineas'                  => ['required', 'array', 'min:1'],
            'lineas.*.item_id'           => ['nullable', 'integer', 'exists:item_productos_servicios,id'],
            'lineas.*.descripcion'       => ['required', 'string', 'max:500'],
            'lineas.*.cantidad'          => ['required', 'numeric', 'min:0.0001'],
            'lineas.*.precio_unitario'   => ['required', 'numeric', 'min:0'],
            'lineas.*.impuesto_id'       => ['required', 'integer', Rule::in(TaxImpuesto::itbmsGlobales()->pluck('id')->all())],
            'lineas.*.cuenta_ingreso_id' => ['nullable', 'integer', 'exists:cgl_cuentas,id'],
        ]);

        $usuario = $request->user();
        $accion  = $request->input('accion', 'emitir');

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

        $subtotal = 0;
        $itbms    = 0;
        $lineasCalc = [];
        foreach ($data['lineas'] as $i => $linea) {
            $base        = round((float) $linea['cantidad'] * (float) $linea['precio_unitario'], 2);
            $tasa        = (float) ($impuestos[$linea['impuesto_id']]->porcentaje ?? 0);
            $impMonto    = round($base * $tasa / 100, 2);
            $totalLinea  = round($base + $impMonto, 2);
            $subtotal   += $base;
            $itbms      += $impMonto;
            $lineasCalc[] = array_merge($linea, ['base' => $base, 'impuesto_monto' => $impMonto, 'total_linea' => $totalLinea, 'linea' => $i + 1]);
        }
        $subtotal = round($subtotal, 2);
        $itbms    = round($itbms, 2);
        $total    = round($subtotal + $itbms, 2);

        if ($accion === 'borrador') {
            if (VentaFactura::where('compania_id', $companiaId)->where('estado', VentaFactura::ESTADO_BORRADOR)->exists()) {
                return back()->withInput()->withErrors(['factura' => 'Ya existe un borrador para esta compañía. Edítalo o emítelo antes de crear otro.']);
            }

            $factura = DB::transaction(function () use ($companiaId, $data, $usuario, $lineasCalc, $subtotal, $itbms, $total, $numeracion, $numeroManual) {
                $factura = VentaFactura::create([
                    'compania_id'       => $companiaId,
                    'cliente_id'        => $data['cliente_id'],
                    'numero'            => 'BORRADOR',
                    'fecha'             => $data['fecha'],
                    'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                    'subtotal'          => $subtotal,
                    'descuento'         => 0,
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
                        'descuento'         => 0,
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

        $factura = DB::transaction(function () use ($companiaId, $data, $usuario, $lineasCalc, $subtotal, $itbms, $total, $cuentaCxcId, $cuentaItbmsId, $cuentaVentasId, $numeracion, $numeroManual) {
            $numero = $numeracion === 'manual' ? $numeroManual : VentaFactura::siguienteNumero($companiaId);

            $factura = VentaFactura::create([
                'compania_id'       => $companiaId,
                'cliente_id'        => $data['cliente_id'],
                'numero'            => $numero,
                'fecha'             => $data['fecha'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'subtotal'          => $subtotal,
                'descuento'         => 0,
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
                    'descuento'         => 0,
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
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'subtotal'          => $subtotal,
                'descuento'         => 0,
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
                    'descuento'       => 0,
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

            $cliente = Contacto::find($data['cliente_id']);
            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId, $data['fecha'],
                "Factura de venta {$numero} — ".($cliente->nombre ?? ''),
                $numero, $lineasAsiento, 'CXC', 'ventas_facturas', $factura->id, $usuario,
            );

            $factura->update(['cxc_documento_id' => $cxc->id, 'asiento_id' => $asiento->id]);
            $cxc->update(['asiento_id' => $asiento->id]);

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
            'lineas'                     => ['required', 'array', 'min:1'],
            'lineas.*.item_id'           => ['nullable', 'integer', 'exists:item_productos_servicios,id'],
            'lineas.*.descripcion'       => ['required', 'string', 'max:500'],
            'lineas.*.cantidad'          => ['required', 'numeric', 'min:0.0001'],
            'lineas.*.precio_unitario'   => ['required', 'numeric', 'min:0'],
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

        $subtotal = 0; $itbms = 0; $lineasCalc = [];
        foreach ($data['lineas'] as $i => $linea) {
            $base       = round((float) $linea['cantidad'] * (float) $linea['precio_unitario'], 2);
            $tasa       = (float) ($impuestos[$linea['impuesto_id']]->porcentaje ?? 0);
            $impMonto   = round($base * $tasa / 100, 2);
            $subtotal  += $base;
            $itbms     += $impMonto;
            $lineasCalc[] = array_merge($linea, ['base' => $base, 'impuesto_monto' => $impMonto, 'total_linea' => round($base + $impMonto, 2), 'linea' => $i + 1]);
        }
        $subtotal = round($subtotal, 2);
        $itbms    = round($itbms, 2);
        $total    = round($subtotal + $itbms, 2);

        DB::transaction(function () use ($factura, $data, $usuario, $lineasCalc, $subtotal, $itbms, $total, $numeracion, $numeroManual) {
            $factura->update([
                'cliente_id'        => $data['cliente_id'],
                'fecha'             => $data['fecha'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'subtotal'          => $subtotal,
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
                    'descuento'         => 0,
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
            'descripcion'       => $d->descripcion,
            'cantidad'          => $d->cantidad,
            'precio_unitario'   => $d->precio_unitario,
            'base'              => round((float) $d->total_linea - (float) $d->impuesto_monto, 2),
            'impuesto_id'       => $d->impuesto_id,
            'impuesto_monto'    => (float) $d->impuesto_monto,
            'total_linea'       => (float) $d->total_linea,
            'cuenta_ingreso_id' => $d->cuenta_ingreso_id,
        ])->all();

        DB::transaction(function () use ($factura, $companiaId, $usuario, $lineasCalc, $subtotal, $itbms, $total, $cuentaCxcId, $cuentaItbmsId, $cuentaVentasId, $numeracion, $numeroManual) {
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
                'descuento'         => 0,
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
                    'descuento'       => 0,
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

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId, $factura->fecha,
                "Factura de venta {$numero} — ".($factura->cliente->nombre ?? ''),
                $numero, $lineasAsiento, 'CXC', 'ventas_facturas', $factura->id, $usuario,
            );

            $factura->update(['cxc_documento_id' => $cxc->id, 'asiento_id' => $asiento->id]);
            $cxc->update(['asiento_id' => $asiento->id]);
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
        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx,xls']]);

        $companiaId     = $this->companiaActivaId($request);
        $usuario        = $request->user();
        $cuentaCxcId    = CuentaDefault::idPara($companiaId, 'CXC');
        $cuentaItbmsId  = CuentaDefault::idPara($companiaId, 'ITBMS_POR_PAGAR');
        $cuentaVentasId = CuentaDefault::idPara($companiaId, 'VENTAS');

        if (! $cuentaCxcId) {
            return back()->withErrors(['archivo' => 'La compañía no tiene configurada la cuenta default CXC.']);
        }

        $impuestosGlobales = TaxImpuesto::itbmsGlobales();
        $tipoCliente       = TipoContacto::where('codigo', 'CLIENTE')->first();
        $consultaDgi       = new DgiFepConsulta;

        // Resuelve el impuesto ITBMS global cuya tasa corresponde a la línea
        // (base/itbms del documento de la DGI).
        $impuestoPara = function (float $base, float $itbms) use ($impuestosGlobales) {
            $tasa = $base > 0 ? (int) round($itbms / $base * 100) : 0;

            return $impuestosGlobales->first(fn ($t) => (int) round((float) $t->porcentaje) === $tasa)
                ?? $impuestosGlobales->firstWhere('porcentaje', 0)
                ?? $impuestosGlobales->first();
        };

        // Empareja el código de artículo de la DGI con el catálogo de la
        // compañía; si no existe, lo crea con datos mínimos. Devuelve el item o
        // null cuando la línea no trae código.
        $resolverItem = function (string $codigo, string $descripcion, float $precio, ?int $impuestoId)
            use ($companiaId, $cuentaVentasId, $usuario): ?ItemProducto {
            $codigo = substr(trim($codigo), 0, 100);
            if ($codigo === '') {
                return null;
            }

            $item = ItemProducto::where('compania_id', $companiaId)->where('codigo', $codigo)->first();
            if ($item) {
                return $item;
            }

            return ItemProducto::create([
                'compania_id'       => $companiaId,
                'codigo'            => $codigo,
                'nombre'            => substr($descripcion !== '' ? $descripcion : $codigo, 0, 200),
                'tipo'              => ItemProducto::TIPO_SERVICIO,
                'precio_venta'      => $precio,
                'impuesto_id'       => $impuestoId,
                'cuenta_ingreso_id' => $cuentaVentasId,
                'activo'            => true,
                'created_by'        => $usuario->email,
            ]);
        };

        // formatData = false: leer valores crudos. El reporte DGI guarda el "Monto" como
        // texto con formato #,##0.00; con formatData=true PhpSpreadsheet devolvería
        // "1,048.60" y (float) lo cortaría en 1.0 (la coma), descuadrando el asiento.
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('archivo')->getRealPath());
        $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, false);

        // Fila 0: título, Fila 1: encabezados, Filas 2+: datos
        // Columnas: 0=CUFE, 1=Tipo Documento, 3=Fecha Emisión, 5=RUC, 6=Nombre, 7=Subtotal, 8=ITBMS, 9=Monto
        $creadas  = 0;
        $omitidas = 0;
        $errores  = [];

        foreach (array_slice($rows, 2) as $idx => $row) {
            $fila     = $idx + 3;
            $tipo     = trim((string) ($row[1] ?? ''));
            $cufe     = trim((string) ($row[0] ?? ''));
            $ruc      = trim((string) ($row[5] ?? ''));
            $nombre   = trim((string) ($row[6] ?? ''));
            $fechaRaw = $row[3] ?? '';
            // Col O "Tiempo de Pago": "Inmediato" = contado; cualquier otro plazo = crédito.
            $tiempoPago = trim((string) ($row[14] ?? ''));
            $formaPago  = strcasecmp($tiempoPago, 'Inmediato') === 0
                ? Contacto::FORMA_PAGO_CONTADO
                : Contacto::FORMA_PAGO_CREDITO;
            $subtotal = $this->montoImport($row[7] ?? 0);
            $itbmsVal = $this->montoImport($row[8] ?? 0);
            // El total se deriva de subtotal+ITBMS para garantizar que el asiento cuadre;
            // la columna "Monto" del reporte es texto y puede traer separador de miles.
            $total    = round($subtotal + $itbmsVal, 2);

            $esFactura = $tipo === 'Factura de Operación Interna';
            $esNota    = $tipo === 'Nota de Crédito Genérica';

            if (! $esFactura && ! $esNota) {
                $omitidas++;
                continue;
            }

            if (! $cufe || ! $ruc || ! $fechaRaw) {
                $errores[] = "Fila {$fila}: datos incompletos.";
                continue;
            }

            // Deduplicación por CUFE
            $yaExiste = $esFactura
                ? VentaFactura::where('compania_id', $companiaId)->where('cufe', $cufe)->exists()
                : VentaNotaCredito::where('compania_id', $companiaId)->where('motivo', 'like', "%{$cufe}%")->exists();

            if ($yaExiste) {
                $omitidas++;
                continue;
            }

            try {
                if (is_numeric($fechaRaw)) {
                    $fecha = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $fechaRaw)->format('Y-m-d');
                } else {
                    $fecha = \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', trim((string) $fechaRaw))->toDateString();
                }
            } catch (\Throwable) {
                $errores[] = "Fila {$fila}: fecha inválida ({$fechaRaw}).";
                continue;
            }

            // Trae la factura real de la DGI por su CUFE: número del emisor (la
            // propia compañía) y líneas de detalle con su código de artículo.
            $dgi = $esFactura ? $consultaDgi->porCufe($cufe) : null;

            try {
                DB::transaction(function () use (
                    $companiaId, $ruc, $nombre, $fecha, $subtotal, $itbmsVal, $total,
                    $cufe, $dgi, $impuestoPara, $resolverItem,
                    $cuentaCxcId, $cuentaItbmsId, $cuentaVentasId,
                    $usuario, $tipoCliente, $esFactura, $formaPago
                ) {
                    $cliente = Contacto::where('compania_id', $companiaId)->where('codigo', $ruc)->first();
                    if (! $cliente) {
                        $cliente = Contacto::create([
                            'compania_id'    => $companiaId,
                            'codigo'         => $ruc,
                            'nombre'         => $nombre,
                            'identificacion' => $ruc,
                            'dv'             => RucDigitoVerificador::calcular($ruc),
                            'forma_pago'     => $formaPago,
                            'activo'         => true,
                            'created_by'     => $usuario->email,
                        ]);
                        if ($tipoCliente) {
                            $cliente->tipos()->sync([$tipoCliente->id]);
                        }
                    }

                    if ($esFactura) {
                        // Líneas: detalle real de la DGI (una por artículo, con su
                        // código) si la consulta respondió; si no, una sola línea
                        // "Servicios" con los totales del Excel.
                        $lineasCalc = [];
                        if ($dgi && ! empty($dgi['lineas'])) {
                            foreach ($dgi['lineas'] as $n => $l) {
                                $itbmsLinea = round((float) $l['itbms'], 2);
                                $baseLinea  = round((float) $l['total'] - $itbmsLinea, 2);
                                $cantidad   = (float) $l['cantidad'] ?: 1.0;
                                $precio     = (float) $l['precio_unitario'];
                                if ($precio == 0.0 && $cantidad != 0.0) {
                                    $precio = round($baseLinea / $cantidad, 4);
                                }
                                $imp  = $impuestoPara($baseLinea, $itbmsLinea);
                                $item = $resolverItem((string) ($l['codigo'] ?? ''), (string) $l['descripcion'], $precio, $imp?->id);
                                $lineasCalc[] = [
                                    'linea'             => $n + 1,
                                    'item_id'           => $item?->id,
                                    'descripcion'       => substr((string) $l['descripcion'] ?: 'Sin descripción', 0, 500),
                                    'cantidad'          => $cantidad,
                                    'precio_unitario'   => $precio,
                                    'descuento'         => round((float) ($l['descuento'] ?? 0), 2),
                                    'impuesto_id'       => $imp?->id,
                                    'impuesto_monto'    => $itbmsLinea,
                                    'base'              => $baseLinea,
                                    'total_linea'       => round((float) $l['total'], 2),
                                    'cuenta_ingreso_id' => $item?->cuenta_ingreso_id ?? $cuentaVentasId,
                                ];
                            }
                        } else {
                            $imp = $impuestoPara($subtotal, $itbmsVal);
                            $lineasCalc[] = [
                                'linea'             => 1,
                                'item_id'           => null,
                                'descripcion'       => 'Servicios',
                                'cantidad'          => 1.0,
                                'precio_unitario'   => $subtotal,
                                'descuento'         => 0,
                                'impuesto_id'       => $imp?->id,
                                'impuesto_monto'    => $itbmsVal,
                                'base'              => $subtotal,
                                'total_linea'       => $total,
                                'cuenta_ingreso_id' => $cuentaVentasId,
                            ];
                        }

                        $subtotalDoc = round(array_sum(array_column($lineasCalc, 'base')), 2);
                        $itbmsDoc    = round(array_sum(array_column($lineasCalc, 'impuesto_monto')), 2);
                        $totalDoc    = round($subtotalDoc + $itbmsDoc, 2);

                        // Número real del emisor (la propia compañía) si la DGI lo
                        // devolvió y no está usado; si no, numeración interna.
                        $numeroDgi = $dgi['numero'] ?? null;
                        $numero = ($numeroDgi !== null && ! $this->numeroExiste($companiaId, $numeroDgi))
                            ? $numeroDgi
                            : VentaFactura::siguienteNumero($companiaId);

                        $factura = VentaFactura::create([
                            'compania_id' => $companiaId,
                            'cliente_id'  => $cliente->id,
                            'numero'      => $numero,
                            'cufe'        => $cufe,
                            'fecha'       => $fecha,
                            'subtotal'    => $subtotalDoc,
                            'descuento'   => 0,
                            'itbms'       => $itbmsDoc,
                            'total'       => $totalDoc,
                            'saldo'       => $totalDoc,
                            'estado'      => VentaFactura::ESTADO_EMITIDA,
                            'created_by'  => $usuario->email,
                        ]);

                        foreach ($lineasCalc as $l) {
                            VentaFacturaDetalle::create([
                                'factura_id'        => $factura->id,
                                'linea'             => $l['linea'],
                                'item_id'           => $l['item_id'],
                                'descripcion'       => $l['descripcion'],
                                'cantidad'          => $l['cantidad'],
                                'precio_unitario'   => $l['precio_unitario'],
                                'descuento'         => $l['descuento'],
                                'impuesto_id'       => $l['impuesto_id'],
                                'impuesto_monto'    => $l['impuesto_monto'],
                                'total_linea'       => $l['total_linea'],
                                'cuenta_ingreso_id' => $l['cuenta_ingreso_id'],
                                'created_by'        => $usuario->email,
                            ]);
                        }

                        $cxc = CxcDocumento::create([
                            'compania_id'    => $companiaId,
                            'cliente_id'     => $cliente->id,
                            'tipo_documento' => CxcDocumento::TIPO_FACTURA,
                            'numero'         => $numero,
                            'fecha'          => $fecha,
                            'subtotal'       => $subtotalDoc,
                            'descuento'      => 0,
                            'impuesto'       => $itbmsDoc,
                            'total'          => $totalDoc,
                            'saldo'          => $totalDoc,
                            'estado'         => CxcDocumento::ESTADO_PENDIENTE,
                            'created_by'     => $usuario->email,
                        ]);

                        foreach ($lineasCalc as $l) {
                            CxcDocumentoDetalle::create([
                                'documento_id'    => $cxc->id,
                                'linea'           => $l['linea'],
                                'item_id'         => $l['item_id'],
                                'descripcion'     => $l['descripcion'],
                                'cantidad'        => $l['cantidad'],
                                'precio_unitario' => $l['precio_unitario'],
                                'descuento'       => $l['descuento'],
                                'impuesto_monto'  => $l['impuesto_monto'],
                                'total_linea'     => $l['total_linea'],
                                'cuenta_id'       => $l['cuenta_ingreso_id'],
                                'created_by'      => $usuario->email,
                            ]);
                        }

                        $lineasAsiento = [
                            ['cuenta_id' => $cuentaCxcId, 'contacto_id' => $cliente->id, 'descripcion' => "Factura {$numero}", 'debito' => $totalDoc, 'credito' => 0],
                        ];
                        foreach ($lineasCalc as $l) {
                            $lineasAsiento[] = ['cuenta_id' => $l['cuenta_ingreso_id'], 'descripcion' => substr($l['descripcion'], 0, 255), 'debito' => 0, 'credito' => $l['base']];
                        }
                        if ($itbmsDoc > 0 && $cuentaItbmsId) {
                            $lineasAsiento[] = ['cuenta_id' => $cuentaItbmsId, 'descripcion' => "ITBMS factura {$numero}", 'debito' => 0, 'credito' => $itbmsDoc];
                        }

                        $asiento = app(AsientoAutomatico::class)->postear(
                            $companiaId, $fecha,
                            "Factura de venta {$numero} — {$nombre}",
                            $numero, $lineasAsiento, 'CXC', 'ventas_facturas', $factura->id, $usuario,
                        );

                        $factura->update(['cxc_documento_id' => $cxc->id, 'asiento_id' => $asiento->id]);
                        $cxc->update(['asiento_id' => $asiento->id]);
                    } else {
                        // Nota de crédito independiente (sin vincular a factura)
                        $numero = VentaNotaCredito::siguienteNumero($companiaId);

                        $cxcNota = CxcDocumento::create([
                            'compania_id'    => $companiaId,
                            'cliente_id'     => $cliente->id,
                            'tipo_documento' => CxcDocumento::TIPO_NOTA_CREDITO,
                            'numero'         => $numero,
                            'fecha'          => $fecha,
                            'subtotal'       => $total,
                            'impuesto'       => 0,
                            'total'          => $total,
                            'saldo'          => $total,
                            'estado'         => CxcDocumento::ESTADO_PENDIENTE,
                            'created_by'     => $usuario->email,
                        ]);

                        $nota = VentaNotaCredito::create([
                            'compania_id'      => $companiaId,
                            'cliente_id'       => $cliente->id,
                            'numero'           => $numero,
                            'fecha'            => $fecha,
                            'motivo'           => "FEL: {$cufe}",
                            'total'            => $total,
                            'cxc_documento_id' => $cxcNota->id,
                            'estado'           => VentaNotaCredito::ESTADO_EMITIDA,
                            'created_by'       => $usuario->email,
                            'updated_by'       => $usuario->email,
                        ]);

                        $asiento = app(AsientoAutomatico::class)->postear(
                            $companiaId, $fecha,
                            "NC Ventas {$numero} — {$nombre}",
                            $numero,
                            [
                                ['cuenta_id' => $cuentaVentasId, 'descripcion' => "Nota crédito {$numero}", 'debito' => $total, 'credito' => 0],
                                ['cuenta_id' => $cuentaCxcId, 'contacto_id' => $cliente->id, 'descripcion' => "Nota crédito {$numero}", 'debito' => 0, 'credito' => $total],
                            ],
                            'VENTAS', 'ventas_facturas', $nota->id, $usuario,
                        );

                        $nota->update(['asiento_id' => $asiento->id]);
                    }
                });
                $creadas++;
            } catch (\Throwable $e) {
                $errores[] = "Fila {$fila}: ".$e->getMessage();
            }
        }

        $msg = "Importación completada: {$creadas} documentos creados, {$omitidas} omitidos.";
        if ($errores) {
            return back()->with('status', $msg)
                ->withErrors(['importar' => implode(' | ', array_slice($errores, 0, 5))]);
        }

        return redirect()->route('admin.ventas.facturas.index')->with('status', $msg);
    }

    public function anular(Request $request, VentaFactura $factura): RedirectResponse
    {
        abort_unless($factura->compania_id === $this->companiaActivaId($request), 404);

        if ($factura->esAnulada()) {
            return back()->withErrors(['factura' => 'La factura ya está anulada.']);
        }

        if ($factura->cxcDocumento && $factura->cxcDocumento->aplicacionesComoDestino()->exists()) {
            return back()->withErrors(['factura' => 'La factura tiene cobros aplicados; anula primero los cobros en CxC.']);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($factura, $usuario) {
            // Anular el asiento contable
            if ($factura->asiento) {
                app(AsientoAutomatico::class)->anular($factura->asiento, $usuario);
            }

            // Anular el cxc_documentos vinculado
            if ($factura->cxcDocumento) {
                $factura->cxcDocumento->update([
                    'estado'     => 'ANULADO',
                    'saldo'      => 0,
                    'updated_by' => $usuario->email,
                ]);
            }

            $factura->update([
                'estado'     => VentaFactura::ESTADO_ANULADA,
                'saldo'      => 0,
                'updated_by' => $usuario->email,
            ]);

            // Revertir la cotización a ACEPTADA si viene de una
            if ($factura->cotizacion_id) {
                $factura->cotizacion->update([
                    'estado'     => VentaCotizacion::ESTADO_ACEPTADA,
                    'updated_by' => $usuario->email,
                ]);
            }
        });

        return redirect()->route('admin.ventas.facturas.show', $factura)
            ->with('status', "Factura {$factura->numero} anulada.");
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
            ->where('naturaleza', 'ACREEDORA')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);
    }

    private function itemsVenta(int $companiaId)
    {
        return ItemProducto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'precio_venta', 'impuesto_id', 'cuenta_ingreso_id']);
    }
}
