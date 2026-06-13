<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\CuentaContable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CajaController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $cajas = Caja::with('cuentaContable')
            ->where('compania_id', $companiaId)
            ->orderBy('codigo')
            ->get();

        return view('admin.caja.index', [
            'cajas'   => $cajas,
            'cuentas' => $this->cuentasEfectivo($companiaId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('caja.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo'             => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_]+$/'],
            'nombre'             => ['required', 'string', 'max:100'],
            'cuenta_contable_id' => ['nullable', 'integer', Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId)],
        ], [
            'codigo.regex' => 'El código solo puede tener letras mayúsculas, números y guiones bajos.',
        ]);

        $existe = Caja::where('compania_id', $companiaId)
            ->where('codigo', strtoupper($data['codigo']))
            ->exists();

        if ($existe) {
            return back()->withErrors(['codigo' => "Ya existe una caja con el código {$data['codigo']}."]);
        }

        Caja::create([
            'compania_id'        => $companiaId,
            'codigo'             => strtoupper($data['codigo']),
            'nombre'             => $data['nombre'],
            'cuenta_contable_id' => $data['cuenta_contable_id'] ?? null,
            'responsable_id'     => $request->user()->id,
            'activa'             => true,
            'created_by'         => $request->user()->email,
        ]);

        return back()->with('status', "Caja {$data['codigo']} creada.");
    }

    public function update(Request $request, Caja $caja): RedirectResponse
    {
        abort_unless($request->user()->can('caja.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($caja->compania_id === $companiaId, 404);

        $data = $request->validate([
            'nombre'             => ['required', 'string', 'max:100'],
            'cuenta_contable_id' => ['nullable', 'integer', Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId)],
        ]);

        $caja->update([
            'nombre'             => $data['nombre'],
            'cuenta_contable_id' => $data['cuenta_contable_id'] ?? null,
            'updated_by'         => $request->user()->email,
        ]);

        return back()->with('status', "Caja {$caja->codigo} actualizada.");
    }

    public function toggle(Request $request, Caja $caja): RedirectResponse
    {
        abort_unless($request->user()->can('caja.gestionar'), 403);
        abort_unless($caja->compania_id === $this->companiaActivaId($request), 404);

        $caja->update(['activa' => ! $caja->activa, 'updated_by' => $request->user()->email]);

        return back()->with('status', "Caja {$caja->codigo} ".($caja->activa ? 'activada' : 'desactivada').'.');
    }

    public function show(Request $request, Caja $caja): View
    {
        abort_unless($caja->compania_id === $this->companiaActivaId($request), 404);

        $caja->load(['cuentaContable', 'movimientos.cuentaContable', 'reembolsos', 'vales', 'arqueos']);

        return view('admin.caja.show', [
            'caja'    => $caja,
            'saldo'   => $caja->saldoSistema(),
            'cuentas' => $this->cuentasMovimiento($caja->compania_id),
        ]);
    }

    /** Cuentas de efectivo/banco (codigo 11xx) para asignar a la caja. */
    private function cuentasEfectivo(int $companiaId)
    {
        return CuentaContable::where('compania_id', $companiaId)
            ->where('activa', true)
            ->where('permite_movimiento', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);
    }

    /** Todas las cuentas con movimiento, para gastos/contrapartidas. */
    private function cuentasMovimiento(int $companiaId)
    {
        return CuentaContable::where('compania_id', $companiaId)
            ->where('activa', true)
            ->where('permite_movimiento', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);
    }
}
