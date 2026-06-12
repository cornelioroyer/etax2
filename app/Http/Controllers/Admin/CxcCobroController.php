<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxcAplicacion;
use App\Models\CxcDocumento;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CxcCobroController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'cliente_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $cobros = CxcDocumento::query()
            ->with('cliente')
            ->where('compania_id', $companiaId)
            ->where('tipo_documento', CxcDocumento::TIPO_PAGO)
            ->when($filtros['cliente_id'] ?? null, fn ($q, $cliente) => $q->where('cliente_id', $cliente))
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('fecha')
            ->orderByDesc('numero')
            ->paginate(25)
            ->withQueryString();

        $clientes = Contacto::where('compania_id', $companiaId)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('admin.cxc.cobros.index', compact('cobros', 'filtros', 'clientes'));
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $clienteId = $request->integer('cliente_id') ?: null;

        $facturas = $clienteId
            ? CxcDocumento::where('compania_id', $companiaId)
                ->whereIn('tipo_documento', CxcDocumento::tiposCobrables())
                ->where('cliente_id', $clienteId)
                ->whereIn('estado', [CxcDocumento::ESTADO_PENDIENTE, CxcDocumento::ESTADO_PARCIAL])
                ->where('saldo', '>', 0)
                ->orderBy('fecha')
                ->get()
            : collect();

        return view('admin.cxc.cobros.create', [
            'clientes' => Contacto::where('compania_id', $companiaId)
                ->where('activo', true)
                ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
            'clienteId' => $clienteId,
            'facturas' => $facturas,
            'cuentasCobro' => CuentaContable::where('compania_id', $companiaId)
                ->where('permite_movimiento', true)
                ->where('activa', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
            'cuentaBancoId' => CuentaDefault::idPara($companiaId, 'BANCO_DEFAULT')
                ?? CuentaDefault::idPara($companiaId, 'CAJA_DEFAULT'),
        ]);
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
            'cuenta_cobro_id' => [
                'required', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
            'referencia' => ['nullable', 'string', 'max:100'],
            'aplicaciones' => ['required', 'array', 'min:1'],
            'aplicaciones.*.documento_id' => ['required', 'integer'],
            'aplicaciones.*.monto' => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
        ]);

        $cuentaCxcId = CuentaDefault::idPara($companiaId, 'CXC');

        if (! $cuentaCxcId) {
            throw ValidationException::withMessages([
                'cliente_id' => 'La compañía no tiene configurada la cuenta default CXC (Cuentas por Cobrar).',
            ]);
        }

        // Solo facturas con monto > 0
        $aplicar = collect($data['aplicaciones'])
            ->map(fn ($a) => ['documento_id' => (int) $a['documento_id'], 'monto' => round((float) ($a['monto'] ?? 0), 2)])
            ->filter(fn ($a) => $a['monto'] > 0)
            ->values();

        if ($aplicar->isEmpty()) {
            throw ValidationException::withMessages(['aplicaciones' => 'Indica el monto a cobrar en al menos una factura.']);
        }

        $facturas = CxcDocumento::where('compania_id', $companiaId)
            ->where('cliente_id', $data['cliente_id'])
            ->whereIn('tipo_documento', CxcDocumento::tiposCobrables())
            ->whereIn('id', $aplicar->pluck('documento_id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($aplicar as $i => $apl) {
            $factura = $facturas->get($apl['documento_id']);

            if (! $factura) {
                throw ValidationException::withMessages(['aplicaciones' => 'Una de las facturas no pertenece al cliente seleccionado.']);
            }

            if ($apl['monto'] > round((float) $factura->saldo, 2) + 0.004) {
                throw ValidationException::withMessages([
                    'aplicaciones' => "El monto aplicado a {$factura->numero} (B/. {$apl['monto']}) excede su saldo (B/. {$factura->saldo}).",
                ]);
            }
        }

        $total = round($aplicar->sum('monto'), 2);

        $cobro = DB::transaction(function () use ($companiaId, $data, $aplicar, $facturas, $total, $cuentaCxcId, $usuario) {
            $cobro = CxcDocumento::create([
                'compania_id' => $companiaId,
                'cliente_id' => $data['cliente_id'],
                'tipo_documento' => CxcDocumento::TIPO_PAGO,
                'numero' => CxcDocumento::siguienteNumero($companiaId, CxcDocumento::TIPO_PAGO),
                'fecha' => $data['fecha'],
                'subtotal' => $total,
                'impuesto' => 0,
                'total' => $total,
                'saldo' => 0,
                'estado' => CxcDocumento::ESTADO_PAGADO,
                'created_by' => $usuario->email,
            ]);

            foreach ($aplicar as $apl) {
                $factura = $facturas->get($apl['documento_id']);

                CxcAplicacion::create([
                    'compania_id' => $companiaId,
                    'cliente_id' => $data['cliente_id'],
                    'documento_origen_id' => $cobro->id,
                    'documento_destino_id' => $factura->id,
                    'fecha' => $data['fecha'],
                    'monto_aplicado' => $apl['monto'],
                    'created_by' => $usuario->email,
                ]);

                $factura->saldo = round((float) $factura->saldo - $apl['monto'], 2);
                $factura->estado = $factura->estadoSegunSaldo();
                $factura->updated_by = $usuario->email;
                $factura->save();
            }

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId,
                $data['fecha'],
                "Cobro {$cobro->numero} — ".$cobro->cliente->nombre,
                $data['referencia'] ?? $cobro->numero,
                [
                    [
                        'cuenta_id' => (int) $data['cuenta_cobro_id'],
                        'descripcion' => "Cobro {$cobro->numero}",
                        'debito' => $total,
                        'credito' => 0,
                    ],
                    [
                        'cuenta_id' => $cuentaCxcId,
                        'contacto_id' => (int) $data['cliente_id'],
                        'descripcion' => "Cobro {$cobro->numero}",
                        'debito' => 0,
                        'credito' => $total,
                    ],
                ],
                'CXC',
                'cxc_documentos',
                $cobro->id,
                $usuario,
            );

            $cobro->update(['asiento_id' => $asiento->id]);

            return $cobro;
        });

        return redirect()->route('admin.cxc.cobros.show', $cobro)
            ->with('status', "Cobro {$cobro->numero} registrado por B/. ".number_format($total, 2).'.');
    }

    public function show(Request $request, CxcDocumento $documento): View
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($documento->tipo_documento === CxcDocumento::TIPO_PAGO, 404);

        $documento->load(['cliente', 'asiento', 'aplicacionesComoOrigen.destino']);

        return view('admin.cxc.cobros.show', ['cobro' => $documento]);
    }

    public function anular(Request $request, CxcDocumento $documento): RedirectResponse
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($documento->tipo_documento === CxcDocumento::TIPO_PAGO, 404);

        if ($documento->esAnulado()) {
            return back()->withErrors(['documento' => 'El cobro ya está anulado.']);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($documento, $usuario) {
            // Devolver el saldo a cada factura aplicada
            foreach ($documento->aplicacionesComoOrigen()->with('destino')->lockForUpdate()->get() as $aplicacion) {
                $factura = $aplicacion->destino;
                $factura->saldo = round((float) $factura->saldo + (float) $aplicacion->monto_aplicado, 2);
                $factura->estado = $factura->estadoSegunSaldo();
                $factura->updated_by = $usuario->email;
                $factura->save();

                $aplicacion->delete();
            }

            app(AsientoAutomatico::class)->anular($documento->asiento, $usuario);

            $documento->update([
                'estado' => CxcDocumento::ESTADO_ANULADO,
                'saldo' => 0,
                'updated_by' => $usuario->email,
            ]);
        });

        return redirect()->route('admin.cxc.cobros.show', $documento)
            ->with('status', "Cobro {$documento->numero} anulado; los saldos de las facturas fueron restaurados.");
    }
}
