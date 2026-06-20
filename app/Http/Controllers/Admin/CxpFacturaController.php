<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Imports\CxpFacturasImport;
use App\Jobs\ProcesarImportacionCxpFel;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxpDocumento;
use App\Models\CxpDocumentoDetalle;
use App\Models\CxpImportacion;
use App\Models\TipoContacto;
use App\Models\TipoDocumento;
use App\Services\AsientoAutomatico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class CxpFacturaController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    public function index(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'tipo' => ['nullable', Rule::in(CxpDocumento::tiposModulo())],
            'estado' => ['nullable', Rule::in(['BORRADOR', 'PENDIENTE', 'PARCIAL', 'PAGADO', 'ANULADO'])],
            'proveedor_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['numero', 'tipo_documento', 'fecha', 'proveedor', 'subtotal', 'impuesto', 'total', 'saldo', 'estado'])],
            'dir'  => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $filtros['sort'] ?? 'fecha';
        $dir  = $filtros['dir']  ?? 'desc';

        $consulta = CxpDocumento::query()
            ->with('proveedor')
            ->where('compania_id', $companiaId)
            ->whereIn('tipo_documento', CxpDocumento::tiposModulo())
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo_documento', $tipo))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['proveedor_id'] ?? null, fn ($q, $proveedor) => $q->where('proveedor_id', $proveedor))
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->when($filtros['q'] ?? null, function ($q, $texto) {
                $busqueda = '%'.mb_strtolower($texto).'%';
                $q->where(function ($q) use ($busqueda) {
                    $q->whereRaw('LOWER(numero) LIKE ?', [$busqueda])
                        ->orWhereHas('proveedor', fn ($c) => $c->whereRaw('LOWER(nombre) LIKE ?', [$busqueda]));
                });
            })
            ->when($sort === 'proveedor',
                fn ($q) => $q->orderByRaw('(SELECT nombre FROM contact_contactos WHERE id = cxp_documentos.proveedor_id) '.($dir === 'asc' ? 'ASC' : 'DESC'))
            )
            ->when($sort !== 'proveedor', fn ($q) => $q->orderBy($sort, $dir))
            ->when($sort !== 'fecha', fn ($q) => $q->orderByDesc('fecha'));

        if ($request->query('export')) {
            $todas = (clone $consulta)->get();

            if ($export = $this->exportarReporte($request, 'admin.exports.listado', [
                'titulo' => 'Facturas de Compras',
                'compania' => Compania::find($companiaId)?->nombre ?? '',
                'subtitulo' => 'Listado al '.now()->format('d/m/Y').' — '.$todas->count().' facturas',
                'encabezados' => [
                    ['titulo' => 'Número'], ['titulo' => 'Fecha'],
                    ['titulo' => 'Proveedor'],
                    ['titulo' => 'Subtotal', 'num' => true], ['titulo' => 'ITBMS', 'num' => true],
                    ['titulo' => 'Total', 'num' => true], ['titulo' => 'Saldo', 'num' => true],
                    ['titulo' => 'Estado'],
                ],
                'filas' => $todas->map(fn ($f) => [
                    $f->numero, $f->fecha->format('d/m/Y'),
                    $f->proveedor->nombre ?? '',
                    number_format((float) $f->subtotal, 2), number_format((float) $f->impuesto, 2),
                    number_format((float) $f->total, 2), number_format((float) $f->saldo, 2),
                    ucfirst(strtolower($f->estado)),
                ])->all(),
                'totales' => ['TOTAL', '', '',
                    number_format((float) $todas->sum('subtotal'), 2),
                    number_format((float) $todas->sum('impuesto'), 2),
                    number_format((float) $todas->sum('total'), 2),
                    number_format((float) $todas->sum('saldo'), 2), ''],
            ], 'facturas_cxp_'.now()->format('Y-m-d'))) {
                return $export;
            }
        }

        // Totales de la columna sobre TODO el conjunto filtrado (no solo la
        // página): mismo signo que se muestra (notas de crédito en negativo).
        $nc = CxpDocumento::TIPO_NOTA_CREDITO;
        $totales = (clone $consulta)->toBase()->reorder()->selectRaw(
            'COALESCE(SUM(CASE WHEN tipo_documento = ? THEN -subtotal ELSE subtotal END), 0) AS subtotal, '.
            'COALESCE(SUM(CASE WHEN tipo_documento = ? THEN -impuesto ELSE impuesto END), 0) AS impuesto, '.
            'COALESCE(SUM(CASE WHEN tipo_documento = ? THEN -total   ELSE total   END), 0) AS total, '.
            'COALESCE(SUM(CASE WHEN tipo_documento = ? THEN -saldo   ELSE saldo   END), 0) AS saldo',
            [$nc, $nc, $nc, $nc]
        )->first();

        $facturas = $consulta->paginate(25)->withQueryString();

        // Saldo neto: facturas y notas de débito suman; notas de crédito restan.
        $saldoTotal = (float) CxpDocumento::where('compania_id', $companiaId)
            ->whereIn('tipo_documento', CxpDocumento::tiposModulo())
            ->whereNotIn('estado', [CxpDocumento::ESTADO_ANULADO, CxpDocumento::ESTADO_BORRADOR])
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo_documento = ? THEN -saldo ELSE saldo END), 0) AS saldo', [CxpDocumento::TIPO_NOTA_CREDITO])
            ->value('saldo');

        return view('admin.cxp.facturas.index', [
            'facturas' => $facturas,
            'filtros' => $filtros,
            'proveedores' => $this->proveedores($companiaId),
            'saldoTotal' => $saldoTotal,
            'totales' => $totales,
            'sort' => $sort,
            'dir'  => $dir,
        ]);
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        return view('admin.cxp.facturas.create', [
            'proveedores' => $this->proveedores($companiaId),
            'cuentas' => CuentaContable::where('compania_id', $companiaId)
                ->where('permite_movimiento', true)
                ->where('activa', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
            'cuentaGastoId' => CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT'),
            'cuentasPago' => $this->cuentasPago($companiaId),
            'cuentaPagoId' => CuentaDefault::idPara($companiaId, 'BANCO_DEFAULT')
                ?? CuentaDefault::idPara($companiaId, 'CAJA_DEFAULT'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario = $request->user();

        $data = $this->datosValidados($request, $companiaId);
        [$lineas, $subtotal, $impuesto, $total] = $this->calcularLineas($data['lineas']);

        $tipo = $data['tipo_documento'];

        // Solo los documentos tipo factura (factura/reembolso/importación) se
        // pueden registrar al contado; las notas de crédito y débito siempre
        // nacen como borrador a crédito y se contabilizan aparte.
        $contado = in_array($tipo, CxpDocumento::tiposFacturaCargo(), true)
            && (($data['forma_pago'] ?? 'CREDITO') === 'CONTADO');

        $factura = DB::transaction(function () use ($companiaId, $data, $tipo, $lineas, $subtotal, $impuesto, $total, $usuario, $contado) {
            $factura = CxpDocumento::create([
                'compania_id' => $companiaId,
                'proveedor_id' => $data['proveedor_id'],
                'tipo_documento' => $tipo,
                'numero' => $data['numero'],
                'fecha' => $data['fecha'],
                // En contado no hay crédito pendiente: vence el mismo día.
                'fecha_vencimiento' => $contado ? $data['fecha'] : ($data['fecha_vencimiento'] ?? null),
                'subtotal' => $subtotal,
                'descuento' => 0,
                'impuesto' => $impuesto,
                'total' => $total,
                'saldo' => $contado ? 0 : $total,
                'estado' => $contado ? CxpDocumento::ESTADO_PAGADO : CxpDocumento::ESTADO_BORRADOR,
                'created_by' => $usuario->email,
            ]);

            foreach ($lineas as $linea) {
                CxpDocumentoDetalle::create($linea + ['documento_id' => $factura->id, 'created_by' => $usuario->email]);
            }

            if ($contado) {
                $factura->load(['proveedor', 'detalle']);
                $this->contabilizarContado($factura, (int) $data['cuenta_pago_id'], $usuario);
            }

            return $factura;
        });

        $etiqueta = TipoDocumento::descripcion(TipoDocumento::AUX_CXP, $tipo);

        $mensaje = $contado
            ? "Compra al contado {$factura->numero} registrada y contabilizada. Asiento {$factura->fresh()->asiento->numero}."
            : "{$etiqueta} {$factura->numero} guardada como borrador. Revísala y contabilízala cuando esté lista.";

        return redirect()->route('admin.cxp.facturas.show', $factura)->with('status', $mensaje);
    }

    public function show(Request $request, CxpDocumento $documento): View
    {
        $this->autorizarFactura($request, $documento);

        $documento->load(['proveedor', 'detalle.cuenta', 'asiento', 'aplicacionesComoDestino.origen', 'compraOrden']);

        return view('admin.cxp.facturas.show', ['factura' => $documento]);
    }

    public function edit(Request $request, CxpDocumento $documento): View|RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $this->autorizarFactura($request, $documento);

        if (! $documento->esBorrador()) {
            return redirect()->route('admin.cxp.facturas.show', $documento)
                ->withErrors(['documento' => 'Solo se pueden editar facturas en borrador. Una factura contabilizada debe anularse.']);
        }

        $documento->load('detalle');

        return view('admin.cxp.facturas.edit', [
            'factura' => $documento,
            'proveedores' => $this->proveedores($companiaId),
            'cuentas' => CuentaContable::where('compania_id', $companiaId)
                ->where('permite_movimiento', true)
                ->where('activa', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
            'cuentaGastoId' => CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT'),
        ]);
    }

    public function update(Request $request, CxpDocumento $documento): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $this->autorizarFactura($request, $documento);

        if (! $documento->esBorrador()) {
            return redirect()->route('admin.cxp.facturas.show', $documento)
                ->withErrors(['documento' => 'Solo se pueden editar facturas en borrador.']);
        }

        $usuario = $request->user();
        $data = $this->datosValidados($request, $companiaId, $documento->id, $documento->tipo_documento);
        [$lineas, $subtotal, $impuesto, $total] = $this->calcularLineas($data['lineas']);

        DB::transaction(function () use ($documento, $data, $lineas, $subtotal, $impuesto, $total, $usuario) {
            $documento->update([
                'proveedor_id' => $data['proveedor_id'],
                'numero' => $data['numero'],
                'fecha' => $data['fecha'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'subtotal' => $subtotal,
                'impuesto' => $impuesto,
                'total' => $total,
                'saldo' => $total,
                'updated_by' => $usuario->email,
            ]);

            $documento->detalle()->delete();

            foreach ($lineas as $linea) {
                CxpDocumentoDetalle::create($linea + ['documento_id' => $documento->id, 'created_by' => $usuario->email]);
            }
        });

        return redirect()->route('admin.cxp.facturas.show', $documento)
            ->with('status', "Borrador {$documento->numero} actualizado.");
    }

    public function contabilizar(Request $request, CxpDocumento $documento): RedirectResponse
    {
        $this->autorizarFactura($request, $documento);

        if (! $documento->esBorrador()) {
            return back()->withErrors(['documento' => 'La factura ya está contabilizada o anulada.']);
        }

        $usuario = $request->user();
        $documento->load(['proveedor', 'detalle']);

        DB::transaction(function () use ($documento, $usuario) {
            $this->contabilizarFactura($documento, $usuario);
        });

        return redirect()->route('admin.cxp.facturas.show', $documento)
            ->with('status', "Factura {$documento->numero} contabilizada. Asiento {$documento->fresh()->asiento->numero}.");
    }

    public function destroy(Request $request, CxpDocumento $documento): RedirectResponse
    {
        $this->autorizarFactura($request, $documento);

        if (! $documento->esBorrador()) {
            return back()->withErrors(['documento' => 'Solo se pueden eliminar facturas en borrador. Una factura contabilizada debe anularse.']);
        }

        $numero = $documento->numero;

        DB::transaction(function () use ($documento) {
            $documento->detalle()->delete();
            $documento->delete();
        });

        return redirect()->route('admin.cxp.facturas.index')
            ->with('status', "Borrador {$numero} eliminado.");
    }

    /** Valida los datos del formulario y verifica que el número no esté duplicado. */
    private function datosValidados(Request $request, int $companiaId, ?int $exceptoId = null, ?string $tipoForzado = null): array
    {
        $data = $request->validate([
            'tipo_documento' => ['nullable', Rule::in(CxpDocumento::tiposModulo())],
            'proveedor_id' => [
                'required', 'integer',
                Rule::exists('contact_contactos', 'id')->where('compania_id', $companiaId),
            ],
            'numero' => ['required', 'string', 'max:50'],
            'fecha' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha'],
            'forma_pago' => ['nullable', Rule::in(['CREDITO', 'CONTADO'])],
            'cuenta_pago_id' => [
                Rule::requiredIf(fn () => $request->input('forma_pago') === 'CONTADO'),
                'nullable', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.descripcion' => ['required', 'string', 'max:500'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'gte:0', 'max:999999999'],
            'lineas.*.tasa_itbms' => ['required', 'integer', Rule::in(CxcFacturaController::TASAS_ITBMS)],
            'lineas.*.cuenta_id' => [
                'required', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
        ]);

        // Tipo del documento: forzado (al editar conserva el original) o el
        // elegido en el formulario; por defecto factura.
        $tipo = $tipoForzado ?? ($data['tipo_documento'] ?? CxpDocumento::TIPO_FACTURA);
        $data['tipo_documento'] = $tipo;

        $duplicada = CxpDocumento::where('compania_id', $companiaId)
            ->where('proveedor_id', $data['proveedor_id'])
            ->where('tipo_documento', $tipo)
            ->where('numero', $data['numero'])
            ->when($exceptoId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();

        if ($duplicada) {
            $etiqueta = mb_strtolower(TipoDocumento::descripcion(TipoDocumento::AUX_CXP, $tipo));

            throw ValidationException::withMessages([
                'numero' => "Ya existe {$etiqueta} {$data['numero']} de ese proveedor.",
            ]);
        }

        return $data;
    }

    /**
     * Calcula líneas normalizadas y los totales (subtotal/ITBMS/total).
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float, 2: float, 3: float}
     */
    private function calcularLineas(array $lineasInput): array
    {
        $lineas = [];
        $subtotal = 0.0;
        $impuesto = 0.0;

        foreach (array_values($lineasInput) as $i => $linea) {
            $cantidad = round((float) $linea['cantidad'], 4);
            $precio = round((float) $linea['precio_unitario'], 4);
            $base = round($cantidad * $precio, 2);
            $itbms = round($base * ((int) $linea['tasa_itbms']) / 100, 2);

            $subtotal += $base;
            $impuesto += $itbms;

            $lineas[] = [
                'linea' => $i + 1,
                'descripcion' => $linea['descripcion'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'impuesto_monto' => $itbms,
                'total_linea' => round($base + $itbms, 2),
                'cuenta_id' => (int) $linea['cuenta_id'],
            ];
        }

        $subtotal = round($subtotal, 2);
        $impuesto = round($impuesto, 2);
        $total = round($subtotal + $impuesto, 2);

        if ($total <= 0) {
            throw ValidationException::withMessages(['lineas' => 'El total de la factura debe ser mayor que cero.']);
        }

        return [$lineas, $subtotal, $impuesto, $total];
    }

    /**
     * Postea el asiento de una factura en borrador y la deja en estado PENDIENTE.
     * Debe llamarse dentro de una transacción.
     */
    private function contabilizarFactura(CxpDocumento $factura, $usuario): void
    {
        $companiaId = $factura->compania_id;
        $impuesto = round((float) $factura->impuesto, 2);

        $cuentaCxpId = CuentaDefault::idPara($companiaId, 'CXP');
        $cuentaItbmsId = CuentaDefault::idPara($companiaId, 'ITBMS_CREDITO');

        if (! $cuentaCxpId) {
            throw ValidationException::withMessages([
                'documento' => 'La compañía no tiene configurada la cuenta default CXP (Cuentas por Pagar). Aplica una plantilla de plan de cuentas o configúrala.',
            ]);
        }

        if ($impuesto > 0 && ! $cuentaItbmsId) {
            throw ValidationException::withMessages([
                'documento' => 'La compañía no tiene configurada la cuenta default ITBMS_CREDITO; no se puede contabilizar el ITBMS de compras.',
            ]);
        }

        // La dirección del asiento depende del SIGNO del tipo en el maestro: los
        // abonos (NC, -1) invierten la compra; los cargos (factura/ND/reembolso/
        // importación, +1) la registran como deuda al proveedor.
        $esCredito = $factura->esAbono();
        $total = round((float) $factura->total, 2);
        $etiqueta = $factura->etiquetaTipo();

        $lineasAsiento = [];

        if ($esCredito) {
            // Nota de crédito: invierte la compra. Dr CXP; Cr contrapartida + Cr ITBMS.
            $lineasAsiento[] = [
                'cuenta_id' => $cuentaCxpId,
                'contacto_id' => $factura->proveedor_id,
                'descripcion' => "{$etiqueta} {$factura->numero}",
                'debito' => $total,
                'credito' => 0,
            ];
            foreach ($factura->detalle as $linea) {
                $base = round((float) $linea->total_linea - (float) $linea->impuesto_monto, 2);
                $lineasAsiento[] = [
                    'cuenta_id' => $linea->cuenta_id,
                    'descripcion' => $linea->descripcion,
                    'debito' => 0,
                    'credito' => $base,
                ];
            }
            if ($impuesto > 0) {
                $lineasAsiento[] = [
                    'cuenta_id' => $cuentaItbmsId,
                    'descripcion' => "ITBMS {$etiqueta} {$factura->numero}",
                    'debito' => 0,
                    'credito' => $impuesto,
                ];
            }
        } else {
            // Factura y nota de débito: Dr contrapartida + Dr ITBMS; Cr CXP.
            foreach ($factura->detalle as $linea) {
                $base = round((float) $linea->total_linea - (float) $linea->impuesto_monto, 2);
                $lineasAsiento[] = [
                    'cuenta_id' => $linea->cuenta_id,
                    'descripcion' => $linea->descripcion,
                    'debito' => $base,
                    'credito' => 0,
                ];
            }
            if ($impuesto > 0) {
                $lineasAsiento[] = [
                    'cuenta_id' => $cuentaItbmsId,
                    'descripcion' => "ITBMS {$etiqueta} {$factura->numero}",
                    'debito' => $impuesto,
                    'credito' => 0,
                ];
            }
            $lineasAsiento[] = [
                'cuenta_id' => $cuentaCxpId,
                'contacto_id' => $factura->proveedor_id,
                'descripcion' => "{$etiqueta} {$factura->numero}",
                'debito' => 0,
                'credito' => $total,
            ];
        }

        $asiento = app(AsientoAutomatico::class)->postear(
            $companiaId,
            $factura->fecha->format('Y-m-d'),
            "{$etiqueta} de compra {$factura->numero} — ".$factura->proveedor->nombre,
            $factura->numero,
            $lineasAsiento,
            'CXP',
            'cxp_documentos',
            $factura->id,
            $usuario,
        );

        $factura->update([
            'asiento_id' => $asiento->id,
            'estado' => CxpDocumento::ESTADO_PENDIENTE,
            'updated_by' => $usuario->email,
        ]);
    }

    /**
     * Postea el asiento de una compra al contado (pago directo desde banco/caja,
     * sin pasar por CXP) y deja la factura PAGADA. Debe llamarse dentro de una
     * transacción, con $factura->detalle y proveedor ya cargados.
     */
    private function contabilizarContado(CxpDocumento $factura, int $cuentaPagoId, $usuario): void
    {
        $companiaId = $factura->compania_id;
        $impuesto = round((float) $factura->impuesto, 2);

        $cuentaItbmsId = CuentaDefault::idPara($companiaId, 'ITBMS_CREDITO');

        if ($impuesto > 0 && ! $cuentaItbmsId) {
            throw ValidationException::withMessages([
                'documento' => 'La compañía no tiene configurada la cuenta default ITBMS_CREDITO; no se puede contabilizar el ITBMS de compras.',
            ]);
        }

        // Asiento: débito gasto/costo por línea, débito ITBMS crédito fiscal, crédito Banco/Caja
        $lineasAsiento = [];

        foreach ($factura->detalle as $linea) {
            $base = round((float) $linea->total_linea - (float) $linea->impuesto_monto, 2);
            $lineasAsiento[] = [
                'cuenta_id' => $linea->cuenta_id,
                'descripcion' => $linea->descripcion,
                'debito' => $base,
                'credito' => 0,
            ];
        }

        if ($impuesto > 0) {
            $lineasAsiento[] = [
                'cuenta_id' => $cuentaItbmsId,
                'descripcion' => "ITBMS factura {$factura->numero}",
                'debito' => $impuesto,
                'credito' => 0,
            ];
        }

        $lineasAsiento[] = [
            'cuenta_id' => $cuentaPagoId,
            'descripcion' => "Compra al contado {$factura->numero}",
            'debito' => 0,
            'credito' => round((float) $factura->total, 2),
        ];

        $asiento = app(AsientoAutomatico::class)->postear(
            $companiaId,
            $factura->fecha->format('Y-m-d'),
            "Compra al contado {$factura->numero} — ".$factura->proveedor->nombre,
            $factura->numero,
            $lineasAsiento,
            'CXP',
            'cxp_documentos',
            $factura->id,
            $usuario,
        );

        $factura->update([
            'asiento_id' => $asiento->id,
            'estado' => CxpDocumento::ESTADO_PAGADO,
            'updated_by' => $usuario->email,
        ]);
    }

    private function autorizarFactura(Request $request, CxpDocumento $documento): void
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless(in_array($documento->tipo_documento, CxpDocumento::tiposModulo(), true), 404);
    }

    public function importar(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario = $request->user();

        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']]);

        // Validación rápida del formato antes de encolar (evita jobs basura).
        $import = new CxpFacturasImport;
        Excel::import($import, $request->file('archivo'));

        if (empty($import->filas)) {
            return back()->withErrors(['archivo' => 'El archivo no contiene filas válidas o no tiene el formato esperado.']);
        }

        // Guarda el archivo y encola el procesamiento (consulta la DGI por CUFE,
        // que es lento) para mostrar barra de progreso sin bloquear la petición.
        // Conserva la extensión original: el job lee por ruta y PhpSpreadsheet
        // necesita la extensión para detectar el reader (store() puede no ponerla).
        $archivo = $request->file('archivo');
        $ext = strtolower($archivo->getClientOriginalExtension() ?: 'xlsx');
        $ruta = $archivo->storeAs('imports/cxp', \Illuminate\Support\Str::uuid().'.'.$ext);

        $importacion = CxpImportacion::create([
            'compania_id' => $companiaId,
            'usuario' => $usuario->email,
            'archivo' => $request->file('archivo')->getClientOriginalName(),
            'ruta' => $ruta,
            'estado' => CxpImportacion::ESTADO_PENDIENTE,
            'total' => count($import->filas),
        ]);

        ProcesarImportacionCxpFel::dispatch($importacion->id);

        return redirect()->route('admin.cxp.facturas.importar.progreso', $importacion);
    }

    public function importarProgreso(Request $request, CxpImportacion $importacion): View
    {
        abort_unless($importacion->compania_id === $this->companiaActivaId($request), 404);

        return view('admin.cxp.facturas.importar-progreso', compact('importacion'));
    }

    public function importarEstado(Request $request, CxpImportacion $importacion): JsonResponse
    {
        abort_unless($importacion->compania_id === $this->companiaActivaId($request), 404);

        return response()->json([
            'estado' => $importacion->estado,
            'total' => $importacion->total,
            'procesadas' => $importacion->procesadas,
            'creadas' => $importacion->creadas,
            'con_detalle' => $importacion->con_detalle,
            'omitidas' => $importacion->omitidas,
            'errores' => $importacion->errores ?? [],
            'mensaje_error' => $importacion->mensaje_error,
            'porcentaje' => $importacion->porcentaje(),
            'terminada' => $importacion->terminada(),
        ]);
    }

    public function anular(Request $request, CxpDocumento $documento): RedirectResponse
    {
        $this->autorizarFactura($request, $documento);

        if ($documento->esBorrador()) {
            return back()->withErrors(['documento' => 'La factura está en borrador (sin contabilizar); elimínala en lugar de anularla.']);
        }

        if ($documento->esAnulado()) {
            return back()->withErrors(['documento' => 'La factura ya está anulada.']);
        }

        if ($documento->aplicacionesComoDestino()->exists()) {
            return back()->withErrors(['documento' => 'La factura tiene pagos aplicados; anula primero los pagos.']);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($documento, $usuario) {
            app(AsientoAutomatico::class)->anular($documento->asiento, $usuario);

            $documento->update([
                'estado' => CxpDocumento::ESTADO_ANULADO,
                'saldo' => 0,
                'updated_by' => $usuario->email,
            ]);
        });

        return redirect()->route('admin.cxp.facturas.show', $documento)
            ->with('status', "Factura {$documento->numero} anulada.");
    }

    private function proveedores(int $companiaId)
    {
        return Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'PROVEEDOR'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'cuenta_gasto_id']);
    }

    /** Cuentas de movimiento (banco/caja) para pago al contado, igual que el módulo de Pagos. */
    private function cuentasPago(int $companiaId)
    {
        return CuentaContable::where('compania_id', $companiaId)
            ->where('activa', true)
            ->where('permite_movimiento', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);
    }
}
