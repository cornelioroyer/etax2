<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxcDocumento;
use App\Models\CxcDocumentoDetalle;
use App\Services\AsientoAutomatico;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CxcFacturaController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    /** Tasas ITBMS de Panamá aceptadas por línea. */
    public const TASAS_ITBMS = [0, 7, 10, 15];

    public function index(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in(['PENDIENTE', 'PARCIAL', 'PAGADO', 'ANULADO'])],
            'cliente_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $consulta = CxcDocumento::query()
            ->with('cliente')
            ->where('compania_id', $companiaId)
            ->where('tipo_documento', CxcDocumento::TIPO_FACTURA)
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['cliente_id'] ?? null, fn ($q, $cliente) => $q->where('cliente_id', $cliente))
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->when($filtros['q'] ?? null, function ($q, $texto) {
                $busqueda = '%'.mb_strtolower($texto).'%';
                $q->where(function ($q) use ($busqueda) {
                    $q->whereRaw('LOWER(numero) LIKE ?', [$busqueda])
                        ->orWhereHas('cliente', fn ($c) => $c->whereRaw('LOWER(nombre) LIKE ?', [$busqueda]));
                });
            })
            ->orderByDesc('fecha')
            ->orderByDesc('numero');

        if ($request->query('export')) {
            $todas = (clone $consulta)->get();

            if ($export = $this->exportarReporte($request, 'admin.exports.listado', [
                'titulo' => 'Facturas por cobrar',
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
                    $f->cliente->nombre ?? '', number_format((float) $f->total, 2),
                    number_format((float) $f->saldo, 2), ucfirst(strtolower($f->estado)),
                ])->all(),
                'totales' => ['TOTAL', '', '', '',
                    number_format((float) $todas->sum('total'), 2),
                    number_format((float) $todas->sum('saldo'), 2), ''],
            ], 'facturas_cxc_'.now()->format('Y-m-d'))) {
                return $export;
            }
        }

        $facturas = $consulta->paginate(25)->withQueryString();

        $clientes = $this->clientes($companiaId);

        $totales = CxcDocumento::where('compania_id', $companiaId)
            ->where('tipo_documento', CxcDocumento::TIPO_FACTURA)
            ->where('estado', '!=', CxcDocumento::ESTADO_ANULADO)
            ->selectRaw('COALESCE(SUM(saldo), 0) AS saldo')
            ->value('saldo');

        return view('admin.cxc.facturas.index', [
            'facturas' => $facturas,
            'filtros' => $filtros,
            'clientes' => $clientes,
            'saldoTotal' => (float) $totales,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.cxc.facturas.create', $this->datosFormulario($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario = $request->user();

        $data = $request->validate([
            'cliente_id' => [
                'required', 'integer',
                Rule::exists('contact_contactos', 'id')->where('compania_id', $companiaId),
            ],
            'fecha' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.descripcion' => ['required', 'string', 'max:500'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'gte:0', 'max:999999999'],
            'lineas.*.tasa_itbms' => ['required', 'integer', Rule::in(self::TASAS_ITBMS)],
            'lineas.*.cuenta_id' => [
                'required', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
        ]);

        $cuentaCxcId = CuentaDefault::idPara($companiaId, 'CXC');
        $cuentaItbmsId = CuentaDefault::idPara($companiaId, 'ITBMS_POR_PAGAR');

        if (! $cuentaCxcId) {
            throw ValidationException::withMessages([
                'cliente_id' => 'La compañía no tiene configurada la cuenta default CXC. Configúrala antes de facturar.',
            ]);
        }

        $lineas = [];
        $subtotal = 0.0;
        $impuesto = 0.0;

        foreach (array_values($data['lineas']) as $i => $linea) {
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
            throw ValidationException::withMessages(['lineas' => 'El total debe ser mayor que cero.']);
        }

        if ($impuesto > 0 && ! $cuentaItbmsId) {
            throw ValidationException::withMessages([
                'lineas' => 'La compañía no tiene configurada la cuenta default ITBMS_POR_PAGAR.',
            ]);
        }

        $factura = DB::transaction(function () use ($companiaId, $data, $lineas, $subtotal, $impuesto, $total, $cuentaCxcId, $cuentaItbmsId, $usuario) {
            $factura = CxcDocumento::create([
                'compania_id' => $companiaId,
                'cliente_id' => $data['cliente_id'],
                'tipo_documento' => CxcDocumento::TIPO_FACTURA,
                'numero' => CxcDocumento::siguienteNumero($companiaId, CxcDocumento::TIPO_FACTURA),
                'fecha' => $data['fecha'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'subtotal' => $subtotal,
                'descuento' => 0,
                'impuesto' => $impuesto,
                'total' => $total,
                'saldo' => $total,
                'estado' => CxcDocumento::ESTADO_PENDIENTE,
                'created_by' => $usuario->email,
            ]);

            foreach ($lineas as $linea) {
                CxcDocumentoDetalle::create($linea + ['documento_id' => $factura->id, 'created_by' => $usuario->email]);
            }

            $lineasAsiento = [[
                'cuenta_id' => $cuentaCxcId,
                'contacto_id' => (int) $data['cliente_id'],
                'descripcion' => "Factura {$factura->numero}",
                'debito' => $total,
                'credito' => 0,
            ]];

            foreach ($lineas as $linea) {
                $base = round($linea['total_linea'] - $linea['impuesto_monto'], 2);
                $lineasAsiento[] = [
                    'cuenta_id' => $linea['cuenta_id'],
                    'descripcion' => $linea['descripcion'],
                    'debito' => 0,
                    'credito' => $base,
                ];
            }

            if ($impuesto > 0) {
                $lineasAsiento[] = [
                    'cuenta_id' => $cuentaItbmsId,
                    'descripcion' => "ITBMS factura {$factura->numero}",
                    'debito' => 0,
                    'credito' => $impuesto,
                ];
            }

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId, $data['fecha'],
                "Factura de venta {$factura->numero} — ".$factura->cliente->nombre,
                $factura->numero, $lineasAsiento, 'CXC', 'cxc_documentos', $factura->id, $usuario,
            );

            $factura->update(['asiento_id' => $asiento->id]);

            return $factura;
        });

        return redirect()->route('admin.cxc.facturas.show', $factura)
            ->with('status', "Factura {$factura->numero} registrada y contabilizada.");
    }

    public function show(Request $request, CxcDocumento $documento): View
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($documento->tipo_documento === CxcDocumento::TIPO_FACTURA, 404);

        $documento->load(['cliente', 'detalle.cuenta', 'asiento', 'aplicacionesComoDestino.origen']);

        return view('admin.cxc.facturas.show', ['factura' => $documento]);
    }

    public function anular(Request $request, CxcDocumento $documento): RedirectResponse
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($documento->tipo_documento === CxcDocumento::TIPO_FACTURA, 404);

        if ($documento->esAnulado()) {
            return back()->withErrors(['documento' => 'La factura ya está anulada.']);
        }

        if ($documento->aplicacionesComoDestino()->exists()) {
            return back()->withErrors(['documento' => 'La factura tiene cobros aplicados; anula primero los cobros.']);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($documento, $usuario) {
            app(AsientoAutomatico::class)->anular($documento->asiento, $usuario);

            $documento->update([
                'estado' => CxcDocumento::ESTADO_ANULADO,
                'saldo' => 0,
                'updated_by' => $usuario->email,
            ]);
        });

        return redirect()->route('admin.cxc.facturas.show', $documento)
            ->with('status', "Factura {$documento->numero} anulada.");
    }

    private function datosFormulario(Request $request): array
    {
        $companiaId = $this->companiaActivaId($request);

        return [
            'clientes' => $this->clientes($companiaId),
            'cuentas' => CuentaContable::where('compania_id', $companiaId)
                ->where('permite_movimiento', true)
                ->where('activa', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
            'cuentaVentasId' => CuentaDefault::idPara($companiaId, 'VENTAS'),
        ];
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
