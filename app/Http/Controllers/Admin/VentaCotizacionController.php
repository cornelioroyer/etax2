<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\TaxImpuesto;
use App\Models\VentaCotizacion;
use App\Models\VentaCotizacionDetalle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'q'          => ['nullable', 'string', 'max:100'],
        ]);

        $consulta = VentaCotizacion::query()
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

    private function clientes(int $companiaId)
    {
        return Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);
    }
}
