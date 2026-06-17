<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\CuentaDefault;
use App\Models\CxcDocumento;
use App\Models\CxcDocumentoDetalle;
use App\Models\TaxImpuesto;
use App\Models\VentaCotizacion;
use App\Models\VentaFactura;
use App\Models\VentaFacturaDetalle;
use App\Services\AsientoAutomatico;
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
            'clientes'      => $this->clientes($companiaId),
            'impuestos'     => TaxImpuesto::itbmsGlobales(),
            'numeroPreview' => VentaFactura::siguienteNumero($companiaId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'cliente_id'        => ['required', 'integer', 'exists:contact_contactos,id'],
            'fecha'             => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha'],
            'notas'             => ['nullable', 'string', 'max:1000'],
            'lineas'            => ['required', 'array', 'min:1'],
            'lineas.*.descripcion'    => ['required', 'string', 'max:500'],
            'lineas.*.cantidad'       => ['required', 'numeric', 'min:0.0001'],
            'lineas.*.precio_unitario'=> ['required', 'numeric', 'min:0'],
            'lineas.*.impuesto_id'    => ['required', 'integer', Rule::in(TaxImpuesto::itbmsGlobales()->pluck('id')->all())],
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
                        'factura_id'      => $factura->id,
                        'linea'           => $linea['linea'],
                        'descripcion'     => $linea['descripcion'],
                        'cantidad'        => $linea['cantidad'],
                        'precio_unitario' => $linea['precio_unitario'],
                        'descuento'       => 0,
                        'impuesto_id'     => $linea['impuesto_id'],
                        'impuesto_monto'  => $linea['impuesto_monto'],
                        'total_linea'     => $linea['total_linea'],
                        'created_by'      => $usuario->email,
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
                    'descripcion'       => $linea['descripcion'],
                    'cantidad'          => $linea['cantidad'],
                    'precio_unitario'   => $linea['precio_unitario'],
                    'descuento'         => 0,
                    'impuesto_id'       => $linea['impuesto_id'],
                    'impuesto_monto'    => $linea['impuesto_monto'],
                    'total_linea'       => $linea['total_linea'],
                    'cuenta_ingreso_id' => $cuentaVentasId,
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
                    'cuenta_id'       => $cuentaVentasId,
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
                    'cuenta_id'   => $cuentaVentasId,
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
            'estado'     => ['nullable', Rule::in(['BORRADOR', 'EMITIDA', 'PARCIAL', 'PAGADA', 'ANULADA'])],
            'cliente_id' => ['nullable', 'integer'],
            'desde'      => ['nullable', 'date'],
            'hasta'      => ['nullable', 'date'],
            'q'          => ['nullable', 'string', 'max:100'],
            'sort'       => ['nullable', Rule::in(['numero', 'fecha', 'fecha_vencimiento', 'total', 'saldo', 'estado'])],
            'dir'        => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $filtros['sort'] ?? 'fecha';
        $dir  = $filtros['dir']  ?? 'desc';

        $consulta = VentaFactura::query()
            ->with('cliente')
            ->where('compania_id', $companiaId)
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
                'titulo' => 'Facturas de venta',
                'compania' => Compania::find($companiaId)?->nombre ?? '',
                'subtitulo' => 'Listado al '.now()->format('d/m/Y').' — '.$todas->count().' facturas',
                'encabezados' => [
                    ['titulo' => 'Número'], ['titulo' => 'Fecha'], ['titulo' => 'Vence'],
                    ['titulo' => 'Cliente'], ['titulo' => 'Total', 'num' => true],
                    ['titulo' => 'Saldo', 'num' => true], ['titulo' => 'Estado'],
                ],
                'filas' => $todas->map(fn ($f) => [
                    $f->numero, $f->fecha->format('d/m/Y'),
                    $f->fecha_vencimiento?->format('d/m/Y') ?? '',
                    $f->cliente->nombre ?? '',
                    number_format((float) $f->total, 2),
                    number_format((float) $f->saldo, 2),
                    ucfirst(strtolower($f->estado)),
                ])->all(),
                'totales' => ['TOTAL', '', '', '',
                    number_format((float) $todas->sum('total'), 2),
                    number_format((float) $todas->sum('saldo'), 2), ''],
            ], 'facturas_venta_'.now()->format('Y-m-d'))) {
                return $export;
            }
        }

        $saldoTotal = VentaFactura::where('compania_id', $companiaId)
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
            'clientes'      => $this->clientes($companiaId),
            'impuestos'     => TaxImpuesto::itbmsGlobales(),
            'factura'       => $factura,
            'numeroPreview' => VentaFactura::siguienteNumero($companiaId),
        ]);
    }

    public function update(Request $request, VentaFactura $factura): RedirectResponse
    {
        abort_unless($factura->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($factura->estado === VentaFactura::ESTADO_BORRADOR, 403);

        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'cliente_id'              => ['required', 'integer', 'exists:contact_contactos,id'],
            'fecha'                   => ['required', 'date'],
            'fecha_vencimiento'       => ['nullable', 'date', 'after_or_equal:fecha'],
            'notas'                   => ['nullable', 'string', 'max:1000'],
            'lineas'                  => ['required', 'array', 'min:1'],
            'lineas.*.descripcion'    => ['required', 'string', 'max:500'],
            'lineas.*.cantidad'       => ['required', 'numeric', 'min:0.0001'],
            'lineas.*.precio_unitario'=> ['required', 'numeric', 'min:0'],
            'lineas.*.impuesto_id'    => ['required', 'integer', Rule::in(TaxImpuesto::itbmsGlobales()->pluck('id')->all())],
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
                    'factura_id'      => $factura->id,
                    'linea'           => $linea['linea'],
                    'descripcion'     => $linea['descripcion'],
                    'cantidad'        => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'descuento'       => 0,
                    'impuesto_id'     => $linea['impuesto_id'],
                    'impuesto_monto'  => $linea['impuesto_monto'],
                    'total_linea'     => $linea['total_linea'],
                    'created_by'      => $usuario->email,
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
            'linea'          => $d->linea,
            'descripcion'    => $d->descripcion,
            'cantidad'       => $d->cantidad,
            'precio_unitario'=> $d->precio_unitario,
            'base'           => round((float) $d->total_linea - (float) $d->impuesto_monto, 2),
            'impuesto_id'    => $d->impuesto_id,
            'impuesto_monto' => (float) $d->impuesto_monto,
            'total_linea'    => (float) $d->total_linea,
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
                    'cuenta_id'       => $cuentaVentasId,
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
                $lineasAsiento[] = ['cuenta_id' => $cuentaVentasId, 'descripcion' => $linea['descripcion'], 'debito' => 0, 'credito' => $linea['base']];
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
}
