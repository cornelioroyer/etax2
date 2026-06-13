<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\Diario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiarioController extends Controller
{
    const TIPOS = [
        'GENERAL'  => 'General',
        'VENTAS'   => 'Ventas',
        'CXCOBRO'  => 'Cuentas por Cobrar',
        'CXPAGO'   => 'Cuentas por Pagar',
        'COMPRAS'  => 'Compras',
        'BANCO'    => 'Banco',
        'CAJA'     => 'Caja',
    ];

    public function index(Request $request): View
    {
        $compania = $this->companiaActiva($request);

        $diarios = Diario::with('cuentaDefault')
            ->where('compania_id', $compania->id)
            ->orderBy('codigo')
            ->get();

        $cuentas = CuentaContable::where('compania_id', $compania->id)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        return view('admin.diarios.index', [
            'compania' => $compania,
            'diarios'  => $diarios,
            'cuentas'  => $cuentas,
            'tipos'    => self::TIPOS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $compania = $this->companiaActiva($request);

        $data = $request->validate([
            'codigo'               => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_]+$/'],
            'nombre'               => ['required', 'string', 'max:100'],
            'tipo_diario'          => ['required', 'string', 'in:' . implode(',', array_keys(self::TIPOS))],
            'cuenta_default_id'    => ['nullable', 'integer', 'exists:cgl_cuentas,id'],
            'requiere_aprobacion'  => ['boolean'],
        ], [
            'codigo.regex' => 'El código solo puede tener letras mayúsculas, números y guiones bajos.',
        ]);

        $existe = Diario::where('compania_id', $compania->id)
            ->where('codigo', strtoupper($data['codigo']))
            ->exists();

        if ($existe) {
            return back()->withErrors(['codigo' => "Ya existe un diario con el código {$data['codigo']}."]);
        }

        $usuario = $request->user()->email;

        Diario::create([
            'compania_id'          => $compania->id,
            'codigo'               => strtoupper($data['codigo']),
            'nombre'               => $data['nombre'],
            'tipo_diario'          => $data['tipo_diario'],
            'cuenta_default_id'    => $data['cuenta_default_id'] ?? null,
            'requiere_aprobacion'  => $request->boolean('requiere_aprobacion'),
            'activo'               => true,
            'created_by'           => $usuario,
            'updated_by'           => $usuario,
        ]);

        return back()->with('status', "Diario {$data['codigo']} creado.");
    }

    public function update(Request $request, Diario $diario): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $compania = $this->companiaActiva($request);
        abort_unless($diario->compania_id === $compania->id, 404);

        $data = $request->validate([
            'nombre'               => ['required', 'string', 'max:100'],
            'tipo_diario'          => ['required', 'string', 'in:' . implode(',', array_keys(self::TIPOS))],
            'cuenta_default_id'    => ['nullable', 'integer', 'exists:cgl_cuentas,id'],
            'requiere_aprobacion'  => ['boolean'],
        ]);

        $diario->update([
            'nombre'               => $data['nombre'],
            'tipo_diario'          => $data['tipo_diario'],
            'cuenta_default_id'    => $data['cuenta_default_id'] ?? null,
            'requiere_aprobacion'  => $request->boolean('requiere_aprobacion'),
            'updated_by'           => $request->user()->email,
        ]);

        return back()->with('status', "Diario {$diario->codigo} actualizado.");
    }

    public function toggleActivo(Request $request, Diario $diario): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $compania = $this->companiaActiva($request);
        abort_unless($diario->compania_id === $compania->id, 404);

        $diario->update([
            'activo'     => ! $diario->activo,
            'updated_by' => $request->user()->email,
        ]);

        $estado = $diario->activo ? 'activado' : 'desactivado';

        return back()->with('status', "Diario {$diario->codigo} {$estado}.");
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
