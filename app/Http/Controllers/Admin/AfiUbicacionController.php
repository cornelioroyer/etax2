<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\AfiUbicacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AfiUbicacionController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('activos.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $ubicaciones = AfiUbicacion::where('compania_id', $companiaId)
            ->orderBy('codigo')
            ->get();

        return view('admin.activos.ubicaciones.index', compact('ubicaciones'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('activos.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:30'],
            'nombre' => ['required', 'string', 'max:150'],
        ]);

        $codigo = strtoupper(trim($data['codigo']));

        if (AfiUbicacion::where('compania_id', $companiaId)->where('codigo', $codigo)->exists()) {
            return back()->withErrors(['codigo' => 'Ya existe una ubicación con ese código.'])->withInput();
        }

        AfiUbicacion::create([
            'compania_id' => $companiaId,
            'codigo'      => $codigo,
            'nombre'      => $data['nombre'],
            'created_by'  => $request->user()->email,
        ]);

        return back()->with('status', 'Ubicación creada.');
    }

    public function update(Request $request, AfiUbicacion $ubicacion): RedirectResponse
    {
        abort_unless($request->user()->can('activos.gestionar'), 403);
        abort_unless($ubicacion->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
        ]);

        $ubicacion->update(array_merge($data, ['updated_by' => $request->user()->email]));

        return back()->with('status', 'Ubicación actualizada.');
    }

    public function destroy(Request $request, AfiUbicacion $ubicacion): RedirectResponse
    {
        abort_unless($request->user()->can('activos.gestionar'), 403);
        abort_unless($ubicacion->compania_id === $this->companiaActivaId($request), 404);

        if ($ubicacion->activos()->exists()) {
            return back()->withErrors(['ubicacion' => 'No se puede eliminar: tiene activos asociados.']);
        }

        $ubicacion->delete();

        return back()->with('status', 'Ubicación eliminada.');
    }
}
