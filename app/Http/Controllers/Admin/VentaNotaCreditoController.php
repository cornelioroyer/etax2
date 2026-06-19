<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxcDocumento;
use App\Models\VentaFactura;
use App\Models\VentaNotaCredito;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VentaNotaCreditoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'cliente_id' => ['nullable', 'integer'],
            'desde'      => ['nullable', 'date'],
            'hasta'      => ['nullable', 'date'],
            'estado'     => ['nullable', 'string'],
        ]);

        $notas = VentaNotaCredito::with('cliente')
            ->where('compania_id', $companiaId)
            ->when($filtros['cliente_id'] ?? null, fn ($q, $v) => $q->where('cliente_id', $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->orderByDesc('fecha')
            ->orderByDesc('numero')
            ->paginate(25)->withQueryString();

        $clientes = Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('admin.ventas.notas-credito.index', compact('notas', 'filtros', 'clientes'));
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);
        $clienteId  = $request->integer('cliente_id') ?: null;

        $facturas = $clienteId
            ? VentaFactura::where('compania_id', $companiaId)
                ->where('cliente_id', $clienteId)
                ->whereNotIn('estado', [VentaFactura::ESTADO_ANULADA])
                ->orderBy('fecha')
                ->get(['id', 'numero', 'fecha', 'total', 'saldo'])
            : collect();

        $clientes = Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        $cuentasVenta = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $cuentaVentaId = CuentaDefault::idPara($companiaId, 'VENTAS');

        return view('admin.ventas.notas-credito.create', compact(
            'clientes', 'clienteId', 'facturas', 'cuentasVenta', 'cuentaVentaId'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario    = $request->user();

        $data = $request->validate([
            'cliente_id'     => ['required', 'integer'],
            'fecha'          => ['required', 'date'],
            'motivo'         => ['required', 'string', 'max:500'],
            'total'          => ['required', 'numeric', 'min:0.01'],
            'cuenta_id'      => ['required', 'integer', 'exists:cgl_cuentas,id'],
            'factura_id'     => ['nullable', 'integer'],
            'tipo_fel'       => ['nullable', 'in:04,06'],
        ]);

        $total       = round((float) $data['total'], 2);
        $cuentaCxcId = CuentaDefault::idPara($companiaId, 'CXC');
        // Código DGI: 04 si referencia una factura, 06 (genérica) si no.
        $tipoFel     = $data['tipo_fel'] ?? (! empty($data['factura_id']) ? '04' : '06');

        $nota = DB::transaction(function () use ($companiaId, $data, $total, $cuentaCxcId, $tipoFel, $usuario) {
            // Crear CxcDocumento de nota crédito
            $cxcNota = CxcDocumento::create([
                'compania_id'    => $companiaId,
                'cliente_id'     => $data['cliente_id'],
                'tipo_documento' => CxcDocumento::TIPO_NOTA_CREDITO,
                'numero'         => CxcDocumento::siguienteNumero($companiaId, CxcDocumento::TIPO_NOTA_CREDITO),
                'fecha'          => $data['fecha'],
                'subtotal'       => $total,
                'impuesto'       => 0,
                'total'          => $total,
                'saldo'          => $total,
                'estado'         => CxcDocumento::ESTADO_PENDIENTE,
                'created_by'     => $usuario->email,
            ]);

            $nota = VentaNotaCredito::create([
                'compania_id'    => $companiaId,
                'cliente_id'     => $data['cliente_id'],
                'numero'         => VentaNotaCredito::siguienteNumero($companiaId),
                'fecha'          => $data['fecha'],
                'motivo'         => $data['motivo'],
                'total'          => $total,
                'cxc_documento_id' => $cxcNota->id,
                'estado'         => VentaNotaCredito::ESTADO_EMITIDA,
                'extra'          => ['tipo_fel' => $tipoFel],
                'created_by'     => $usuario->email,
                'updated_by'     => $usuario->email,
            ]);

            // Si se vincula a una factura, aplicar automáticamente
            if (! empty($data['factura_id'])) {
                $factura = VentaFactura::where('compania_id', $companiaId)
                    ->where('id', $data['factura_id'])
                    ->lockForUpdate()->first();

                if ($factura && $factura->saldo > 0) {
                    $montoAplicar = min($total, (float) $factura->saldo);

                    if ($factura->cxc_documento_id) {
                        $cxcFactura = $factura->cxcDocumento()->lockForUpdate()->first();
                        if ($cxcFactura) {
                            $nuevoSaldo = round((float) $cxcFactura->saldo - $montoAplicar, 2);
                            $cxcFactura->update([
                                'saldo'      => max(0, $nuevoSaldo),
                                'estado'     => $nuevoSaldo <= 0 ? CxcDocumento::ESTADO_PAGADO : CxcDocumento::ESTADO_PARCIAL,
                                'updated_by' => $usuario->email,
                            ]);
                        }
                    }

                    $nuevoSaldo = round((float) $factura->saldo - $montoAplicar, 2);
                    $factura->saldo      = max(0, $nuevoSaldo);
                    $factura->estado     = $nuevoSaldo <= 0 ? VentaFactura::ESTADO_PAGADA : VentaFactura::ESTADO_PARCIAL;
                    $factura->updated_by = $usuario->email;
                    $factura->save();

                    // Reducir el saldo de la nota (ya aplicada)
                    $saldoNC = round($total - $montoAplicar, 2);
                    $cxcNota->update([
                        'saldo'  => $saldoNC,
                        'estado' => $saldoNC <= 0 ? CxcDocumento::ESTADO_PAGADO : CxcDocumento::ESTADO_PENDIENTE,
                    ]);
                    $nota->update(['estado' => $saldoNC <= 0
                        ? VentaNotaCredito::ESTADO_APLICADA
                        : VentaNotaCredito::ESTADO_EMITIDA]);
                }
            }

            // Asiento: DB CxC (reduce deuda cliente), CR Ventas (devuelve ingreso)
            $nombreCliente = Contacto::find($data['cliente_id'])?->nombre ?? '';
            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId,
                $data['fecha'],
                "NC Ventas {$nota->numero} — {$nombreCliente}",
                $nota->numero,
                [
                    [
                        'cuenta_id'   => (int) $data['cuenta_id'],
                        'descripcion' => "Nota crédito {$nota->numero}",
                        'debito'      => $total,
                        'credito'     => 0,
                    ],
                    [
                        'cuenta_id'   => $cuentaCxcId,
                        'contacto_id' => (int) $data['cliente_id'],
                        'descripcion' => "Nota crédito {$nota->numero}",
                        'debito'      => 0,
                        'credito'     => $total,
                    ],
                ],
                'VENTAS',
                'ventas_facturas',
                $nota->id,
                $usuario,
            );

            $nota->update(['asiento_id' => $asiento->id]);

            return $nota;
        });

        return redirect()->route('admin.ventas.notas-credito.show', $nota)
            ->with('status', "Nota de crédito {$nota->numero} emitida por B/. " . number_format($total, 2) . '.');
    }

    public function show(Request $request, VentaNotaCredito $notaCredito): View
    {
        abort_unless($notaCredito->compania_id === $this->companiaActivaId($request), 404);

        $notaCredito->load(['cliente', 'asiento', 'cxcDocumento']);

        return view('admin.ventas.notas-credito.show', ['nota' => $notaCredito]);
    }

    public function anular(Request $request, VentaNotaCredito $notaCredito): RedirectResponse
    {
        abort_unless($notaCredito->compania_id === $this->companiaActivaId($request), 404);

        if ($notaCredito->esAnulada()) {
            return back()->withErrors(['nota' => 'La nota de crédito ya está anulada.']);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($notaCredito, $usuario) {
            if ($notaCredito->asiento) {
                app(AsientoAutomatico::class)->anular($notaCredito->asiento, $usuario);
            }

            if ($notaCredito->cxcDocumento) {
                $notaCredito->cxcDocumento->update([
                    'estado'     => CxcDocumento::ESTADO_ANULADO,
                    'saldo'      => 0,
                    'updated_by' => $usuario->email,
                ]);
            }

            $notaCredito->update([
                'estado'     => VentaNotaCredito::ESTADO_ANULADA,
                'updated_by' => $usuario->email,
            ]);
        });

        return redirect()->route('admin.ventas.notas-credito.show', $notaCredito)
            ->with('status', "Nota {$notaCredito->numero} anulada.");
    }
}
