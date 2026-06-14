<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\DimLineaNegocio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DimLineaNegocioController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('dimensiones.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $items = DimLineaNegocio::where('compania_id', $companiaId)
            ->orderBy('codigo')
            ->get();

        return view('admin.dimensiones.lineas-negocio.index', compact('items'));
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

        if (DimLineaNegocio::where('compania_id', $companiaId)->where('codigo', $codigo)->exists()) {
            return back()->withErrors(['codigo' => 'Ya existe una línea de negocio con ese código.'])->withInput();
        }

        DimLineaNegocio::create([
            'compania_id' => $companiaId,
            'codigo'      => $codigo,
            'nombre'      => $data['nombre'],
            'activo'      => true,
            'created_by'  => $request->user()->email,
        ]);

        return back()->with('status', 'Línea de negocio creada.');
    }

    public function update(Request $request, DimLineaNegocio $lineaNegocio): RedirectResponse
    {
        abort_unless($request->user()->can('dimensiones.gestionar'), 403);
        abort_unless($lineaNegocio->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'activo' => ['boolean'],
        ]);

        $lineaNegocio->update(array_merge($data, ['updated_by' => $request->user()->email]));

        return back()->with('status', 'Línea de negocio actualizada.');
    }

    public function destroy(Request $request, DimLineaNegocio $lineaNegocio): RedirectResponse
    {
        abort_unless($request->user()->can('dimensiones.gestionar'), 403);
        abort_unless($lineaNegocio->compania_id === $this->companiaActivaId($request), 404);

        if ($lineaNegocio->asientosDetalle()->exists()) {
            return back()->withErrors(['linea' => 'No se puede eliminar: tiene asientos asociados.']);
        }

        $lineaNegocio->delete();

        return back()->with('status', 'Línea de negocio eliminada.');
    }
}
