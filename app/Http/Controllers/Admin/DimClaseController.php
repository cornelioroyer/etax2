<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\DimClase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DimClaseController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('dimensiones.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $items = DimClase::where('compania_id', $companiaId)
            ->orderBy('codigo')
            ->get();

        return view('admin.dimensiones.clases.index', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('dimensiones.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:30'],
            'nombre' => ['required', 'string', 'max:150'],
        ]);

        $codigo = strtoupper(trim($data['codigo']));

        if (DimClase::where('compania_id', $companiaId)->where('codigo', $codigo)->exists()) {
            return back()->withErrors(['codigo' => 'Ya existe una clase con ese código.'])->withInput();
        }

        DimClase::create([
            'compania_id' => $companiaId,
            'codigo'      => $codigo,
            'nombre'      => $data['nombre'],
            'activo'      => true,
            'created_by'  => $request->user()->email,
        ]);

        return back()->with('status', 'Clase creada.');
    }

    public function update(Request $request, DimClase $clase): RedirectResponse
    {
        abort_unless($request->user()->can('dimensiones.gestionar'), 403);
        abort_unless($clase->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'activo' => ['boolean'],
        ]);

        $clase->update(array_merge($data, ['updated_by' => $request->user()->email]));

        return back()->with('status', 'Clase actualizada.');
    }

    public function destroy(Request $request, DimClase $clase): RedirectResponse
    {
        abort_unless($request->user()->can('dimensiones.gestionar'), 403);
        abort_unless($clase->compania_id === $this->companiaActivaId($request), 404);

        if ($clase->asientosDetalle()->exists()) {
            return back()->withErrors(['clase' => 'No se puede eliminar: tiene asientos asociados.']);
        }

        $clase->delete();

        return back()->with('status', 'Clase eliminada.');
    }
}
