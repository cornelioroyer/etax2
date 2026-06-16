<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\PrhEdificio;
use App\Models\PrhPropietario;
use App\Models\PrhUnidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrhUnidadController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request, PrhEdificio $edificio): View
    {
        abort_unless($request->user()->can('prh.ver'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);

        $unidades = $edificio->unidades()->with('propietario')->orderBy('codigo')->get();
        $propietarios = PrhPropietario::where('compania_id', $edificio->compania_id)
            ->where('activo', true)->orderBy('nombre')->get();

        return view('admin.prh.unidades.index', compact('edificio', 'unidades', 'propietarios'));
    }

    public function create(Request $request, PrhEdificio $edificio): View
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);

        $propietarios = PrhPropietario::where('compania_id', $edificio->compania_id)
            ->where('activo', true)->orderBy('nombre')->get();

        return view('admin.prh.unidades.create', compact('edificio', 'propietarios'));
    }

    public function store(Request $request, PrhEdificio $edificio): RedirectResponse
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'codigo'         => ['required', 'string', 'max:30'],
            'numero'         => ['required', 'string', 'max:50'],
            'tipo'           => ['required', 'in:' . implode(',', PrhUnidad::TIPOS)],
            'piso'           => ['nullable', 'string', 'max:20'],
            'area_m2'        => ['nullable', 'numeric', 'min:0'],
            'coeficiente'    => ['nullable', 'numeric', 'min:0', 'max:1'],
            'propietario_id' => ['nullable', 'integer'],
        ]);

        PrhUnidad::create([
            ...$data,
            'edificio_id' => $edificio->id,
            'coeficiente' => $data['coeficiente'] ?? 0,
            'created_by'  => $request->user()->email,
        ]);

        return redirect()->route('admin.prh.edificios.unidades.index', $edificio)
            ->with('status', "Unidad {$data['numero']} creada.");
    }

    public function edit(Request $request, PrhEdificio $edificio, PrhUnidad $unidad): View
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($unidad->edificio_id === $edificio->id, 404);

        $propietarios = PrhPropietario::where('compania_id', $edificio->compania_id)
            ->where('activo', true)->orderBy('nombre')->get();

        return view('admin.prh.unidades.edit', compact('edificio', 'unidad', 'propietarios'));
    }

    public function update(Request $request, PrhEdificio $edificio, PrhUnidad $unidad): RedirectResponse
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($unidad->edificio_id === $edificio->id, 404);

        $data = $request->validate([
            'codigo'         => ['required', 'string', 'max:30'],
            'numero'         => ['required', 'string', 'max:50'],
            'tipo'           => ['required', 'in:' . implode(',', PrhUnidad::TIPOS)],
            'piso'           => ['nullable', 'string', 'max:20'],
            'area_m2'        => ['nullable', 'numeric', 'min:0'],
            'coeficiente'    => ['nullable', 'numeric', 'min:0', 'max:1'],
            'propietario_id' => ['nullable', 'integer'],
            'activo'         => ['boolean'],
        ]);

        $unidad->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.prh.edificios.unidades.index', $edificio)
            ->with('status', 'Unidad actualizada.');
    }

    public function destroy(Request $request, PrhEdificio $edificio, PrhUnidad $unidad): RedirectResponse
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($unidad->edificio_id === $edificio->id, 404);

        if ($unidad->cuotas()->exists()) {
            return back()->withErrors(['destroy' => 'No se puede eliminar: la unidad tiene cuotas registradas.']);
        }

        $unidad->delete();

        return back()->with('status', 'Unidad eliminada.');
    }
}
