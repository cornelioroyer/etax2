<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BancoCuenta;
use App\Models\Compania;
use App\Models\CuentaContable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BancoCuentaController extends Controller
{
    public function index(Request $request): View
    {
        $compania = $this->companiaActiva($request);

        $cuentas = BancoCuenta::with('cuentaContable')
            ->where('compania_id', $compania->id)
            ->orderBy('banco_nombre')
            ->get();

        $cuentasContables = CuentaContable::where('compania_id', $compania->id)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        return view('admin.bancos.index', [
            'compania'         => $compania,
            'cuentas'          => $cuentas,
            'cuentasContables' => $cuentasContables,
            'tipos'            => BancoCuenta::TIPOS,
            'monedas'          => BancoCuenta::MONEDAS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('bancos.gestionar'), 403);
        $compania = $this->companiaActiva($request);

        $data = $request->validate([
            'banco_nombre'       => ['required', 'string', 'max:100'],
            'numero_cuenta'      => ['required', 'string', 'max:50'],
            'tipo'               => ['required', 'string', 'in:CORRIENTE,AHORROS,INVERSION'],
            'moneda'             => ['required', 'string', 'in:PAB,USD'],
            'cuenta_contable_id' => ['nullable', 'integer', 'exists:cgl_cuentas,id'],
            'saldo_inicial'      => ['nullable', 'numeric', 'min:0'],
        ]);

        $existe = BancoCuenta::where('compania_id', $compania->id)
            ->where('numero_cuenta', $data['numero_cuenta'])
            ->exists();

        if ($existe) {
            return back()->withErrors(['numero_cuenta' => 'Ya existe una cuenta con ese número.']);
        }

        $usuario = $request->user()->email;

        BancoCuenta::create([
            'compania_id'        => $compania->id,
            'banco_nombre'       => $data['banco_nombre'],
            'numero_cuenta'      => $data['numero_cuenta'],
            'tipo'               => $data['tipo'],
            'moneda'             => $data['moneda'],
            'cuenta_contable_id' => $data['cuenta_contable_id'] ?? null,
            'saldo_inicial'      => $data['saldo_inicial'] ?? 0,
            'activa'             => true,
            'created_by'         => $usuario,
            'updated_by'         => $usuario,
        ]);

        return back()->with('status', "Cuenta bancaria {$data['numero_cuenta']} registrada.");
    }

    public function update(Request $request, BancoCuenta $cuenta): RedirectResponse
    {
        abort_unless($request->user()->can('bancos.gestionar'), 403);
        $compania = $this->companiaActiva($request);
        abort_unless($cuenta->compania_id === $compania->id, 404);

        $data = $request->validate([
            'banco_nombre'       => ['required', 'string', 'max:100'],
            'tipo'               => ['required', 'string', 'in:CORRIENTE,AHORROS,INVERSION'],
            'moneda'             => ['required', 'string', 'in:PAB,USD'],
            'cuenta_contable_id' => ['nullable', 'integer', 'exists:cgl_cuentas,id'],
            'saldo_inicial'      => ['nullable', 'numeric', 'min:0'],
        ]);

        $cuenta->update([
            'banco_nombre'       => $data['banco_nombre'],
            'tipo'               => $data['tipo'],
            'moneda'             => $data['moneda'],
            'cuenta_contable_id' => $data['cuenta_contable_id'] ?? null,
            'saldo_inicial'      => $data['saldo_inicial'] ?? 0,
            'updated_by'         => $request->user()->email,
        ]);

        return back()->with('status', "Cuenta {$cuenta->numero_cuenta} actualizada.");
    }

    public function toggleActiva(Request $request, BancoCuenta $cuenta): RedirectResponse
    {
        abort_unless($request->user()->can('bancos.gestionar'), 403);
        $compania = $this->companiaActiva($request);
        abort_unless($cuenta->compania_id === $compania->id, 404);

        $cuenta->update([
            'activa'     => ! $cuenta->activa,
            'updated_by' => $request->user()->email,
        ]);

        $estado = $cuenta->activa ? 'activada' : 'desactivada';

        return back()->with('status', "Cuenta {$cuenta->numero_cuenta} {$estado}.");
    }

    private function companiaActiva(Request $request): Compania
    {
        $companiaId = session('compania_activa_id');
        abort_if(! $companiaId, 404, 'No hay compañía activa.');
        abort_unless(
            $request->user()->is_admin || $request->user()->companiasAccesibles()->contains('id', (int) $companiaId),
            403
        );

        return Compania::findOrFail($companiaId);
    }
}
