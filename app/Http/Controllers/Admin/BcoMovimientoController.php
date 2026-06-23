<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\BcoCuenta;
use App\Models\BcoMovimiento;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Services\AsientoAutomatico;
use App\Services\BancoSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BcoMovimientoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'cuenta_id' => ['nullable', 'integer'],
            'tipo'      => ['nullable', 'string'],
            'desde'     => ['nullable', 'date'],
            'hasta'     => ['nullable', 'date'],
            'q'         => ['nullable', 'string', 'max:100'],
        ]);

        $movimientos = BcoMovimiento::with('cuenta.banco', 'contacto')
            ->where('compania_id', $companiaId)
            ->when($filtros['cuenta_id'] ?? null, fn ($q, $v) => $q->where('cuenta_bancaria_id', $v))
            ->when($filtros['tipo'] ?? null, fn ($q, $v) => $q->where('tipo_movimiento', $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->where(
                fn ($q) => $q->whereRaw('LOWER(descripcion) LIKE ?', ['%' . mb_strtolower($texto) . '%'])
                    ->orWhereRaw('LOWER(referencia) LIKE ?', ['%' . mb_strtolower($texto) . '%'])
            ))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30)->withQueryString();

        $cuentas = BcoCuenta::where('compania_id', $companiaId)
            ->where('activa', true)
            ->with('banco')
            ->orderBy('nombre')
            ->get();

        return view('admin.bco.movimientos.index', compact('movimientos', 'filtros', 'cuentas'));
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $cuentas = BcoCuenta::where('compania_id', $companiaId)
            ->where('activa', true)
            ->with('banco')
            ->orderBy('nombre')
            ->get();

        $contactos = Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        $cuentasContables = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        return view('admin.bco.movimientos.create', compact('cuentas', 'contactos', 'cuentasContables'));
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario    = $request->user();

        $data = $request->validate([
            'cuenta_bancaria_id' => ['required', 'integer', 'exists:bco_cuentas,id'],
            'fecha'              => ['required', 'date'],
            'tipo_movimiento'    => ['required', 'string'],
            'descripcion'        => ['required', 'string', 'max:500'],
            'referencia'         => ['nullable', 'string', 'max:100'],
            'debito'             => ['nullable', 'numeric', 'min:0'],
            'credito'            => ['nullable', 'numeric', 'min:0'],
            'contacto_id'        => ['nullable', 'integer'],
            'cuenta_contable_id' => ['nullable', 'integer', 'exists:cgl_cuentas,id'],
        ]);

        $debito  = round((float) ($data['debito'] ?? 0), 2);
        $credito = round((float) ($data['credito'] ?? 0), 2);

        if ($debito <= 0 && $credito <= 0) {
            return back()->withErrors(['debito' => 'Indica un monto de débito o crédito mayor a cero.'])->withInput();
        }

        $cuenta = BcoCuenta::where('compania_id', $companiaId)->findOrFail($data['cuenta_bancaria_id']);

        $movimiento = DB::transaction(function () use ($companiaId, $data, $debito, $credito, $cuenta, $usuario) {
            $movimiento = BcoMovimiento::create([
                'compania_id'        => $companiaId,
                'cuenta_bancaria_id' => $cuenta->id,
                'fecha'              => $data['fecha'],
                'tipo_movimiento'    => $data['tipo_movimiento'],
                'descripcion'        => $data['descripcion'],
                'referencia'         => $data['referencia'] ?? null,
                'debito'             => $debito,
                'credito'            => $credito,
                'contacto_id'        => $data['contacto_id'] ?? null,
                'conciliado'         => false,
                'created_by'         => $usuario->email,
                'updated_by'         => $usuario->email,
            ]);

            // Asiento automático si se indica cuenta contable contraparte
            if (! empty($data['cuenta_contable_id']) && $cuenta->cuenta_contable_id) {
                $monto = max($debito, $credito);

                $lineas = $debito > 0
                    ? [
                        ['cuenta_id' => $cuenta->cuenta_contable_id, 'descripcion' => $data['descripcion'], 'debito' => $monto, 'credito' => 0],
                        ['cuenta_id' => (int) $data['cuenta_contable_id'], 'descripcion' => $data['descripcion'], 'debito' => 0, 'credito' => $monto],
                    ]
                    : [
                        ['cuenta_id' => (int) $data['cuenta_contable_id'], 'descripcion' => $data['descripcion'], 'debito' => $monto, 'credito' => 0],
                        ['cuenta_id' => $cuenta->cuenta_contable_id, 'descripcion' => $data['descripcion'], 'debito' => 0, 'credito' => $monto],
                    ];

                if (! empty($data['contacto_id'])) {
                    $lineas[1]['contacto_id'] = (int) $data['contacto_id'];
                }

                $asiento = app(AsientoAutomatico::class)->postear(
                    $companiaId,
                    $data['fecha'],
                    $data['descripcion'],
                    $data['referencia'] ?? $movimiento->id,
                    $lineas,
                    'BANCOS',
                    'bco_movimientos',
                    $movimiento->id,
                    $usuario,
                );

                $movimiento->update(['asiento_id' => $asiento->id]);
            }

            return $movimiento;
        });

        return redirect()->route('admin.bco.movimientos.show', $movimiento)
            ->with('status', 'Movimiento registrado.');
    }

    public function show(Request $request, BcoMovimiento $movimiento): View
    {
        abort_unless($movimiento->compania_id === $this->companiaActivaId($request), 404);

        $movimiento->load('cuenta.banco', 'contacto', 'asiento');

        return view('admin.bco.movimientos.show', compact('movimiento'));
    }

    /**
     * Anula un movimiento bancario manual: revierte su asiento (si lo tiene) y
     * lo retira del ledger del banco. Solo aplica a movimientos manuales; los
     * reflejos de un asiento de otro módulo (BancoSync) o un movimiento ya
     * conciliado no se tocan aquí.
     */
    public function anular(Request $request, BcoMovimiento $movimiento): RedirectResponse
    {
        abort_unless($movimiento->compania_id === $this->companiaActivaId($request), 404);

        if ($motivo = $this->motivoBloqueo($movimiento)) {
            return back()->withErrors(['movimiento' => $motivo]);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($movimiento, $usuario) {
            if ($movimiento->asiento) {
                app(AsientoAutomatico::class)->anular($movimiento->asiento, $usuario);
            }

            $movimiento->delete();
        });

        return redirect()->route('admin.bco.movimientos.index')
            ->with('status', 'Movimiento eliminado'.($movimiento->asiento_id ? ' y su asiento anulado' : '').'.');
    }

    /**
     * "Editar" un movimiento bancario manual: lo anula (revierte el asiento y
     * lo retira del ledger) y reabre el formulario de captura pre-llenado para
     * registrar la corrección como un movimiento nuevo.
     */
    public function editar(Request $request, BcoMovimiento $movimiento): RedirectResponse
    {
        abort_unless($movimiento->compania_id === $this->companiaActivaId($request), 404);

        if ($motivo = $this->motivoBloqueo($movimiento)) {
            return back()->withErrors(['movimiento' => $motivo]);
        }

        $usuario = $request->user();
        $movimiento->load('asiento.detalle', 'cuenta');

        // Contrapartida = línea del asiento cuya cuenta NO es la cuenta contable
        // del banco; así se recupera la cuenta para el asiento automático.
        $cuentaContableId = null;
        if ($movimiento->asiento) {
            $glBanco = $movimiento->cuenta?->cuenta_contable_id;
            $cuentaContableId = $movimiento->asiento->detalle
                ->first(fn ($d) => $d->cuenta_id !== $glBanco)?->cuenta_id;
        }

        $old = [
            'cuenta_bancaria_id' => $movimiento->cuenta_bancaria_id,
            'fecha'              => $movimiento->fecha->format('Y-m-d'),
            'tipo_movimiento'    => $movimiento->tipo_movimiento,
            'descripcion'        => $movimiento->descripcion,
            'referencia'         => $movimiento->referencia,
            'debito'             => number_format((float) $movimiento->debito, 2, '.', ''),
            'credito'            => number_format((float) $movimiento->credito, 2, '.', ''),
            'contacto_id'        => $movimiento->contacto_id,
            'cuenta_contable_id' => $cuentaContableId,
        ];

        DB::transaction(function () use ($movimiento, $usuario) {
            if ($movimiento->asiento) {
                app(AsientoAutomatico::class)->anular($movimiento->asiento, $usuario);
            }

            $movimiento->delete();
        });

        return redirect()->route('admin.bco.movimientos.create')
            ->withInput($old)
            ->with('status', 'Movimiento anulado para corrección. Ajusta los datos y regístralo de nuevo (se crea uno nuevo; el anterior queda fuera del ledger y su asiento anulado).');
    }

    /**
     * Razón por la que un movimiento no se puede anular/editar aquí, o null si
     * sí se puede. Los movimientos conciliados y los reflejos de asientos de
     * otros módulos (BancoSync) quedan protegidos.
     */
    private function motivoBloqueo(BcoMovimiento $movimiento): ?string
    {
        if ($movimiento->conciliado) {
            return 'El movimiento está conciliado; reabre la conciliación antes de modificarlo.';
        }

        if ($movimiento->documento_origen === BancoSync::ORIGEN) {
            return 'Este movimiento refleja un asiento de otro módulo; corrígelo desde su documento de origen, no desde Bancos.';
        }

        return null;
    }
}
