<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\NomDepartamento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NomDepartamentoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('nomina.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $items = NomDepartamento::where('compania_id', $companiaId)
            ->withCount('empleados')
            ->orderBy('codigo')
            ->get();

        return view('admin.nomina.departamentos.index', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:200'],
        ]);

        $codigo = strtoupper(trim($data['codigo']));

        if (NomDepartamento::where('compania_id', $companiaId)->where('codigo', $codigo)->exists()) {
            return back()->withErrors(['codigo' => 'Ya existe un departamento con ese código.'])->withInput();
        }

        NomDepartamento::create([
            'compania_id' => $companiaId,
            'codigo' => $codigo,
            'nombre' => $data['nombre'],
            'activo' => true,
            'created_by' => $request->user()->email,
        ]);

        return back()->with('status', 'Departamento creado.');
    }

    public function update(Request $request, NomDepartamento $departamento): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        abort_unless($departamento->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'activo' => ['boolean'],
        ]);

        $departamento->update(array_merge($data, ['updated_by' => $request->user()->email]));

        return back()->with('status', 'Departamento actualizado.');
    }

    public function destroy(Request $request, NomDepartamento $departamento): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        abort_unless($departamento->compania_id === $this->companiaActivaId($request), 404);

        if ($departamento->empleados()->exists()) {
            return back()->withErrors(['departamento' => 'No se puede eliminar: tiene empleados asignados. Inactívalo.']);
        }

        $departamento->delete();

        return back()->with('status', 'Departamento eliminado.');
    }
}
