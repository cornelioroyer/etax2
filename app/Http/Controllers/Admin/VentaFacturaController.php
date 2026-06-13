<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\VentaCotizacion;
use App\Models\VentaFactura;
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

    public function index(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'estado'     => ['nullable', Rule::in(['BORRADOR', 'EMITIDA', 'PARCIAL', 'PAGADA', 'ANULADA'])],
            'cliente_id' => ['nullable', 'integer'],
            'desde'      => ['nullable', 'date'],
            'hasta'      => ['nullable', 'date'],
            'q'          => ['nullable', 'string', 'max:100'],
        ]);

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
            ->orderByDesc('fecha')
            ->orderByDesc('numero');

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
        ]);
    }

    public function show(Request $request, VentaFactura $factura): View
    {
        abort_unless($factura->compania_id === $this->companiaActivaId($request), 404);

        $factura->load(['cliente', 'detalle.impuesto', 'detalle.cuentaIngreso', 'asiento', 'cotizacion', 'cxcDocumento']);

        return view('admin.ventas.facturas.show', ['factura' => $factura]);
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

    private function clientes(int $companiaId)
    {
        return Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);
    }
}
