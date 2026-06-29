<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\EmparejaContactos;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Exports\CxpComprasPlantillaExport;
use App\Exports\CxpSaldosInicialesPlantillaExport;
use App\Imports\CxpComprasGenericoImport;
use App\Imports\CxpSaldosInicialesImport;
use App\Imports\CxpFacturasImport;
use App\Jobs\ProcesarImportacionCxpFel;
use App\Models\Compania;
use App\Models\CompraOrden;
use App\Models\CompraOrdenDetalle;
use App\Models\Contacto;
use App\Services\CalculoDocumento;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\AfiActivo;
use App\Models\CxpAplicacion;
use App\Models\CxpDocumento;
use App\Models\CxpDocumentoDetalle;
use App\Models\CxpImportacion;
use App\Models\InvAlmacen;
use App\Models\InvExistencia;
use App\Models\ItemProducto;
use App\Models\TipoContacto;
use App\Models\TipoDocumento;
use App\Services\AdjuntoService;
use App\Services\AsientoAutomatico;
use App\Services\DgiFepConsulta;
use App\Services\InventarioCompras;
use App\Services\InventarioVentas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CxpFacturaController extends Controller
{
    use ConCompaniaActiva;
    use EmparejaContactos;
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
            'cuentasApertura' => CuentaContable::where('compania_id', $companiaId)
                ->where('permite_movimiento', true)
                ->where('activa', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
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
            'cuentaInventarioId' => CuentaDefault::idPara($companiaId, 'INVENTARIO'),
            'articulos' => $this->articulosCompra($companiaId),
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
        [$lineas, $subtotal, $impuesto, $total, $descuentoDoc] = $this->calcularLineas($data['lineas'], (float) ($data['descuento_general'] ?? 0));

        $tipo = $data['tipo_documento'];

        // Solo los documentos tipo factura (factura/reembolso/importación) se
        // pueden registrar al contado; las notas de crédito y débito siempre
        // nacen como borrador a crédito y se contabilizan aparte.
        $formaPago = $data['forma_pago'] ?? 'CREDITO';
        $contado = in_array($tipo, CxpDocumento::tiposFacturaCargo(), true)
            && in_array($formaPago, ['CONTADO', 'TARJETA'], true);

        // Vencimiento: contado vence el mismo día; a crédito usa la fecha indicada
        // o, si no se indicó, fecha + días de crédito del proveedor (default 30).
        if ($contado) {
            $vencimiento = $data['fecha'];
        } elseif (! empty($data['fecha_vencimiento'])) {
            $vencimiento = $data['fecha_vencimiento'];
        } else {
            $proveedor = Contacto::where('compania_id', $companiaId)->find($data['proveedor_id']);
            $dias = (int) ($proveedor?->dias_credito ?: 30);
            $vencimiento = \Carbon\Carbon::parse($data['fecha'])->addDays($dias)->format('Y-m-d');
        }

        $factura = DB::transaction(function () use ($companiaId, $data, $tipo, $lineas, $subtotal, $impuesto, $total, $usuario, $contado, $formaPago, $vencimiento, $descuentoDoc) {
            $factura = CxpDocumento::create([
                'compania_id' => $companiaId,
                'proveedor_id' => $data['proveedor_id'],
                'tipo_documento' => $tipo,
                'numero' => $data['numero'],
                'fecha' => $data['fecha'],
                // En contado no hay crédito pendiente: vence el mismo día.
                'fecha_vencimiento' => $vencimiento,
                'subtotal' => $subtotal,
                'descuento' => $descuentoDoc,
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
                $this->contabilizarContado($factura, (int) $data['cuenta_pago_id'], $usuario, $formaPago);
            }

            return $factura;
        });

        $etiqueta = TipoDocumento::descripcion(TipoDocumento::AUX_CXP, $tipo);

        $labelPago = match ($formaPago) {
            'TARJETA' => 'con tarjeta',
            'CONTADO' => 'al contado',
            default   => '',
        };

        $mensaje = $contado
            ? "Compra {$labelPago} {$factura->numero} registrada y contabilizada. Asiento {$factura->fresh()->asiento->numero}."
            : "{$etiqueta} {$factura->numero} guardada como borrador. Revísala y contabilízala cuando esté lista.";

        return redirect()->route('admin.cxp.facturas.show', $factura)->with('status', $mensaje);
    }

    public function show(Request $request, CxpDocumento $documento): View
    {
        $this->autorizarFactura($request, $documento);

        // Espeja (idempotente) el archivo_path legado a core_adjuntos para que el
        // bloque de adjuntos también muestre la foto/PDF guardado por el flujo viejo.
        $this->espejarArchivoEnAdjuntos($documento);

        $documento->load(['proveedor', 'detalle.cuenta.tipo', 'asiento', 'aplicacionesComoDestino.origen', 'compraOrden', 'adjuntos']);

        $activosPorDetalle = AfiActivo::whereIn('cxp_detalle_id', $documento->detalle->pluck('id'))
            ->get(['id', 'codigo', 'descripcion', 'cxp_detalle_id'])
            ->keyBy('cxp_detalle_id');

        // Vista previa del asiento: solo en borrador (una vez contabilizado ya
        // existe el asiento real y el enlace "Ver asiento"). Reutiliza la misma
        // construcción de líneas que el posteo, así no puede divergir.
        $previewAsiento = $documento->esBorrador()
            ? $this->previsualizarAsiento($documento)
            : null;

        // ¿Se puede ofrecer "Devolver al proveedor"? Factura contabilizada (cargo,
        // no abono) con saldo pendiente y al menos un producto inventariable.
        $puedeDevolver = $this->validarDevolucion($documento) === null
            && ! empty($this->lineasDevolubles($documento, $this->almacenDevolucion($documento->compania_id)));

        return view('admin.cxp.facturas.show', [
            'factura'            => $documento,
            'activosPorDetalle'  => $activosPorDetalle,
            'puedeGestionarAdjuntos' => $request->user()->can('cxp.gestionar'),
            'previewAsiento'     => $previewAsiento,
            'puedeDevolver'      => $puedeDevolver,
        ]);
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
            'cuentaInventarioId' => CuentaDefault::idPara($companiaId, 'INVENTARIO'),
            'articulos' => $this->articulosCompra($companiaId),
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
        [$lineas, $subtotal, $impuesto, $total, $descuentoDoc] = $this->calcularLineas($data['lineas'], (float) ($data['descuento_general'] ?? 0));

        if (! empty($data['fecha_vencimiento'])) {
            $vencimiento = $data['fecha_vencimiento'];
        } else {
            $proveedor = Contacto::where('compania_id', $companiaId)->find($data['proveedor_id']);
            $dias = (int) ($proveedor?->dias_credito ?: 30);
            $vencimiento = \Carbon\Carbon::parse($data['fecha'])->addDays($dias)->format('Y-m-d');
        }

        DB::transaction(function () use ($documento, $data, $lineas, $subtotal, $impuesto, $total, $usuario, $vencimiento, $descuentoDoc) {
            $documento->update([
                'proveedor_id' => $data['proveedor_id'],
                'numero' => $data['numero'],
                'fecha' => $data['fecha'],
                'fecha_vencimiento' => $vencimiento,
                'subtotal' => $subtotal,
                'descuento' => $descuentoDoc,
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
            'forma_pago' => ['nullable', Rule::in(['CREDITO', 'CONTADO', 'TARJETA'])],
            'cuenta_pago_id' => [
                Rule::requiredIf(fn () => in_array($request->input('forma_pago'), ['CONTADO', 'TARJETA'])),
                'nullable', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
            'descuento_general' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.item_id' => [
                'nullable', 'integer',
                Rule::exists('item_productos_servicios', 'id')->where('compania_id', $companiaId),
            ],
            'lineas.*.descripcion' => ['required', 'string', 'max:500'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'gte:0', 'max:999999999'],
            'lineas.*.descuento' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
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

        // Una factura ANULADA no bloquea el número: el índice único de BD es
        // parcial (excluye ANULADO), así se puede re-registrar/clonar con el
        // mismo número. La validación de la app debe respetar esa misma regla.
        $duplicada = CxpDocumento::where('compania_id', $companiaId)
            ->where('proveedor_id', $data['proveedor_id'])
            ->where('tipo_documento', $tipo)
            ->where('numero', $data['numero'])
            ->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)
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
    private function calcularLineas(array $lineasInput, float $descuentoGeneral = 0.0): array
    {
        $entradas = [];
        foreach (array_values($lineasInput) as $linea) {
            $entradas[] = [
                'item_id'         => ! empty($linea['item_id']) ? (int) $linea['item_id'] : null,
                'descripcion'     => $linea['descripcion'],
                'cantidad'        => $linea['cantidad'],
                'precio_unitario' => $linea['precio_unitario'],
                'descuento'       => $linea['descuento'] ?? 0,
                'cuenta_id'       => (int) $linea['cuenta_id'],
                'tasa'            => (float) ((int) $linea['tasa_itbms']),
            ];
        }

        $calc = CalculoDocumento::calcular($entradas, $descuentoGeneral);

        if ($calc['total'] <= 0) {
            throw ValidationException::withMessages(['lineas' => 'El total de la factura debe ser mayor que cero.']);
        }

        $lineas = [];
        foreach ($calc['lineas'] as $l) {
            $lineas[] = [
                'linea'           => $l['linea'],
                'item_id'         => $l['item_id'],
                'descripcion'     => $l['descripcion'],
                'cantidad'        => $l['cantidad'],
                'precio_unitario' => $l['precio_unitario'],
                'descuento'       => $l['descuento'],
                'impuesto_monto'  => $l['impuesto_monto'],
                'total_linea'     => $l['total_linea'],
                'cuenta_id'       => $l['cuenta_id'],
            ];
        }

        return [$lineas, $calc['subtotal'], $calc['itbms'], $calc['total'], $calc['descuento']];
    }

    /**
     * Postea el asiento de una factura en borrador y la deja en estado PENDIENTE.
     * Debe llamarse dentro de una transacción.
     */
    private function contabilizarFactura(CxpDocumento $factura, $usuario): void
    {
        $companiaId = $factura->compania_id;
        $etiqueta = $factura->etiquetaTipo();

        $lineasAsiento = $this->construirLineasAsiento($factura);

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

        // Sube existencias de las líneas que apuntan a un artículo de inventario.
        $this->registrarEntradasInventario($factura, $usuario);
    }

    /**
     * Construye las líneas del asiento de una factura/NC/ND de compra a partir
     * de su detalle. Es la FUENTE ÚNICA de la dirección contable: la usan tanto
     * la contabilización real ({@see contabilizarFactura}) como la vista previa
     * del asiento en el borrador ({@see show}), de modo que la previsualización
     * nunca difiera del asiento que finalmente se postea.
     *
     * Lanza ValidationException si faltan las cuentas default necesarias (CXP /
     * ITBMS_CREDITO); el llamador de la vista previa lo captura para avisar en
     * lugar de fallar.
     *
     * @return array<int, array{cuenta_id:int, contacto_id?:int, descripcion:string, debito:float, credito:float}>
     */
    private function construirLineasAsiento(CxpDocumento $factura): array
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

        return $lineasAsiento;
    }

    /**
     * Arma la vista previa legible del asiento de un borrador (cuenta con
     * código/nombre, contacto, débito/crédito) reutilizando exactamente las
     * líneas que se postearían. Devuelve ['error' => mensaje] si faltan cuentas
     * default, o ['lineas' => [...], 'total_debito' => x, 'total_credito' => x].
     *
     * @return array{lineas?: array<int, array<string, mixed>>, total_debito?: float, total_credito?: float, error?: string}
     */
    private function previsualizarAsiento(CxpDocumento $factura): array
    {
        try {
            $lineas = $this->construirLineasAsiento($factura);
        } catch (ValidationException $e) {
            return ['error' => collect($e->errors())->flatten()->first()];
        }

        $cuentas = CuentaContable::whereIn('id', collect($lineas)->pluck('cuenta_id')->unique())
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy('id');

        $proveedor = $factura->proveedor->nombre ?? null;

        $filas = collect($lineas)->map(function (array $l) use ($cuentas, $proveedor) {
            $cuenta = $cuentas->get($l['cuenta_id']);

            return [
                'cuenta'      => $cuenta ? $cuenta->codigo.' — '.$cuenta->nombre : '(cuenta #'.$l['cuenta_id'].')',
                'contacto'    => ! empty($l['contacto_id']) ? $proveedor : null,
                'descripcion' => $l['descripcion'] ?? '',
                'debito'      => round((float) $l['debito'], 2),
                'credito'     => round((float) $l['credito'], 2),
            ];
        });

        return [
            'lineas'        => $filas->all(),
            'total_debito'  => round($filas->sum('debito'), 2),
            'total_credito' => round($filas->sum('credito'), 2),
        ];
    }

    /**
     * Postea el asiento de una compra pagada de inmediato (contado o tarjeta,
     * sin pasar por CXP) y deja la factura PAGADA. Debe llamarse dentro de una
     * transacción, con $factura->detalle y proveedor ya cargados.
     */
    private function contabilizarContado(CxpDocumento $factura, int $cuentaPagoId, $usuario, string $formaPago = 'CONTADO'): void
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

        $labelPago = $formaPago === 'TARJETA' ? 'con tarjeta' : 'al contado';

        $lineasAsiento[] = [
            'cuenta_id' => $cuentaPagoId,
            'descripcion' => "Compra {$labelPago} {$factura->numero}",
            'debito' => 0,
            'credito' => round((float) $factura->total, 2),
        ];

        $asiento = app(AsientoAutomatico::class)->postear(
            $companiaId,
            $factura->fecha->format('Y-m-d'),
            "Compra {$labelPago} {$factura->numero} — ".$factura->proveedor->nombre,
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

        // Sube existencias de las líneas que apuntan a un artículo de inventario.
        $this->registrarEntradasInventario($factura, $usuario);
    }

    /**
     * Sube a inventario las líneas de la factura que apuntan a un artículo de
     * tipo PRODUCTO. La contabilidad ya la lleva el asiento de la factura
     * (Dr Inventario / Cr CxP o Banco), así que el movimiento de inventario NO
     * postea asiento propio: solo enlaza al documento para kárdex/trazabilidad.
     *
     * Solo aplica a documentos de cargo (factura/ND/reembolso/importación); las
     * notas de crédito (abonos) no mueven stock. Debe llamarse dentro de la
     * transacción de contabilización, con $factura->detalle ya cargado.
     */
    private function registrarEntradasInventario(CxpDocumento $factura, $usuario): void
    {
        if ($factura->esAbono()) {
            return;
        }

        $itemIds = $factura->detalle->pluck('item_id')->filter()->unique();
        if ($itemIds->isEmpty()) {
            return;
        }

        $items = ItemProducto::whereIn('id', $itemIds)
            ->where('compania_id', $factura->compania_id)
            ->get(['id', 'tipo'])
            ->keyBy('id');

        $entradas = [];
        foreach ($factura->detalle as $linea) {
            if (! $linea->item_id) {
                continue;
            }
            $item = $items->get($linea->item_id);
            if (! $item || $item->tipo !== ItemProducto::TIPO_PRODUCTO) {
                continue;
            }
            $cantidad = (float) $linea->cantidad;
            if ($cantidad <= 0) {
                continue;
            }
            // Costo = base sin ITBMS (el crédito fiscal no es costo del inventario).
            $base = round((float) $linea->total_linea - (float) $linea->impuesto_monto, 2);
            $entradas[] = [
                'item_id'        => (int) $linea->item_id,
                'cantidad'       => $cantidad,
                'costo_unitario' => round($base / $cantidad, 4),
            ];
        }

        if (empty($entradas)) {
            return;
        }

        // Almacén destino: el primer almacén activo de la compañía. Sin almacén
        // no se mueve stock (la contabilidad ya quedó registrada en el asiento).
        $almacenId = InvAlmacen::where('compania_id', $factura->compania_id)
            ->where('activo', true)
            ->orderBy('codigo')
            ->value('id');

        if (! $almacenId) {
            return;
        }

        app(InventarioCompras::class)->registrarEntrada(
            $factura->compania_id,
            $almacenId,
            $factura->fecha->format('Y-m-d'),
            $entradas,
            $factura->asiento_id,
            'cxp_documentos',
            $factura->id,
            $usuario,
        );
    }

    /**
     * Almacén de la devolución: el primer almacén activo (misma convención con que
     * el inventario ENTRÓ al facturar/recepcionar). null si la compañía no tiene.
     */
    private function almacenDevolucion(int $companiaId): ?int
    {
        return InvAlmacen::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('codigo')
            ->value('id');
    }

    /**
     * Verifica que la factura admita devolución de inventario. Devuelve un mensaje
     * de error o null si procede. Solo cargos contabilizados (con o sin saldo):
     * con saldo se reduce el saldo por pagar; ya pagada genera crédito a favor.
     */
    private function validarDevolucion(CxpDocumento $documento): ?string
    {
        if ($documento->esBorrador()) {
            return 'La factura está en borrador; contabilízala antes de devolver.';
        }
        if ($documento->esAnulado()) {
            return 'La factura está anulada.';
        }
        if ($documento->esAbono()) {
            return 'Una nota de crédito no admite devolución de inventario.';
        }

        return null;
    }

    /**
     * Líneas de producto de la factura que pueden devolverse, con su costo de
     * compra, ITBMS de la línea, existencia actual y el máximo devolvible
     * (min entre lo comprado y lo disponible en stock).
     *
     * @return array<int, array<string, mixed>>
     */
    private function lineasDevolubles(CxpDocumento $documento, ?int $almacenId): array
    {
        if (! $almacenId) {
            return [];
        }

        $documento->loadMissing('detalle');
        $itemIds = $documento->detalle->pluck('item_id')->filter()->unique();
        if ($itemIds->isEmpty()) {
            return [];
        }

        $items = ItemProducto::whereIn('id', $itemIds)
            ->where('compania_id', $documento->compania_id)
            ->get(['id', 'codigo', 'nombre', 'tipo', 'cuenta_inventario_id'])
            ->keyBy('id');

        $existencias = InvExistencia::where('almacen_id', $almacenId)
            ->whereIn('item_id', $itemIds)
            ->get(['item_id', 'cantidad', 'costo_promedio'])
            ->keyBy('item_id');

        $lineas = [];
        foreach ($documento->detalle as $d) {
            if (! $d->item_id) {
                continue;
            }
            $item = $items->get($d->item_id);
            if (! $item || $item->tipo !== ItemProducto::TIPO_PRODUCTO) {
                continue;
            }
            $cantidad = (float) $d->cantidad;
            if ($cantidad <= 0) {
                continue;
            }
            $base = round((float) $d->total_linea - (float) $d->impuesto_monto, 2);
            $existencia = $existencias->get($d->item_id);
            $disponible = (float) ($existencia->cantidad ?? 0);
            $maxDevolver = round(min($cantidad, max(0.0, $disponible)), 4);
            if ($maxDevolver <= 0) {
                continue;
            }

            $lineas[] = [
                'detalle_id'           => (int) $d->id,
                'item_id'              => (int) $d->item_id,
                'codigo'               => $item->codigo,
                'nombre'               => $item->nombre,
                'descripcion'          => $d->descripcion,
                'cantidad'             => $cantidad,
                'costo_compra'         => round($base / $cantidad, 4),
                'itbms_linea'          => round((float) $d->impuesto_monto, 2),
                'disponible'           => $disponible,
                'max_devolver'         => $maxDevolver,
                'costo_promedio'       => (float) ($existencia->costo_promedio ?? round($base / $cantidad, 4)),
                'cuenta_inventario_id' => $item->cuenta_inventario_id,
            ];
        }

        return $lineas;
    }

    /** Formulario guiado de devolución al proveedor (NC de CxP que devuelve stock). */
    public function devolucionForm(Request $request, CxpDocumento $documento): View|RedirectResponse
    {
        $this->autorizarFactura($request, $documento);

        if ($err = $this->validarDevolucion($documento)) {
            return redirect()->route('admin.cxp.facturas.show', $documento)->withErrors(['documento' => $err]);
        }

        $almacenId = $this->almacenDevolucion($documento->compania_id);
        $lineas = $this->lineasDevolubles($documento, $almacenId);

        if (empty($lineas)) {
            return redirect()->route('admin.cxp.facturas.show', $documento)
                ->withErrors(['documento' => 'Esta factura no tiene productos inventariables con existencia para devolver.']);
        }

        return view('admin.cxp.facturas.devolucion', [
            'factura' => $documento->load('proveedor'),
            'lineas'  => $lineas,
            'almacen' => InvAlmacen::find($almacenId),
        ]);
    }

    /**
     * Registra la devolución: crea una NOTA DE CRÉDITO de CxP aplicada a la factura
     * (reduce el saldo por pagar) y mueve el inventario hacia AFUERA.
     *
     * Contabilidad (costeo promedio): Dr CxP (precio de compra + ITBMS, lo que el
     * proveedor acredita) / Cr Inventario (al costo PROMEDIO vigente) / Cr ITBMS
     * crédito / y la diferencia precio−promedio a GASTO_DEFAULT (variación de costo).
     * Así el crédito a Inventario == baja de existencia (kárdex ≡ mayor).
     */
    public function devolucionStore(Request $request, CxpDocumento $documento): RedirectResponse
    {
        $this->autorizarFactura($request, $documento);
        $companiaId = $documento->compania_id;
        $usuario = $request->user();

        if ($err = $this->validarDevolucion($documento)) {
            return redirect()->route('admin.cxp.facturas.show', $documento)->withErrors(['documento' => $err]);
        }

        $data = $request->validate([
            'fecha'               => ['required', 'date'],
            'lineas'              => ['required', 'array', 'min:1'],
            'lineas.*.detalle_id' => ['required', 'integer'],
            'lineas.*.cantidad'   => ['nullable', 'numeric', 'min:0'],
        ]);

        $almacenId = $this->almacenDevolucion($companiaId);
        if (! $almacenId) {
            return back()->withErrors(['documento' => 'La compañía no tiene un almacén activo para mover el inventario.']);
        }

        $disponibles = collect($this->lineasDevolubles($documento, $almacenId))->keyBy('detalle_id');

        // Filtra y valida lo que se devuelve.
        $aDevolver = [];
        foreach ($data['lineas'] as $l) {
            $qty = round((float) ($l['cantidad'] ?? 0), 4);
            if ($qty <= 0) {
                continue;
            }
            $info = $disponibles->get((int) $l['detalle_id']);
            if (! $info) {
                throw ValidationException::withMessages(['lineas' => 'Una de las líneas no es devolvible.']);
            }
            if ($qty > $info['max_devolver'] + 0.0001) {
                throw ValidationException::withMessages([
                    'lineas' => "No puede devolver {$qty} de «{$info['nombre']}»: el máximo es {$info['max_devolver']} (entre lo comprado y la existencia).",
                ]);
            }
            $aDevolver[] = $info + ['devolver' => $qty];
        }

        if (empty($aDevolver)) {
            return back()->withErrors(['lineas' => 'Indique al menos una cantidad a devolver.']);
        }

        $cuentaCxpId   = CuentaDefault::idPara($companiaId, 'CXP');
        $cuentaItbmsId = CuentaDefault::idPara($companiaId, 'ITBMS_CREDITO');
        $cuentaInvDef  = CuentaDefault::idPara($companiaId, 'INVENTARIO');
        $cuentaGastoId = CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT');

        if (! $cuentaCxpId) {
            throw ValidationException::withMessages(['documento' => 'La compañía no tiene configurada la cuenta default CXP.']);
        }

        // Totales: base (precio de compra), ITBMS proporcional, inventario (promedio).
        $totalBase = 0.0;
        $totalItbms = 0.0;
        $totalInv = 0.0;
        $invPorCuenta = [];
        foreach ($aDevolver as $d) {
            $base  = round($d['costo_compra'] * $d['devolver'], 2);
            $itbms = $d['cantidad'] > 0 ? round($d['itbms_linea'] * $d['devolver'] / $d['cantidad'], 2) : 0.0;
            $inv   = round($d['costo_promedio'] * $d['devolver'], 2);
            $cuentaInv = $d['cuenta_inventario_id'] ?? $cuentaInvDef;
            if (! $cuentaInv) {
                throw ValidationException::withMessages(['documento' => 'Falta la cuenta de inventario del ítem «'.$d['nombre'].'» y no hay default INVENTARIO.']);
            }
            $totalBase += $base;
            $totalItbms += $itbms;
            $totalInv += $inv;
            $invPorCuenta[$cuentaInv] = round(($invPorCuenta[$cuentaInv] ?? 0) + $inv, 2);
        }
        $totalBase = round($totalBase, 2);
        $totalItbms = round($totalItbms, 2);
        $totalInv = round($totalInv, 2);
        $total = round($totalBase + $totalItbms, 2);
        $variacion = round($totalBase - $totalInv, 2);

        if ($totalItbms > 0 && ! $cuentaItbmsId) {
            throw ValidationException::withMessages(['documento' => 'La compañía no tiene configurada la cuenta default ITBMS_CREDITO.']);
        }
        if (abs($variacion) > 0.004 && ! $cuentaGastoId) {
            throw ValidationException::withMessages(['documento' => 'La devolución tiene diferencia entre el precio de compra y el costo promedio, y falta la cuenta default GASTO_DEFAULT para registrarla.']);
        }

        $nota = DB::transaction(function () use ($documento, $companiaId, $usuario, $data, $aDevolver, $almacenId, $cuentaCxpId, $cuentaItbmsId, $cuentaGastoId, $invPorCuenta, $totalBase, $totalItbms, $totalInv, $total, $variacion) {
            // Bloquea la factura para leer su saldo sin carrera. Se aplica hasta el
            // saldo pendiente; el remanente (típico de una factura ya pagada) queda
            // como CRÉDITO A FAVOR del proveedor (saldo de la NC, aplicable en pagos).
            $factura = CxpDocumento::whereKey($documento->id)->lockForUpdate()->first();
            $saldoFactura = round((float) $factura->saldo, 2);
            $aplicado = round(min($total, $saldoFactura), 2);
            $remanente = round($total - $aplicado, 2);

            $nota = CxpDocumento::create([
                'compania_id'    => $companiaId,
                'proveedor_id'   => $documento->proveedor_id,
                'tipo_documento' => CxpDocumento::TIPO_NOTA_CREDITO,
                'numero'         => CxpDocumento::siguienteNumeroNota($companiaId, CxpDocumento::TIPO_NOTA_CREDITO),
                'fecha'          => $data['fecha'],
                'subtotal'       => $totalBase,
                'descuento'      => 0,
                'impuesto'       => $totalItbms,
                'total'          => $total,
                'saldo'          => $remanente,
                'estado'         => $remanente > 0.004 ? CxpDocumento::ESTADO_PENDIENTE : CxpDocumento::ESTADO_PAGADO,
                'created_by'     => $usuario->email,
            ]);

            // Dr CxP (total) / Cr Inventario (promedio, por cuenta) / Cr ITBMS / variación.
            $lineas = [[
                'cuenta_id'   => $cuentaCxpId,
                'contacto_id' => $documento->proveedor_id,
                'descripcion' => "Devolución (NC {$nota->numero}) — factura {$documento->numero}",
                'debito'      => $total,
                'credito'     => 0,
            ]];
            foreach ($invPorCuenta as $cuentaInv => $monto) {
                if ($monto > 0) {
                    $lineas[] = ['cuenta_id' => (int) $cuentaInv, 'descripcion' => 'Inventario devuelto', 'debito' => 0, 'credito' => $monto];
                }
            }
            if ($totalItbms > 0) {
                $lineas[] = ['cuenta_id' => $cuentaItbmsId, 'descripcion' => "ITBMS devolución {$nota->numero}", 'debito' => 0, 'credito' => $totalItbms];
            }
            if (abs($variacion) > 0.004) {
                $lineas[] = $variacion > 0
                    ? ['cuenta_id' => $cuentaGastoId, 'descripcion' => 'Diferencia de costo en devolución', 'debito' => 0, 'credito' => $variacion]
                    : ['cuenta_id' => $cuentaGastoId, 'descripcion' => 'Diferencia de costo en devolución', 'debito' => abs($variacion), 'credito' => 0];
            }

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId, $data['fecha'],
                "Devolución de compra {$nota->numero} — ".($documento->proveedor->nombre ?? '')." (factura {$documento->numero})",
                $nota->numero, $lineas, 'CXP', 'cxp_documentos', $nota->id, $usuario,
            );
            $nota->update(['asiento_id' => $asiento->id]);

            // Aplica a la factura solo lo que cubre su saldo pendiente (si lo hay).
            if ($aplicado > 0.004) {
                CxpAplicacion::create([
                    'compania_id'         => $companiaId,
                    'proveedor_id'        => $documento->proveedor_id,
                    'documento_origen_id' => $nota->id,
                    'documento_destino_id' => $documento->id,
                    'fecha'               => $data['fecha'],
                    'monto_aplicado'      => $aplicado,
                    'created_by'          => $usuario->email,
                ]);
                $factura->saldo = round($saldoFactura - $aplicado, 2);
                $factura->estado = $factura->estadoSegunSaldo();
                $factura->updated_by = $usuario->email;
                $factura->save();
            }

            // Saca el inventario al costo PROMEDIO (sin asiento propio: ya va en el de la NC).
            $detalleInv = array_map(fn ($d) => [
                'item_id'        => $d['item_id'],
                'cantidad'       => $d['devolver'],
                'costo_unitario' => $d['costo_promedio'],
            ], $aDevolver);

            app(InventarioVentas::class)->registrar(
                $companiaId, $almacenId, $data['fecha'], $detalleInv,
                $asiento->id, 'cxp_documentos', $nota->id, $usuario,
            );

            return $nota;
        });

        $saldoNota = round((float) $nota->saldo, 2);
        $mensaje = $saldoNota > 0
            ? "Devolución registrada: nota de crédito {$nota->numero} (crédito a favor de B/. ".number_format($saldoNota, 2)." disponible para futuros pagos al proveedor); inventario descontado."
            : "Devolución registrada: nota de crédito {$nota->numero} aplicada a {$documento->numero}; inventario y CxP actualizados.";

        return redirect()->route('admin.cxp.notas.show', $nota)->with('status', $mensaje);
    }

    /** Artículos activos de la compañía para el combobox de líneas de compra. */
    private function articulosCompra(int $companiaId)
    {
        return ItemProducto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'descripcion', 'tipo', 'costo', 'cuenta_inventario_id', 'cuenta_gasto_id']);
    }

    private function autorizarFactura(Request $request, CxpDocumento $documento): void
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless(in_array($documento->tipo_documento, CxpDocumento::tiposModulo(), true), 404);
    }

    public function desdeCufeForm(Request $request): View
    {
        return view('admin.cxp.facturas.desde-cufe');
    }

    public function consultarCufe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'qr' => ['required', 'string', 'max:4000'],
        ]);
        $companiaId = $this->companiaActivaId($request);

        $r = app(DgiFepConsulta::class)->porQr(trim($data['qr']));

        // Deduplicar por el CUFE extraído del QR (si lo hay).
        $cufe = $r['cufe'] ?? null;
        if ($cufe) {
            $existente = CxpDocumento::where('compania_id', $companiaId)->where('cufe', $cufe)->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)->first();
            if ($existente) {
                return response()->json([
                    'ya_registrada' => true,
                    'numero'        => $existente->numero,
                    'id'            => $existente->id,
                    'url'           => route('admin.cxp.facturas.show', $existente),
                ]);
            }
        }

        if (! $r['ok']) {
            return response()->json([
                'error'  => $r['mensaje'] ?? 'No se pudo obtener la factura de la DGI.',
                'motivo' => $r['motivo'] ?? 'error',
            ], 422);
        }

        return response()->json(array_merge($r['factura'], ['cufe' => $cufe]));
    }

    /**
     * Respaldo cuando el QR no se puede leer: la IA (visión de Claude) lee la foto
     * de la factura. Primero intenta el CUFE → DGI (datos oficiales); si la DGI no
     * la encuentra (o no hay CUFE legible), la IA extrae los datos de la factura.
     */
    public function cufeDesdeFoto(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'image', 'max:10240'],
        ]);

        $apiKey = config('services.anthropic.key');
        if (! $apiKey) {
            return response()->json(['error' => 'La lectura con IA no está configurada (falta ANTHROPIC_API_KEY).'], 422);
        }

        $companiaId = $this->companiaActivaId($request);
        $foto  = $request->file('foto');
        $raw   = (string) file_get_contents($foto->getRealPath());
        $media = $foto->getMimeType() ?: 'image/jpeg';

        // Guarda el original (best-effort) antes de reducir.
        $adj     = $this->guardarAdjuntoCxp($raw, $this->extDeMime($media), $companiaId);
        $adjResp = $adj ? ['archivo_path' => $adj['path'], 'archivo_disk' => $adj['disk']] : [];

        // Reduce la foto para la IA (↓ tokens, ↓ costo, ↓ latencia).
        ['bytes' => $bytes, 'media' => $media] = $this->reducirFoto($raw, $media);
        $b64 = base64_encode($bytes);

        // 1) Intentar leer el CUFE y consultar la DGI (datos oficiales).
        try {
            $promptCufe = 'Esta es una factura electrónica de Panamá (DGI/FEP). '
                ."Busca el CUFE, también llamado \"Código Único de Factura Electrónica\". "
                .'Es un código de 66 caracteres alfanuméricos con guiones que empieza con "FE". '
                .'Devuelve ÚNICAMENTE el CUFE, sin texto adicional, sin espacios ni saltos de línea. '
                .'Si no lo encuentras con claridad, responde exactamente: NONE';

            $texto = $this->visionClaude($apiKey, $b64, $media, $promptCufe, 200);
            $cufe  = preg_replace('/[^A-Za-z0-9\-]/', '', trim($texto));

            if ($cufe !== '' && stripos($texto, 'NONE') !== 0 && strlen($cufe) >= 40) {
                $existente = CxpDocumento::where('compania_id', $companiaId)->where('cufe', $cufe)->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)->first();
                if ($existente) {
                    return response()->json([
                        'ya_registrada' => true,
                        'numero'        => $existente->numero,
                        'id'            => $existente->id,
                        'url'           => route('admin.cxp.facturas.show', $existente),
                    ]);
                }

                $r = app(DgiFepConsulta::class)->porQr($cufe);
                if ($r['ok']) {
                    return response()->json(array_merge($r['factura'], ['cufe' => $r['cufe'] ?? $cufe, 'via' => 'dgi'], $adjResp));
                }
                // CUFE leído pero la DGI no la trajo → caer al respaldo de datos por IA.
            }

            // 2) Respaldo: la IA extrae todos los datos de la factura desde la foto.
            $datos = $this->datosFacturaIa($apiKey, $b64, $media);
            if (! $datos || empty($datos['lineas'])) {
                return response()->json([
                    'error'  => 'La IA no pudo leer los datos de la factura. Toma una foto más nítida y completa, o ingrésala manualmente.',
                    'motivo' => 'sin_datos',
                ], 422);
            }

            $dgi = $this->normalizarDatosIa($datos);

            // Dedup por CUFE si la IA lo logró leer.
            if ($dgi['cufe'] && $existente = CxpDocumento::where('compania_id', $companiaId)->where('cufe', $dgi['cufe'])->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)->first()) {
                return response()->json([
                    'ya_registrada' => true,
                    'numero'        => $existente->numero,
                    'id'            => $existente->id,
                    'url'           => route('admin.cxp.facturas.show', $existente),
                ]);
            }

            // Devuelve los datos para previsualizar y registrar (vía datos_ia).
            return response()->json(array_merge($dgi, [
                'via'      => 'ia',
                'datos_ia' => json_encode($dgi),
            ], $adjResp));
        } catch (Throwable $e) {
            Log::error('CUFE-IA: excepción', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Error al leer la factura con IA: '.$e->getMessage()], 422);
        }
    }

    /** Guarda bytes en el disco de adjuntos (S3). Best-effort: null si falla. */
    private function guardarAdjuntoCxp(string $bytes, string $ext, int $companiaId): ?array
    {
        try {
            $disk = config('filesystems.adjuntos', 's3');
            $path = 'cxp/'.$companiaId.'/'.Str::uuid().'.'.$ext;

            if (Storage::disk($disk)->put($path, $bytes)) {
                return ['path' => $path, 'disk' => $disk];
            }
        } catch (Throwable $e) {
            Log::warning('Adjunto CxP no se pudo guardar', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function extDeMime(string $mime): string
    {
        return match ($mime) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'application/pdf' => 'pdf',
            default      => 'jpg',
        };
    }

    /** Sirve la factura física: archivo guardado (foto/PDF) o el PDF oficial de la DGI al vuelo. */
    public function archivo(Request $request, CxpDocumento $factura): Response|StreamedResponse
    {
        abort_unless($factura->compania_id === $this->companiaActivaId($request), 404);

        // 1) Archivo ya guardado (foto subida por IA o PDF cacheado).
        if ($factura->archivo_path) {
            $disk = $factura->archivo_disk ?: config('filesystems.adjuntos', 's3');
            if (Storage::disk($disk)->exists($factura->archivo_path)) {
                return Storage::disk($disk)->response($factura->archivo_path);
            }
        }

        // 2) Sin archivo pero con CUFE: descargar el PDF oficial de la DGI al vuelo
        //    y cachearlo en S3 para la próxima vez (best-effort).
        if ($factura->cufe && strlen($factura->cufe) === 66) {
            $pdf = app(DgiFepConsulta::class)->pdfPorCufe($factura->cufe);
            if ($pdf) {
                if ($adj = $this->guardarAdjuntoCxp($pdf, 'pdf', $factura->compania_id)) {
                    $factura->update(['archivo_path' => $adj['path'], 'archivo_disk' => $adj['disk']]);
                }

                return response($pdf, 200, [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="factura-'.$factura->numero.'.pdf"',
                ]);
            }
        }

        abort(404, 'No hay factura física disponible para este documento.');
    }

    /**
     * Reduce la imagen a máx 1 600 px y recomprime como JPEG 85 antes de enviarla a la IA.
     * Mantiene el original sin tocar; si GD no está disponible o la imagen ya es pequeña,
     * devuelve los bytes originales.
     *
     * @return array{bytes: string, media: string}
     */
    private function reducirFoto(string $bytes, string $media): array
    {
        if (! function_exists('imagecreatefromstring')) {
            return ['bytes' => $bytes, 'media' => $media];
        }

        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return ['bytes' => $bytes, 'media' => $media];
        }

        $w      = imagesx($img);
        $h      = imagesy($img);
        $maxDim = 1600;

        if ($w <= $maxDim && $h <= $maxDim) {
            imagedestroy($img);

            return ['bytes' => $bytes, 'media' => $media];
        }

        if ($w >= $h) {
            $nw = $maxDim;
            $nh = (int) round($h * $maxDim / $w);
        } else {
            $nh = $maxDim;
            $nw = (int) round($w * $maxDim / $h);
        }

        $canvas = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        ob_start();
        imagejpeg($canvas, null, 85);
        $reduced = (string) ob_get_clean();
        imagedestroy($canvas);

        return ['bytes' => $reduced, 'media' => 'image/jpeg'];
    }

    /** Llama a la API de Anthropic con una imagen y devuelve el texto de la respuesta. */
    private function visionClaude(string $apiKey, string $b64, string $media, string $prompt, int $maxTokens): string
    {
        $resp = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(90)->post('https://api.anthropic.com/v1/messages', [
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => $maxTokens,
            'messages'   => [[
                'role'    => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $media, 'data' => $b64]],
                    ['type' => 'text', 'text' => $prompt],
                ],
            ]],
        ]);

        if (! $resp->successful()) {
            Log::error('Vision-IA: Anthropic error', ['status' => $resp->status(), 'body' => $resp->body()]);
            throw new \RuntimeException('La IA respondió HTTP '.$resp->status().'.');
        }

        $texto = '';
        foreach ($resp->json('content', []) as $bloque) {
            if (($bloque['type'] ?? null) === 'text') {
                $texto .= $bloque['text'];
            }
        }

        return $texto;
    }

    /** Pide a la IA los datos completos de la factura como JSON estructurado. */
    private function datosFacturaIa(string $apiKey, string $b64, string $media): ?array
    {
        $prompt = 'Extrae los datos de esta factura (Panamá) y devuélvelos SOLO como JSON válido, '
            ."sin texto antes ni después, sin ```. Esquema exacto:\n"
            .'{"tipo":"FACTURA|NOTA_CREDITO|NOTA_DEBITO","numero":"","fecha":"YYYY-MM-DD","cufe":"o null",'
            .'"emisor":{"ruc":"","dv":"","nombre":"","direccion":"","telefono":""},'
            .'"subtotal":0,"itbms":0,"total":0,'
            .'"lineas":[{"descripcion":"","cantidad":1,"precio_unitario":0,"descuento":0,"itbms":0,"total":0}]}'
            ."\nUsa números (no texto) en los montos. Si un dato no aparece, usa null o 0. "
            .'El emisor es quien EMITE la factura (el proveedor), no el receptor.';

        $texto = trim($this->visionClaude($apiKey, $b64, $media, $prompt, 2000));

        // Quita posibles cercas de código y recorta al objeto JSON.
        $texto = preg_replace('/^```(?:json)?|```$/m', '', $texto);
        if (preg_match('/\{.*\}/s', $texto, $m)) {
            $texto = $m[0];
        }

        $datos = json_decode(trim($texto), true);

        return is_array($datos) ? $datos : null;
    }

    public function desdeCufe(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canAny(['cxp.gestionar', 'cxp.registrar_qr']), 403);
        $companiaId = $this->companiaActivaId($request);
        $usuario    = $request->user();

        $data = $request->validate([
            'cufe_input'   => ['nullable', 'string', 'max:500'],
            'datos_ia'     => ['nullable', 'string', 'max:20000'],
            'archivo_path' => ['nullable', 'string', 'max:1024'],
            'archivo_disk' => ['nullable', 'string', 'max:30'],
        ]);

        $seguir = $request->boolean('seguir');

        // Foto ya subida a S3 en el paso de IA (si vino). Validar que sea de esta compañía.
        $fotoPath = null;
        $fotoDisk = null;
        if (! empty($data['archivo_path']) && str_starts_with($data['archivo_path'], 'cxp/'.$companiaId.'/')) {
            $fotoPath = $data['archivo_path'];
            $fotoDisk = $data['archivo_disk'] ?: config('filesystems.adjuntos', 's3');
        }

        // ── Camino B: datos extraídos por IA de la foto (sin DGI) ──────────
        if (! empty($data['datos_ia'])) {
            $ia = json_decode($data['datos_ia'], true);
            if (! is_array($ia) || empty($ia['lineas']) || ! is_array($ia['lineas'])) {
                throw ValidationException::withMessages(['cufe_input' => 'Los datos leídos por la IA no son válidos. Reintenta la foto.']);
            }

            $dgi  = $this->normalizarDatosIa($ia);
            $cufe = $dgi['cufe'] ?: null;

            if (! ($dgi['emisor']['ruc'] ?? null) && ! ($dgi['emisor']['nombre'] ?? null)) {
                throw ValidationException::withMessages(['cufe_input' => 'La IA no identificó al proveedor. Toma una foto más clara o regístrala manualmente.']);
            }

            if ($cufe && $existente = CxpDocumento::where('compania_id', $companiaId)->where('cufe', $cufe)->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)->first()) {
                return $this->respuestaCxpExistente($existente, $seguir);
            }

            $factura = $this->crearBorradorCxp($dgi, $companiaId, $usuario);
            if (! $factura->wasRecentlyCreated) {
                return $this->respuestaCxpExistente($factura, $seguir);
            }
            $this->adjuntarArchivoCxp($factura, $fotoPath, $fotoDisk, $cufe, $companiaId);

            return $this->respuestaCxpCreada($factura, $seguir, 'registrada (datos leídos por IA — verifica antes de contabilizar)');
        }

        // ── Camino A: CUFE / QR → DGI (datos oficiales) ───────────────────
        $raw = trim((string) ($data['cufe_input'] ?? ''));
        if ($raw === '') {
            throw ValidationException::withMessages(['cufe_input' => 'Ingresa el CUFE o usa el escáner.']);
        }

        if (preg_match('/[?&]chFE=([^&\s]+)/i', $raw, $m)) {
            $cufe = rawurldecode($m[1]);
        } elseif (preg_match('#/FacturasPorCUFE/([A-Za-z0-9\-]+)#i', $raw, $m)) {
            $cufe = $m[1];
        } else {
            $cufe = $raw;
        }

        if (strlen($cufe) < 20) {
            throw ValidationException::withMessages(['cufe_input' => 'El valor ingresado no parece un CUFE válido.']);
        }

        if ($existente = CxpDocumento::where('compania_id', $companiaId)->where('cufe', $cufe)->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)->first()) {
            return $this->respuestaCxpExistente($existente, $seguir);
        }

        $dgi = app(DgiFepConsulta::class)->porCufe($cufe);

        if (! $dgi) {
            throw ValidationException::withMessages(['cufe_input' => 'No se pudo obtener la factura de la DGI. Verifica el CUFE/QR e intenta nuevamente.']);
        }

        if (! ($dgi['emisor']['ruc'] ?? null)) {
            throw ValidationException::withMessages(['cufe_input' => 'La DGI no devolvió el RUC del emisor. Registra la factura manualmente.']);
        }

        $dgi['cufe'] = $cufe;
        $factura = $this->crearBorradorCxp($dgi, $companiaId, $usuario);
        if (! $factura->wasRecentlyCreated) {
            return $this->respuestaCxpExistente($factura, $seguir);
        }
        $this->adjuntarArchivoCxp($factura, $fotoPath, $fotoDisk, $cufe, $companiaId);

        return $this->respuestaCxpCreada($factura, $seguir, 'registrada desde QR/CUFE. Revisa las cuentas contables y contabiliza.');
    }

    /**
     * Asocia el archivo de respaldo al documento: si vino una foto (subida por
     * IA) la usa; si no, descarga el PDF oficial de la DGI por CUFE (best-effort).
     */
    private function adjuntarArchivoCxp(CxpDocumento $factura, ?string $fotoPath, ?string $fotoDisk, ?string $cufe, int $companiaId): void
    {
        if ($fotoPath) {
            $factura->update(['archivo_path' => $fotoPath, 'archivo_disk' => $fotoDisk]);
            $this->espejarArchivoEnAdjuntos($factura->fresh());

            return;
        }

        if ($cufe && strlen($cufe) === 66) {
            $pdf = app(DgiFepConsulta::class)->pdfPorCufe($cufe);
            if ($pdf && $adj = $this->guardarAdjuntoCxp($pdf, 'pdf', $companiaId)) {
                $factura->update(['archivo_path' => $adj['path'], 'archivo_disk' => $adj['disk']]);
                $this->espejarArchivoEnAdjuntos($factura->fresh());
            }
        }
    }

    /**
     * Registra (idempotente, best-effort) el archivo_path legado del documento en
     * core_adjuntos. Es el puente de "doble escritura" durante la transición al
     * sistema central de adjuntos: no toca S3, solo crea la fila si falta.
     */
    private function espejarArchivoEnAdjuntos(?CxpDocumento $factura): void
    {
        if (! $factura || ! $factura->archivo_path) {
            return;
        }

        try {
            app(AdjuntoService::class)->registrarExistente(
                $factura->archivo_path,
                $factura->archivo_disk,
                'cxp_documentos',
                $factura->id,
                $factura->compania_id,
                'CXP',
            );
        } catch (Throwable $e) {
            Log::warning('CxP: no se pudo espejar archivo en core_adjuntos', [
                'factura' => $factura->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Crea la CxP en BORRADOR (proveedor + documento + líneas) a partir de la
     * estructura de DgiFepConsulta o de los datos extraídos por IA.
     */
    private function crearBorradorCxp(array $dgi, int $companiaId, $usuario): CxpDocumento
    {
        $emisor = $dgi['emisor'] ?? [];
        $ruc    = $emisor['ruc'] ?? null;
        $cufe   = $dgi['cufe'] ?? null;

        $cuentaGastoDefault = CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT');
        $tipoProveedor      = TipoContacto::where('codigo', 'PROVEEDOR')->first();

        // Buscar proveedor por RUC; si no hay RUC, por nombre.
        $proveedor = null;
        if ($ruc) {
            $proveedor = Contacto::where('compania_id', $companiaId)->where('identificacion', $ruc)->first();
        } elseif (! empty($emisor['nombre'])) {
            $proveedor = Contacto::where('compania_id', $companiaId)->where('nombre', $emisor['nombre'])->first();
        }

        if (! $proveedor) {
            $codigo = $ruc ? substr($ruc, 0, 50) : null;
            if ($codigo && Contacto::where('compania_id', $companiaId)->where('codigo', $codigo)->exists()) {
                $codigo = null;
            }

            $proveedor = Contacto::create([
                'compania_id'     => $companiaId,
                'codigo'          => $codigo,
                'nombre'          => substr($emisor['nombre'] ?? $ruc ?? 'Proveedor', 0, 200),
                'tipo_persona'    => 'JURIDICA',
                'identificacion'  => $ruc,
                'dv'              => isset($emisor['dv']) ? substr((string) $emisor['dv'], 0, 5) : null,
                'direccion'       => $emisor['direccion'] ?? null,
                'telefono'        => isset($emisor['telefono']) ? substr((string) $emisor['telefono'], 0, 50) : null,
                'activo'          => true,
                'cuenta_gasto_id' => $cuentaGastoDefault,
                'created_by'      => $usuario->email,
            ]);

            if ($tipoProveedor) {
                $proveedor->tipos()->attach($tipoProveedor->id);
            }
        }

        $cuentaId = $proveedor->cuenta_gasto_id ?? $cuentaGastoDefault;
        $numero   = $dgi['numero'] ?? ($cufe ? substr($cufe, 0, 50) : 'IA-'.now()->format('YmdHis'));
        $tipoDoc  = in_array($dgi['tipo'] ?? null, [CxpDocumento::TIPO_NOTA_CREDITO, CxpDocumento::TIPO_NOTA_DEBITO], true)
            ? $dgi['tipo']
            : CxpDocumento::TIPO_FACTURA;

        // Evita el choque con el índice único parcial (compania, proveedor, tipo,
        // numero) WHERE estado <> 'ANULADO': si ya existe un documento VIGENTE con
        // ese número se devuelve en lugar de provocar un error de BD. Los ANULADO
        // se ignoran a propósito para permitir volver a registrar la factura.
        // El llamador distingue creada vs. existente con $factura->wasRecentlyCreated.
        $existente = CxpDocumento::where('compania_id', $companiaId)
            ->where('proveedor_id', $proveedor->id)
            ->where('tipo_documento', $tipoDoc)
            ->where('numero', $numero)
            ->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)
            ->first();
        if ($existente) {
            return $existente;
        }

        return DB::transaction(function () use ($companiaId, $proveedor, $dgi, $cufe, $numero, $tipoDoc, $cuentaId, $usuario) {
            $factura = CxpDocumento::create([
                'compania_id'    => $companiaId,
                'proveedor_id'   => $proveedor->id,
                'tipo_documento' => $tipoDoc,
                'numero'         => $numero,
                'cufe'           => $cufe,
                'fecha'          => $dgi['fecha'] ?? now()->toDateString(),
                'subtotal'       => $dgi['subtotal'] ?? 0,
                'descuento'      => 0,
                'impuesto'       => $dgi['itbms'] ?? 0,
                'total'          => $dgi['total'] ?? 0,
                'saldo'          => $dgi['total'] ?? 0,
                'estado'         => CxpDocumento::ESTADO_BORRADOR,
                'created_by'     => $usuario->email,
            ]);

            foreach ($dgi['lineas'] as $n => $linea) {
                CxpDocumentoDetalle::create([
                    'documento_id'    => $factura->id,
                    'linea'           => $n + 1,
                    'descripcion'     => substr($linea['descripcion'] ?? 'Sin descripción', 0, 500),
                    'cantidad'        => $linea['cantidad'] ?? 1,
                    'precio_unitario' => $linea['precio_unitario'] ?? 0,
                    'descuento'       => $linea['descuento'] ?? 0,
                    'impuesto_monto'  => $linea['itbms'] ?? 0,
                    'total_linea'     => $linea['total'] ?? 0,
                    'cuenta_id'       => $cuentaId,
                    'created_by'      => $usuario->email,
                ]);
            }

            return $factura;
        });
    }

    /** Limpia/castea la estructura de factura que devolvió la IA. */
    private function normalizarDatosIa(array $ia): array
    {
        $num = fn ($v) => is_numeric($v) ? round((float) $v, 2) : 0.0;

        $tipo = strtoupper((string) ($ia['tipo'] ?? 'FACTURA'));
        if (! in_array($tipo, ['FACTURA', 'NOTA_CREDITO', 'NOTA_DEBITO'], true)) {
            $tipo = 'FACTURA';
        }

        $emisor = is_array($ia['emisor'] ?? null) ? $ia['emisor'] : [];

        $lineas = [];
        foreach ($ia['lineas'] as $l) {
            if (! is_array($l)) {
                continue;
            }
            $lineas[] = [
                'descripcion'     => substr(trim((string) ($l['descripcion'] ?? '')) ?: 'Sin descripción', 0, 500),
                'cantidad'        => $num($l['cantidad'] ?? 1) ?: 1.0,
                'precio_unitario' => $num($l['precio_unitario'] ?? 0),
                'descuento'       => $num($l['descuento'] ?? 0),
                'itbms'           => $num($l['itbms'] ?? 0),
                'total'           => $num($l['total'] ?? 0),
            ];
        }

        $cufe = preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($ia['cufe'] ?? ''));

        return [
            'cufe'     => $cufe !== '' ? $cufe : null,
            'numero'   => isset($ia['numero']) ? substr((string) $ia['numero'], 0, 50) : null,
            'tipo'     => $tipo,
            'fecha'    => $this->fechaIa($ia['fecha'] ?? null),
            'emisor'   => [
                'ruc'       => isset($emisor['ruc']) ? substr(trim((string) $emisor['ruc']), 0, 50) ?: null : null,
                'dv'        => isset($emisor['dv']) ? substr(trim((string) $emisor['dv']), 0, 5) ?: null : null,
                'nombre'    => isset($emisor['nombre']) ? substr(trim((string) $emisor['nombre']), 0, 200) ?: null : null,
                'direccion' => $emisor['direccion'] ?? null,
                'telefono'  => isset($emisor['telefono']) ? substr(trim((string) $emisor['telefono']), 0, 50) ?: null : null,
            ],
            'subtotal' => $num($ia['subtotal'] ?? 0),
            'itbms'    => $num($ia['itbms'] ?? 0),
            'total'    => $num($ia['total'] ?? 0),
            'lineas'   => $lineas,
        ];
    }

    private function fechaIa($valor): ?string
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($valor)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function respuestaCxpExistente(CxpDocumento $existente, bool $seguir): RedirectResponse
    {
        if ($seguir) {
            return redirect()->route('admin.cxp.facturas.desde-cufe.form')
                ->with('ok_factura', [
                    'numero' => $existente->numero,
                    'url'    => route('admin.cxp.facturas.show', $existente),
                    'aviso'  => 'ya estaba registrada',
                ]);
        }

        return redirect()->route('admin.cxp.facturas.show', $existente)
            ->with('status', 'Esta factura ya estaba registrada.');
    }

    private function respuestaCxpCreada(CxpDocumento $factura, bool $seguir, string $aviso): RedirectResponse
    {
        if ($seguir) {
            return redirect()->route('admin.cxp.facturas.desde-cufe.form')
                ->with('ok_factura', [
                    'numero' => $factura->numero,
                    'url'    => route('admin.cxp.facturas.show', $factura),
                    'aviso'  => $aviso,
                ]);
        }

        return redirect()->route('admin.cxp.facturas.show', $factura)
            ->with('status', "Factura {$factura->numero} {$aviso}");
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

    /**
     * Descarga la plantilla .xlsx para importar compras propias (no DGI), con
     * un par de cuentas de gasto reales de la compañía como ejemplo.
     */
    public function importarGenericoPlantilla(Request $request): Response
    {
        abort_unless($request->user()->can('cxp.gestionar'), 403);

        $companiaId = $this->companiaActivaId($request);

        $cuentas = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->where('activa', true)
            ->where('codigo', 'like', '6%') // cuentas de gasto/costo como muestra
            ->orderBy('codigo')
            ->limit(2)
            ->get(['codigo', 'nombre'])
            ->map(fn ($c) => [$c->codigo, $c->nombre])
            ->all();

        return Excel::download(new CxpComprasPlantillaExport($cuentas), 'plantilla_compras.xlsx');
    }

    /**
     * Importa compras propias (no DGI) desde un Excel/CSV. Crea cada documento
     * como BORRADOR para que el contador lo revise y contabilice; nunca postea
     * automáticamente. Síncrono: una lista manual de compras es chica.
     */
    public function importarGenerico(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('cxp.gestionar'), 403);

        $companiaId = $this->companiaActivaId($request);
        $usuario = $request->user();

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $import = new CxpComprasGenericoImport;
        Excel::import($import, $request->file('archivo'));

        if ($import->filas === []) {
            return back()->withErrors(['archivo_generico' => 'El archivo no tiene filas con datos. La primera fila deben ser los encabezados (proveedor, numero, fecha, subtotal…).']);
        }

        $cuentaGastoDefault = CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT');
        $catalogo = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->get(['id', 'codigo'])
            ->keyBy(fn ($c) => trim((string) $c->codigo));

        // Índice de contactos para emparejar proveedores con tolerancia (sin duplicar):
        // por RUC exacto, código exacto y nombre NORMALIZADO. Se construye una vez y los
        // proveedores nuevos se agregan dentro del bucle para reconocerlos en filas siguientes.
        $indiceProveedores = ['ruc' => [], 'codigo' => [], 'nombre' => []];
        foreach (Contacto::where('compania_id', $companiaId)->get(['id', 'codigo', 'nombre', 'identificacion', 'cuenta_gasto_id']) as $c) {
            $this->indexarContacto($indiceProveedores, $c);
        }

        $errores = [];
        $documentos = []; // clave proveedor|tipo|numero => cabecera + líneas

        foreach ($import->filas as $f) {
            $fila = $f['fila'];

            if ($f['proveedor'] === '' && $f['ruc'] === '') {
                $errores[] = "Fila {$fila}: falta el proveedor (nombre o RUC).";

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

            $proveedor = $this->resolverOCrearProveedorGenerico($f, $companiaId, $cuentaGastoDefault, $usuario, $indiceProveedores);

            // Cuenta de gasto: por código del Excel, o la del proveedor, o la default.
            $cuentaId = null;
            if ($f['cuenta'] !== '') {
                $cuentaId = $catalogo[$f['cuenta']]->id ?? null;
                if (! $cuentaId) {
                    $errores[] = "Fila {$fila}: la cuenta '{$f['cuenta']}' no existe o no permite movimiento; se usó la cuenta de gasto por defecto.";
                }
            }
            $cuentaId ??= $proveedor->cuenta_gasto_id ?? $cuentaGastoDefault;

            if (! $cuentaId) {
                $errores[] = "Fila {$fila}: no hay cuenta de gasto (ni en el Excel, ni en el proveedor, ni la default GASTO_DEFAULT). Configura GASTO_DEFAULT.";

                continue;
            }

            $tipo = $this->normalizarTipoCompra($f['tipo']);
            $base = round($f['subtotal'], 2);
            $itbms = $f['itbms'] > 0
                ? round($f['itbms'], 2)
                : ($f['tasa'] > 0 ? round($base * $f['tasa'] / 100, 2) : 0.0);

            $clave = $proveedor->id.'|'.$tipo.'|'.$f['numero'];
            $documentos[$clave] ??= [
                'proveedor'   => $proveedor,
                'tipo'        => $tipo,
                'numero'      => $f['numero'],
                'fecha'       => $f['fecha'],
                'vencimiento' => $f['vencimiento'],
                'lineas'      => [],
            ];
            $documentos[$clave]['lineas'][] = [
                'descripcion'    => $f['concepto'] !== '' ? substr($f['concepto'], 0, 500) : 'Compra '.$f['numero'],
                'cantidad'       => 1,
                'precio'         => $base,
                'itbms'          => $itbms,
                'total'          => round($base + $itbms, 2),
                'cuenta_id'      => (int) $cuentaId,
            ];
        }

        $creadas = 0;
        $omitidas = 0;

        foreach ($documentos as $doc) {
            $existe = CxpDocumento::where('compania_id', $companiaId)
                ->where('proveedor_id', $doc['proveedor']->id)
                ->where('tipo_documento', $doc['tipo'])
                ->where('numero', $doc['numero'])
                ->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)
                ->exists();

            if ($existe) {
                $omitidas++;
                $errores[] = "Documento {$doc['numero']} de {$doc['proveedor']->nombre}: ya existe; se omitió.";

                continue;
            }

            $subtotal = round(array_sum(array_column($doc['lineas'], 'precio')), 2);
            $impuesto = round(array_sum(array_column($doc['lineas'], 'itbms')), 2);
            $total = round($subtotal + $impuesto, 2);

            DB::transaction(function () use ($companiaId, $doc, $subtotal, $impuesto, $total, $usuario) {
                $factura = CxpDocumento::create([
                    'compania_id'       => $companiaId,
                    'proveedor_id'      => $doc['proveedor']->id,
                    'tipo_documento'    => $doc['tipo'],
                    'numero'            => $doc['numero'],
                    'fecha'             => $doc['fecha'],
                    'fecha_vencimiento' => $doc['vencimiento'],
                    'subtotal'          => $subtotal,
                    'descuento'         => 0,
                    'impuesto'          => $impuesto,
                    'total'             => $total,
                    'saldo'             => $total,
                    'estado'            => CxpDocumento::ESTADO_BORRADOR,
                    'created_by'        => $usuario->email,
                ]);

                foreach ($doc['lineas'] as $n => $linea) {
                    CxpDocumentoDetalle::create([
                        'documento_id'    => $factura->id,
                        'linea'           => $n + 1,
                        'descripcion'     => $linea['descripcion'],
                        'cantidad'        => $linea['cantidad'],
                        'precio_unitario' => $linea['precio'],
                        'descuento'       => 0,
                        'impuesto_monto'  => $linea['itbms'],
                        'total_linea'     => $linea['total'],
                        'cuenta_id'       => $linea['cuenta_id'],
                        'created_by'      => $usuario->email,
                    ]);
                }
            });

            $creadas++;
        }

        $resumen = "Importación de compras: {$creadas} documento(s) creado(s) como borrador";
        if ($omitidas > 0) {
            $resumen .= ", {$omitidas} omitido(s) por estar ya registrados";
        }
        $resumen .= '. Revísalos y contabilízalos.';

        return redirect()->route('admin.cxp.facturas.index')
            ->with('status', $resumen)
            ->with('import_compras_errores', array_slice($errores, 0, 50));
    }

    /** Descarga la plantilla de ejemplo para importar saldos iniciales de proveedores. */
    public function importarSaldosInicialesPlantilla(Request $request): Response
    {
        abort_unless($request->user()->can('cxp.gestionar'), 403);

        return Excel::download(new CxpSaldosInicialesPlantillaExport, 'plantilla_saldos_iniciales_cxp.xlsx');
    }

    /**
     * Importa los SALDOS INICIALES de proveedores, factura por factura, desde un
     * Excel/CSV. A diferencia del import de compras del período, cada documento se
     * crea ya CONTABILIZADO (estado PENDIENTE) con un asiento de apertura:
     *
     *   Dr [cuenta de apertura elegida]  /  Cr [CxP de control]   (cargo: factura/ND)
     *   Dr [CxP de control]  /  Cr [cuenta de apertura elegida]   (abono: NC)
     *
     * NO se reconoce gasto ni ITBMS de nuevo (el crédito fiscal ya se tomó en el
     * sistema anterior); el monto es el saldo pendiente. El asiento se postea a la
     * fecha de corte indicada; el documento conserva su fecha y vencimiento
     * originales para que la antigüedad cuadre. Síncrono: una lista de saldos
     * iniciales es acotada. Idempotente por proveedor+tipo+número.
     */
    public function importarSaldosIniciales(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('cxp.gestionar'), 403);

        $companiaId = $this->companiaActivaId($request);
        $usuario = $request->user();

        $request->validate([
            'archivo'           => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'fecha_corte'       => ['required', 'date'],
            'cuenta_apertura_id' => ['required', 'integer'],
        ]);

        $cuentaApertura = CuentaContable::where('compania_id', $companiaId)
            ->where('id', $request->integer('cuenta_apertura_id'))
            ->where('permite_movimiento', true)
            ->first();

        if (! $cuentaApertura) {
            return back()->withErrors(['cuenta_apertura_id' => 'La cuenta de contrapartida (apertura) no existe en esta compañía o no permite movimiento.']);
        }

        $cuentaCxpId = CuentaDefault::idPara($companiaId, 'CXP');
        if (! $cuentaCxpId) {
            return back()->withErrors(['archivo_saldos' => 'La compañía no tiene configurada la cuenta default CXP (Cuentas por Pagar). Aplica una plantilla de plan de cuentas o configúrala.']);
        }
        $cuentaCxpId = (int) $cuentaCxpId;

        $fechaCorte = $request->date('fecha_corte')->format('Y-m-d');

        $import = new CxpSaldosInicialesImport;
        Excel::import($import, $request->file('archivo'));

        if ($import->filas === []) {
            return back()->withErrors(['archivo_saldos' => 'El archivo no tiene filas con datos. La primera fila deben ser los encabezados (proveedor, numero, fecha, monto…).']);
        }

        $cuentaGastoDefault = CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT');

        // Índice de contactos para emparejar proveedores con tolerancia (sin duplicar).
        $indiceProveedores = ['ruc' => [], 'codigo' => [], 'nombre' => []];
        foreach (Contacto::where('compania_id', $companiaId)->get(['id', 'codigo', 'nombre', 'identificacion', 'cuenta_gasto_id']) as $c) {
            $this->indexarContacto($indiceProveedores, $c);
        }

        $errores = [];
        $documentos = []; // clave proveedor|tipo|numero => cabecera (un saldo = un documento)

        foreach ($import->filas as $f) {
            $fila = $f['fila'];

            if ($f['proveedor'] === '' && $f['ruc'] === '') {
                $errores[] = "Fila {$fila}: falta el proveedor (nombre o RUC).";

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
            if ($f['monto'] <= 0) {
                $errores[] = "Fila {$fila}: el monto (saldo pendiente) debe ser mayor que cero.";

                continue;
            }

            $proveedor = $this->resolverOCrearProveedorGenerico($f, $companiaId, $cuentaGastoDefault, $usuario, $indiceProveedores);
            $tipo = $this->normalizarTipoCompra($f['tipo']);

            $clave = $proveedor->id.'|'.$tipo.'|'.$f['numero'];
            if (isset($documentos[$clave])) {
                // Mismo documento repetido en el Excel: suma el monto en lugar de duplicar.
                $documentos[$clave]['monto'] = round($documentos[$clave]['monto'] + $f['monto'], 2);

                continue;
            }
            $documentos[$clave] = [
                'proveedor'   => $proveedor,
                'tipo'        => $tipo,
                'numero'      => $f['numero'],
                'fecha'       => $f['fecha'],
                'vencimiento' => $f['vencimiento'] ?: $f['fecha'],
                'concepto'    => $f['concepto'] !== '' ? substr($f['concepto'], 0, 500) : 'Saldo inicial '.$f['numero'],
                'monto'       => round($f['monto'], 2),
            ];
        }

        $creadas = 0;
        $omitidas = 0;

        foreach ($documentos as $doc) {
            $existe = CxpDocumento::where('compania_id', $companiaId)
                ->where('proveedor_id', $doc['proveedor']->id)
                ->where('tipo_documento', $doc['tipo'])
                ->where('numero', $doc['numero'])
                ->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)
                ->exists();

            if ($existe) {
                $omitidas++;
                $errores[] = "Documento {$doc['numero']} de {$doc['proveedor']->nombre}: ya existe; se omitió.";

                continue;
            }

            try {
                DB::transaction(function () use ($companiaId, $doc, $fechaCorte, $cuentaCxpId, $cuentaApertura, $usuario) {
                    $factura = CxpDocumento::create([
                        'compania_id'       => $companiaId,
                        'proveedor_id'      => $doc['proveedor']->id,
                        'tipo_documento'    => $doc['tipo'],
                        'numero'            => $doc['numero'],
                        'fecha'             => $doc['fecha'],
                        'fecha_vencimiento' => $doc['vencimiento'],
                        'subtotal'          => $doc['monto'],
                        'descuento'         => 0,
                        'impuesto'          => 0,
                        'total'             => $doc['monto'],
                        'saldo'             => $doc['monto'],
                        'estado'            => CxpDocumento::ESTADO_PENDIENTE,
                        'created_by'        => $usuario->email,
                    ]);

                    CxpDocumentoDetalle::create([
                        'documento_id'    => $factura->id,
                        'linea'           => 1,
                        'descripcion'     => $doc['concepto'],
                        'cantidad'        => 1,
                        'precio_unitario' => $doc['monto'],
                        'descuento'       => 0,
                        'impuesto_monto'  => 0,
                        'total_linea'     => $doc['monto'],
                        'cuenta_id'       => $cuentaApertura->id,
                        'created_by'      => $usuario->email,
                    ]);

                    $this->contabilizarSaldoInicial($factura, $cuentaCxpId, $cuentaApertura->id, $fechaCorte, $usuario);
                });

                $creadas++;
            } catch (ValidationException $e) {
                $msg = collect($e->errors())->flatten()->first() ?? 'no se pudo contabilizar';
                $errores[] = "Documento {$doc['numero']} de {$doc['proveedor']->nombre}: {$msg}";
            }
        }

        $resumen = "Saldos iniciales de CxP: {$creadas} documento(s) abierto(s) y contabilizado(s) al corte {$fechaCorte}";
        if ($omitidas > 0) {
            $resumen .= ", {$omitidas} omitido(s) por estar ya registrados";
        }
        $resumen .= '.';

        return redirect()->route('admin.cxp.facturas.index')
            ->with('status', $resumen)
            ->with('import_compras_errores', array_slice($errores, 0, 50));
    }

    /**
     * Postea el asiento de apertura de un saldo inicial de proveedor: la
     * contrapartida del CxP de control es la cuenta de apertura elegida (no un
     * gasto), sin ITBMS. Cargo (factura/ND) => Dr apertura / Cr CxP; abono (NC) =>
     * Dr CxP / Cr apertura. Debe llamarse dentro de una transacción.
     */
    private function contabilizarSaldoInicial(CxpDocumento $factura, int $cuentaCxpId, int $cuentaAperturaId, string $fechaCorte, $usuario): void
    {
        $companiaId = $factura->compania_id;
        $total = round((float) $factura->total, 2);
        $etiqueta = $factura->etiquetaTipo();
        $esCredito = $factura->esAbono();

        $lineaCxp = [
            'cuenta_id'   => $cuentaCxpId,
            'contacto_id' => $factura->proveedor_id,
            'descripcion' => "Saldo inicial {$etiqueta} {$factura->numero}",
        ];
        $lineaApertura = [
            'cuenta_id'   => $cuentaAperturaId,
            'descripcion' => "Saldo inicial {$etiqueta} {$factura->numero} — ".$factura->proveedor->nombre,
        ];

        if ($esCredito) {
            // Nota de crédito a favor: Dr CxP / Cr apertura.
            $lineasAsiento = [
                $lineaCxp + ['debito' => $total, 'credito' => 0],
                $lineaApertura + ['debito' => 0, 'credito' => $total],
            ];
        } else {
            // Factura / nota de débito: Dr apertura / Cr CxP.
            $lineasAsiento = [
                $lineaApertura + ['debito' => $total, 'credito' => 0],
                $lineaCxp + ['debito' => 0, 'credito' => $total],
            ];
        }

        $asiento = app(AsientoAutomatico::class)->postear(
            $companiaId,
            $fechaCorte,
            "Saldo inicial {$etiqueta} {$factura->numero} — ".$factura->proveedor->nombre,
            $factura->numero,
            $lineasAsiento,
            'CXP',
            'cxp_documentos',
            $factura->id,
            $usuario,
        );

        $factura->update([
            'asiento_id' => $asiento->id,
            'updated_by' => $usuario->email,
        ]);
    }

    /** Resuelve el proveedor por RUC → código → nombre; si no existe lo crea. */
    /**
     * Resuelve el proveedor contra el índice (RUC exacto → código exacto → nombre
     * NORMALIZADO, tolerante a tildes/mayúsculas/puntuación/espacios); si no existe
     * lo crea y lo agrega al índice para las filas siguientes. $indice por referencia.
     */
    private function resolverOCrearProveedorGenerico(array $f, int $companiaId, ?int $cuentaGastoDefault, $usuario, array &$indice): Contacto
    {
        $ruc = $f['ruc'] !== '' ? substr($f['ruc'], 0, 50) : null;
        $nombre = $f['proveedor'];

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

        $proveedor = Contacto::create([
            'compania_id'     => $companiaId,
            'codigo'          => $codigo,
            'nombre'          => substr($nombre !== '' ? $nombre : ($ruc ?? 'Proveedor'), 0, 200),
            'tipo_persona'    => 'JURIDICA',
            'identificacion'  => $ruc,
            'activo'          => true,
            'cuenta_gasto_id' => $cuentaGastoDefault,
            'created_by'      => $usuario->email,
        ]);

        if ($tipoProveedor = TipoContacto::where('codigo', 'PROVEEDOR')->first()) {
            $proveedor->tipos()->attach($tipoProveedor->id);
        }

        $this->indexarContacto($indice, $proveedor);

        return $proveedor;
    }

    /** Normaliza el tipo del Excel (FACTURA/NC/ND y sinónimos). */
    private function normalizarTipoCompra(string $tipo): string
    {
        $t = strtoupper(trim($tipo));
        $t = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $t);

        return match (true) {
            $t === '' || str_contains($t, 'FACTURA')                    => CxpDocumento::TIPO_FACTURA,
            $t === 'NC' || str_contains($t, 'CREDITO')                  => CxpDocumento::TIPO_NOTA_CREDITO,
            $t === 'ND' || str_contains($t, 'DEBITO')                   => CxpDocumento::TIPO_NOTA_DEBITO,
            default                                                     => CxpDocumento::TIPO_FACTURA,
        };
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

            // Si la factura subió inventario (compra DIRECTA de productos), bajarlo.
            // Las facturas desde OC no movieron inventario (entró en la recepción
            // contra GRNI); su anulación solo revierte el asiento GRNI→CxP.
            app(InventarioCompras::class)->reversarPorDocumento('cxp_documentos', $documento->id, $usuario);

            // Si proviene de una orden, devuelve lo facturado por línea para que
            // vuelva a quedar facturable, y recalcula el estado de la orden.
            $this->revertirFacturadoEnOrden($documento, $usuario);

            $documento->update([
                'estado' => CxpDocumento::ESTADO_ANULADO,
                'saldo' => 0,
                'updated_by' => $usuario->email,
            ]);
        });

        return redirect()->route('admin.cxp.facturas.show', $documento)
            ->with('status', "Factura {$documento->numero} anulada.");
    }

    /**
     * Devuelve a la orden de compra las cantidades facturadas por este documento
     * (al anularlo): baja cantidad_facturada por línea, libera el enlace de compat
     * y recalcula el estado de la orden. No hace nada si no proviene de una orden.
     */
    private function revertirFacturadoEnOrden(CxpDocumento $documento, $usuario): void
    {
        if (! $documento->orden_id) {
            return;
        }

        $documento->loadMissing('detalle');
        foreach ($documento->detalle as $d) {
            if ($d->orden_detalle_id) {
                CompraOrdenDetalle::where('id', $d->orden_detalle_id)
                    ->update(['cantidad_facturada' => DB::raw('GREATEST(cantidad_facturada - '.(float) $d->cantidad.', 0)')]);
            }
        }

        $orden = CompraOrden::find($documento->orden_id);
        if ($orden) {
            if ($orden->cxp_documento_id === $documento->id) {
                $orden->update(['cxp_documento_id' => null]);
            }
            $orden->load('detalle');
            $orden->refrescarEstadoFacturacion();
        }
    }

    /**
     * Corregir una factura contabilizada: en una sola transacción la anula
     * (revirtiendo su asiento, igual que anular()) y crea un BORRADOR idéntico
     * —mismas líneas y número, que el índice único parcial permite porque
     * excluye los ANULADO— para que el usuario lo edite y vuelva a contabilizar.
     * Nada se borra: la factura original queda ANULADA en el historial.
     */
    public function corregir(Request $request, CxpDocumento $documento): RedirectResponse
    {
        $this->autorizarFactura($request, $documento);

        if ($documento->esBorrador()) {
            return redirect()->route('admin.cxp.facturas.edit', $documento);
        }

        if ($documento->esAnulado()) {
            return back()->withErrors(['documento' => 'La factura ya está anulada.']);
        }

        if ($documento->orden_id) {
            return back()->withErrors(['documento' => 'Esta factura proviene de una orden de compra; anúlala y vuelve a facturar desde la orden para mantener el cuadre con la cuenta puente (GRNI).']);
        }

        if ($documento->aplicacionesComoDestino()->exists()) {
            return back()->withErrors(['documento' => 'La factura tiene pagos aplicados; anula primero los pagos, luego corrígela.']);
        }

        $usuario = $request->user();
        $documento->load('detalle');

        $borrador = DB::transaction(function () use ($documento, $usuario) {
            // 1) Reversar contabilidad y marcar el original como ANULADO.
            if ($documento->asiento) {
                app(AsientoAutomatico::class)->anular($documento->asiento, $usuario);
            }

            // Si subió inventario (compra de productos), bajarlo también.
            app(InventarioCompras::class)->reversarPorDocumento('cxp_documentos', $documento->id, $usuario);

            $documento->update([
                'estado' => CxpDocumento::ESTADO_ANULADO,
                'saldo' => 0,
                'updated_by' => $usuario->email,
            ]);

            // 2) Clonar la cabecera como borrador (sin asiento, saldo = total).
            $borrador = $documento->replicate(['estado', 'asiento_id', 'saldo', 'created_by', 'updated_by']);
            $borrador->estado = CxpDocumento::ESTADO_BORRADOR;
            $borrador->asiento_id = null;
            $borrador->saldo = $documento->total;
            $borrador->created_by = $usuario->email;
            $borrador->updated_by = $usuario->email;
            $borrador->save();

            // 3) Clonar las líneas.
            foreach ($documento->detalle as $linea) {
                $copia = $linea->replicate(['documento_id', 'created_by']);
                $copia->documento_id = $borrador->id;
                $copia->created_by = $usuario->email;
                $copia->save();
            }

            return $borrador;
        });

        return redirect()->route('admin.cxp.facturas.edit', $borrador)
            ->with('status', "Editando {$borrador->numero} en una nueva versión borrador (la anterior quedó anulada). Aplica tus cambios y vuelve a contabilizar.");
    }

    /**
     * Acciones masivas sobre las facturas marcadas en la lista.
     * Reutiliza las mismas guardas y transacciones que las acciones individuales:
     * contabilizar/eliminar solo aplican a BORRADOR; anular solo a contabilizadas
     * sin pagos aplicados. Cada documento se procesa de forma aislada (su propia
     * transacción) para que un caso inválido no bloquee a los demás.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'accion' => ['required', Rule::in(['contabilizar', 'eliminar', 'anular'])],
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer'],
        ]);

        $companiaId = $this->companiaActivaId($request);
        $usuario    = $request->user();

        $documentos = CxpDocumento::where('compania_id', $companiaId)
            ->whereIn('tipo_documento', CxpDocumento::tiposModulo())
            ->whereIn('id', $data['ids'])
            ->get();

        $ok = 0;
        $omitidas = 0;
        $errores = [];

        foreach ($documentos as $documento) {
            try {
                switch ($data['accion']) {
                    case 'contabilizar':
                        if (! $documento->esBorrador()) { $omitidas++; break; }
                        $documento->load(['proveedor', 'detalle']);
                        DB::transaction(fn () => $this->contabilizarFactura($documento, $usuario));
                        $ok++;
                        break;

                    case 'eliminar':
                        if (! $documento->esBorrador()) { $omitidas++; break; }
                        DB::transaction(function () use ($documento) {
                            $documento->detalle()->delete();
                            $documento->delete();
                        });
                        $ok++;
                        break;

                    case 'anular':
                        if ($documento->esBorrador() || $documento->esAnulado()) { $omitidas++; break; }
                        if ($documento->aplicacionesComoDestino()->exists()) {
                            $errores[] = "{$documento->numero}: tiene pagos aplicados (anula primero los pagos)";
                            break;
                        }
                        DB::transaction(function () use ($documento, $usuario) {
                            app(AsientoAutomatico::class)->anular($documento->asiento, $usuario);
                            $documento->update([
                                'estado' => CxpDocumento::ESTADO_ANULADO,
                                'saldo' => 0,
                                'updated_by' => $usuario->email,
                            ]);
                        });
                        $ok++;
                        break;
                }
            } catch (\Throwable $e) {
                $errores[] = "{$documento->numero}: ".$e->getMessage();
            }
        }

        $verbo = ['contabilizar' => 'Contabilizadas', 'eliminar' => 'Eliminadas', 'anular' => 'Anuladas'][$data['accion']];
        $mensaje = "{$verbo}: {$ok} de ".$documentos->count().' seleccionada(s).';
        if ($omitidas > 0) {
            $detalle = $data['accion'] === 'anular'
                ? 'ya estaban anuladas o aún en borrador'
                : 'no estaban en borrador';
            $mensaje .= " Omitidas {$omitidas} ({$detalle}).";
        }

        $redirect = redirect()->route('admin.cxp.facturas.index')->with('status', $mensaje);
        if (! empty($errores)) {
            $redirect->withErrors(['bulk' => 'No se procesaron: '.implode('; ', array_slice($errores, 0, 10))]);
        }

        return $redirect;
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
