<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\PhEdificio;
use App\Models\PhPropietario;
use App\Models\PhUnidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PhUnidadController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request, PhEdificio $edificio): View
    {
        abort_unless($request->user()->can('ph.ver'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);

        $unidades = $edificio->unidades()->with('propietario')->orderBy('codigo')->get();
        $propietarios = PhPropietario::where('compania_id', $edificio->compania_id)
            ->where('activo', true)->orderBy('nombre')->get();

        return view('admin.ph.unidades.index', compact('edificio', 'unidades', 'propietarios'));
    }

    public function create(Request $request, PhEdificio $edificio): View
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);

        $propietarios = PhPropietario::where('compania_id', $edificio->compania_id)
            ->where('activo', true)->orderBy('nombre')->get();

        return view('admin.ph.unidades.create', compact('edificio', 'propietarios'));
    }

    public function store(Request $request, PhEdificio $edificio): RedirectResponse
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'codigo'         => ['required', 'string', 'max:30'],
            'numero'         => ['required', 'string', 'max:50'],
            'tipo'           => ['required', 'in:' . implode(',', PhUnidad::TIPOS)],
            'piso'           => ['nullable', 'string', 'max:20'],
            'area_m2'        => ['nullable', 'numeric', 'min:0'],
            'coeficiente'    => ['nullable', 'numeric', 'min:0', 'max:1'],
            'propietario_id' => ['nullable', 'integer'],
        ]);

        PhUnidad::create([
            ...$data,
            'edificio_id' => $edificio->id,
            'coeficiente' => $data['coeficiente'] ?? 0,
            'created_by'  => $request->user()->email,
        ]);

        return redirect()->route('admin.ph.edificios.unidades.index', $edificio)
            ->with('status', "Unidad {$data['numero']} creada.");
    }

    public function edit(Request $request, PhEdificio $edificio, PhUnidad $unidad): View
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($unidad->edificio_id === $edificio->id, 404);

        $propietarios = PhPropietario::where('compania_id', $edificio->compania_id)
            ->where('activo', true)->orderBy('nombre')->get();

        return view('admin.ph.unidades.edit', compact('edificio', 'unidad', 'propietarios'));
    }

    public function update(Request $request, PhEdificio $edificio, PhUnidad $unidad): RedirectResponse
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($unidad->edificio_id === $edificio->id, 404);

        $data = $request->validate([
            'codigo'         => ['required', 'string', 'max:30'],
            'numero'         => ['required', 'string', 'max:50'],
            'tipo'           => ['required', 'in:' . implode(',', PhUnidad::TIPOS)],
            'piso'           => ['nullable', 'string', 'max:20'],
            'area_m2'        => ['nullable', 'numeric', 'min:0'],
            'coeficiente'    => ['nullable', 'numeric', 'min:0', 'max:1'],
            'propietario_id' => ['nullable', 'integer'],
            'activo'         => ['boolean'],
        ]);

        $unidad->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.ph.edificios.unidades.index', $edificio)
            ->with('status', 'Unidad actualizada.');
    }

    public function destroy(Request $request, PhEdificio $edificio, PhUnidad $unidad): RedirectResponse
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($unidad->edificio_id === $edificio->id, 404);

        if ($unidad->cuotas()->exists()) {
            return back()->withErrors(['destroy' => 'No se puede eliminar: la unidad tiene cuotas registradas.']);
        }

        $unidad->delete();

        return back()->with('status', 'Unidad eliminada.');
    }
}
