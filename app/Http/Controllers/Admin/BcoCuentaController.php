<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\BcoBanco;
use App\Models\BcoCuenta;
use App\Models\CuentaContable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BcoCuentaController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $cuentas = BcoCuenta::with('banco', 'cuentaContable')
            ->where('compania_id', $companiaId)
            ->orderBy('activa', 'desc')
            ->get();

        $bancos = BcoBanco::where('activo', true)->orderBy('nombre')->get();

        $cuentasContables = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        return view('admin.bco.cuentas.index', compact('cuentas', 'bancos', 'cuentasContables'));
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'banco_id'          => ['required', 'integer', 'exists:bco_bancos,id'],
            'numero_cuenta'     => ['required', 'string', 'max:100'],
            'nombre'            => ['required', 'string', 'max:150'],
            'tipo_cuenta'       => ['required', 'string', 'in:CORRIENTE,AHORROS,INVERSION'],
            'cuenta_contable_id' => ['nullable', 'integer', 'exists:cgl_cuentas,id'],
            'saldo_inicial'     => ['nullable', 'numeric', 'min:0'],
        ]);

        $existe = BcoCuenta::where('compania_id', $companiaId)
            ->where('numero_cuenta', $data['numero_cuenta'])
            ->exists();

        if ($existe) {
            return back()->withErrors(['numero_cuenta' => 'Ya existe una cuenta con ese número.'])->withInput();
        }

        BcoCuenta::create([
            'compania_id'        => $companiaId,
            'banco_id'           => $data['banco_id'],
            'numero_cuenta'      => $data['numero_cuenta'],
            'nombre'             => $data['nombre'],
            'tipo_cuenta'        => $data['tipo_cuenta'],
            'cuenta_contable_id' => $data['cuenta_contable_id'] ?? null,
            'saldo_inicial'      => $data['saldo_inicial'] ?? 0,
            'activa'             => true,
            'created_by'         => $request->user()->email,
            'updated_by'         => $request->user()->email,
        ]);

        return back()->with('status', "Cuenta {$data['numero_cuenta']} — {$data['nombre']} creada.");
    }

    public function update(Request $request, BcoCuenta $cuenta): RedirectResponse
    {
        abort_unless($cuenta->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'banco_id'           => ['required', 'integer', 'exists:bco_bancos,id'],
            'nombre'             => ['required', 'string', 'max:150'],
            'tipo_cuenta'        => ['required', 'string', 'in:CORRIENTE,AHORROS,INVERSION'],
            'cuenta_contable_id' => ['nullable', 'integer', 'exists:cgl_cuentas,id'],
            'saldo_inicial'      => ['nullable', 'numeric', 'min:0'],
        ]);

        $cuenta->update([
            'banco_id'           => $data['banco_id'],
            'nombre'             => $data['nombre'],
            'tipo_cuenta'        => $data['tipo_cuenta'],
            'cuenta_contable_id' => $data['cuenta_contable_id'] ?? null,
            'saldo_inicial'      => $data['saldo_inicial'] ?? 0,
            'updated_by'         => $request->user()->email,
        ]);

        return back()->with('status', "Cuenta {$cuenta->numero_cuenta} actualizada.");
    }

    public function show(Request $request, BcoCuenta $cuenta): View
    {
        abort_unless($cuenta->compania_id === $this->companiaActivaId($request), 404);

        $cuenta->load('banco', 'cuentaContable');

        $movimientos = $cuenta->movimientos()
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(30);

        // Calcular saldo acumulado por movimiento (de más antiguo a más nuevo)
        return view('admin.bco.cuentas.show', compact('cuenta', 'movimientos'));
    }

    public function toggle(Request $request, BcoCuenta $cuenta): RedirectResponse
    {
        abort_unless($cuenta->compania_id === $this->companiaActivaId($request), 404);

        $cuenta->update(['activa' => ! $cuenta->activa, 'updated_by' => $request->user()->email]);

        return back()->with('status', "Cuenta {$cuenta->numero_cuenta} " . ($cuenta->activa ? 'activada' : 'desactivada') . '.');
    }
}
