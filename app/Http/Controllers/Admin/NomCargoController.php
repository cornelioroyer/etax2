<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\NomCargo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NomCargoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('nomina.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $items = NomCargo::where('compania_id', $companiaId)
            ->withCount('empleados')
            ->orderBy('codigo')
            ->get();

        return view('admin.nomina.cargos.index', compact('items'));
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

        if (NomCargo::where('compania_id', $companiaId)->where('codigo', $codigo)->exists()) {
            return back()->withErrors(['codigo' => 'Ya existe un cargo con ese código.'])->withInput();
        }

        NomCargo::create([
            'compania_id' => $companiaId,
            'codigo' => $codigo,
            'nombre' => $data['nombre'],
            'activo' => true,
            'created_by' => $request->user()->email,
        ]);

        return back()->with('status', 'Cargo creado.');
    }

    public function update(Request $request, NomCargo $cargo): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        abort_unless($cargo->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'activo' => ['boolean'],
        ]);

        $cargo->update(array_merge($data, ['updated_by' => $request->user()->email]));

        return back()->with('status', 'Cargo actualizado.');
    }

    public function destroy(Request $request, NomCargo $cargo): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        abort_unless($cargo->compania_id === $this->companiaActivaId($request), 404);

        if ($cargo->empleados()->exists()) {
            return back()->withErrors(['cargo' => 'No se puede eliminar: tiene empleados asignados. Inactívalo.']);
        }

        $cargo->delete();

        return back()->with('status', 'Cargo eliminado.');
    }
}
