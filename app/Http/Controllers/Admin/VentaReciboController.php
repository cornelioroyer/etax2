<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxcAplicacion;
use App\Models\CxcDocumento;
use App\Models\VentaFactura;
use App\Models\VentaRecibo;
use App\Models\VentaReciboDetalle;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VentaReciboController extends Controller
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

        $recibos = VentaRecibo::with('cliente')
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

        return view('admin.ventas.recibos.index', compact('recibos', 'filtros', 'clientes'));
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);
        $clienteId  = $request->integer('cliente_id') ?: null;

        $facturasPendientes = $clienteId
            ? VentaFactura::where('compania_id', $companiaId)
                ->where('cliente_id', $clienteId)
                ->whereIn('estado', [VentaFactura::ESTADO_EMITIDA, VentaFactura::ESTADO_PARCIAL])
                ->where('saldo', '>', 0)
                ->orderBy('fecha')
                ->get()
            : collect();

        $clientes = Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        $cuentasCobro = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $cuentaBancoId = CuentaDefault::idPara($companiaId, 'BANCO_DEFAULT')
            ?? CuentaDefault::idPara($companiaId, 'CAJA_DEFAULT');

        return view('admin.ventas.recibos.create', compact(
            'clientes', 'clienteId', 'facturasPendientes', 'cuentasCobro', 'cuentaBancoId'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario    = $request->user();

        $data = $request->validate([
            'cliente_id'      => ['required', 'integer'],
            'fecha'           => ['required', 'date'],
            'metodo_pago'     => ['nullable', 'string', 'max:50'],
            'cuenta_cobro_id' => ['required', 'integer', 'exists:cgl_cuentas,id'],
            'referencia'      => ['nullable', 'string', 'max:100'],
            'facturas'        => ['required', 'array', 'min:1'],
            'facturas.*.id'   => ['required', 'integer'],
            'facturas.*.monto' => ['required', 'numeric', 'min:0'],
        ]);

        $aplicar = collect($data['facturas'])
            ->map(fn ($f) => ['factura_id' => (int) $f['id'], 'monto' => round((float) $f['monto'], 2)])
            ->filter(fn ($f) => $f['monto'] > 0)
            ->values();

        if ($aplicar->isEmpty()) {
            throw ValidationException::withMessages(['facturas' => 'Indica el monto a cobrar en al menos una factura.']);
        }

        // Cargar y validar facturas
        $facturas = VentaFactura::where('compania_id', $companiaId)
            ->where('cliente_id', $data['cliente_id'])
            ->whereIn('estado', [VentaFactura::ESTADO_EMITIDA, VentaFactura::ESTADO_PARCIAL])
            ->whereIn('id', $aplicar->pluck('factura_id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($aplicar as $apl) {
            $factura = $facturas->get($apl['factura_id']);
            if (! $factura) {
                throw ValidationException::withMessages(['facturas' => 'Una de las facturas no pertenece al cliente.']);
            }
            if ($apl['monto'] > round((float) $factura->saldo, 2) + 0.004) {
                throw ValidationException::withMessages([
                    'facturas' => "Monto en {$factura->numero} (B/. {$apl['monto']}) excede el saldo (B/. {$factura->saldo}).",
                ]);
            }
        }

        $total = round($aplicar->sum('monto'), 2);

        $cuentaCxcId = CuentaDefault::idPara($companiaId, 'CXC');

        $recibo = DB::transaction(function () use ($companiaId, $data, $aplicar, $facturas, $total, $cuentaCxcId, $usuario) {
            // Crear el VentaRecibo
            $recibo = VentaRecibo::create([
                'compania_id' => $companiaId,
                'cliente_id'  => $data['cliente_id'],
                'numero'      => VentaRecibo::siguienteNumero($companiaId),
                'fecha'       => $data['fecha'],
                'metodo_pago' => $data['metodo_pago'] ?? null,
                'total'       => $total,
                'estado'      => VentaRecibo::ESTADO_APLICADO,
                'created_by'  => $usuario->email,
                'updated_by'  => $usuario->email,
            ]);

            // Crear el CxcDocumento de pago vinculado
            $cobro = CxcDocumento::create([
                'compania_id'    => $companiaId,
                'cliente_id'     => $data['cliente_id'],
                'tipo_documento' => CxcDocumento::TIPO_PAGO,
                'numero'         => CxcDocumento::siguienteNumero($companiaId, CxcDocumento::TIPO_PAGO),
                'fecha'          => $data['fecha'],
                'subtotal'       => $total,
                'impuesto'       => 0,
                'total'          => $total,
                'saldo'          => 0,
                'estado'         => CxcDocumento::ESTADO_PAGADO,
                'created_by'     => $usuario->email,
            ]);

            $recibo->update(['cxc_documento_id' => $cobro->id]);

            foreach ($aplicar as $apl) {
                $factura = $facturas->get($apl['factura_id']);

                VentaReciboDetalle::create([
                    'recibo_id'       => $recibo->id,
                    'factura_id'      => $factura->id,
                    'cxc_documento_id' => $factura->cxc_documento_id,
                    'monto'           => $apl['monto'],
                    'created_by'      => $usuario->email,
                    'updated_by'      => $usuario->email,
                ]);

                // Aplicar al CxcDocumento de la factura
                if ($factura->cxc_documento_id) {
                    CxcAplicacion::create([
                        'compania_id'         => $companiaId,
                        'cliente_id'          => $data['cliente_id'],
                        'documento_origen_id' => $cobro->id,
                        'documento_destino_id' => $factura->cxc_documento_id,
                        'fecha'               => $data['fecha'],
                        'monto_aplicado'      => $apl['monto'],
                        'created_by'          => $usuario->email,
                    ]);

                    $cxcDoc = $factura->cxcDocumento()->lockForUpdate()->first();
                    if ($cxcDoc) {
                        $nuevoSaldo = round((float) $cxcDoc->saldo - $apl['monto'], 2);
                        $cxcDoc->update([
                            'saldo'      => max(0, $nuevoSaldo),
                            'estado'     => $nuevoSaldo <= 0 ? CxcDocumento::ESTADO_PAGADO : CxcDocumento::ESTADO_PARCIAL,
                            'updated_by' => $usuario->email,
                        ]);
                    }
                }

                // Actualizar VentaFactura
                $nuevoSaldo = round((float) $factura->saldo - $apl['monto'], 2);
                $factura->saldo      = max(0, $nuevoSaldo);
                $factura->estado     = $nuevoSaldo <= 0 ? VentaFactura::ESTADO_PAGADA : VentaFactura::ESTADO_PARCIAL;
                $factura->updated_by = $usuario->email;
                $factura->save();
            }

            // Asiento contable
            $nombreCliente = Contacto::find($data['cliente_id'])?->nombre ?? '';
            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId,
                $data['fecha'],
                "Cobro {$recibo->numero} — {$nombreCliente}",
                $data['referencia'] ?? $recibo->numero,
                [
                    [
                        'cuenta_id'   => (int) $data['cuenta_cobro_id'],
                        'descripcion' => "Cobro {$recibo->numero}",
                        'debito'      => $total,
                        'credito'     => 0,
                    ],
                    [
                        'cuenta_id'   => $cuentaCxcId,
                        'contacto_id' => (int) $data['cliente_id'],
                        'descripcion' => "Cobro {$recibo->numero}",
                        'debito'      => 0,
                        'credito'     => $total,
                    ],
                ],
                'VENTAS',
                'ventas_recibos',
                $recibo->id,
                $usuario,
            );

            $recibo->update(['asiento_id' => $asiento->id]);
            $cobro->update(['asiento_id'  => $asiento->id]);

            return $recibo;
        });

        return redirect()->route('admin.ventas.recibos.show', $recibo)
            ->with('status', "Recibo {$recibo->numero} registrado por B/. " . number_format($total, 2) . '.');
    }

    public function show(Request $request, VentaRecibo $recibo): View
    {
        abort_unless($recibo->compania_id === $this->companiaActivaId($request), 404);

        $recibo->load(['cliente', 'asiento', 'detalle.factura', 'cxcDocumento']);

        return view('admin.ventas.recibos.show', compact('recibo'));
    }

    public function anular(Request $request, VentaRecibo $recibo): RedirectResponse
    {
        abort_unless($recibo->compania_id === $this->companiaActivaId($request), 404);

        if ($recibo->esAnulado()) {
            return back()->withErrors(['recibo' => 'El recibo ya está anulado.']);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($recibo, $usuario) {
            foreach ($recibo->detalle()->with('factura', 'cxcDocumento')->lockForUpdate()->get() as $det) {
                // Restaurar saldo factura
                $factura = $det->factura;
                if ($factura) {
                    $factura->saldo      = round((float) $factura->saldo + (float) $det->monto, 2);
                    $factura->estado     = $factura->saldo > 0
                        ? (round((float) $factura->saldo, 2) < round((float) $factura->total, 2) ? VentaFactura::ESTADO_PARCIAL : VentaFactura::ESTADO_EMITIDA)
                        : VentaFactura::ESTADO_PAGADA;
                    $factura->updated_by = $usuario->email;
                    $factura->save();
                }

                // Restaurar CxcDocumento saldo
                if ($det->cxcDocumento) {
                    $cxcDoc = $det->cxcDocumento;
                    $cxcDoc->saldo      = round((float) $cxcDoc->saldo + (float) $det->monto, 2);
                    $cxcDoc->estado     = CxcDocumento::ESTADO_PARCIAL;
                    $cxcDoc->updated_by = $usuario->email;
                    $cxcDoc->save();
                }
            }

            // Eliminar aplicaciones CxC
            if ($recibo->cxc_documento_id) {
                CxcAplicacion::where('documento_origen_id', $recibo->cxc_documento_id)->delete();
                $recibo->cxcDocumento?->update(['estado' => CxcDocumento::ESTADO_ANULADO, 'updated_by' => $usuario->email]);
            }

            if ($recibo->asiento) {
                app(AsientoAutomatico::class)->anular($recibo->asiento, $usuario);
            }

            $recibo->update(['estado' => VentaRecibo::ESTADO_ANULADO, 'updated_by' => $usuario->email]);
        });

        return redirect()->route('admin.ventas.recibos.show', $recibo)
            ->with('status', "Recibo {$recibo->numero} anulado; saldos restaurados.");
    }
}
