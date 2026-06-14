<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\DimUbicacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DimUbicacionController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('dimensiones.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $items = DimUbicacion::where('compania_id', $companiaId)
            ->orderBy('codigo')
            ->get();

        return view('admin.dimensiones.ubicaciones.index', compact('items'));
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

        if (DimUbicacion::where('compania_id', $companiaId)->where('codigo', $codigo)->exists()) {
            return back()->withErrors(['codigo' => 'Ya existe una ubicación con ese código.'])->withInput();
        }

        DimUbicacion::create([
            'compania_id' => $companiaId,
            'codigo'      => $codigo,
            'nombre'      => $data['nombre'],
            'activo'      => true,
            'created_by'  => $request->user()->email,
        ]);

        return back()->with('status', 'Ubicación creada.');
    }

    public function update(Request $request, DimUbicacion $ubicacion): RedirectResponse
    {
        abort_unless($request->user()->can('dimensiones.gestionar'), 403);
        abort_unless($ubicacion->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'activo' => ['boolean'],
        ]);

        $ubicacion->update(array_merge($data, ['updated_by' => $request->user()->email]));

        return back()->with('status', 'Ubicación actualizada.');
    }

    public function destroy(Request $request, DimUbicacion $ubicacion): RedirectResponse
    {
        abort_unless($request->user()->can('dimensiones.gestionar'), 403);
        abort_unless($ubicacion->compania_id === $this->companiaActivaId($request), 404);

        if ($ubicacion->asientosDetalle()->exists()) {
            return back()->withErrors(['ubicacion' => 'No se puede eliminar: tiene asientos asociados.']);
        }

        $ubicacion->delete();

        return back()->with('status', 'Ubicación eliminada.');
    }
}
