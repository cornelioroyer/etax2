<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\PrhEdificio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrhEdificioController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('prh.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $search = trim($request->input('q', ''));
        $edificios = PrhEdificio::where('compania_id', $companiaId)
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%")
                ->orWhere('codigo', 'ilike', "%{$search}%"))
            ->withCount('unidades')
            ->orderBy('codigo')
            ->paginate(20)
            ->withQueryString();

        return view('admin.prh.edificios.index', compact('edificios', 'search'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);
        return view('admin.prh.edificios.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo'      => ['required', 'string', 'max:30'],
            'nombre'      => ['required', 'string', 'max:200'],
            'direccion'   => ['nullable', 'string', 'max:500'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ]);

        $edificio = PrhEdificio::create([
            ...$data,
            'compania_id' => $companiaId,
            'created_by'  => $request->user()->email,
        ]);

        return redirect()->route('admin.prh.edificios.show', $edificio)
            ->with('status', "Edificio {$edificio->nombre} creado.");
    }

    public function show(Request $request, PrhEdificio $edificio): View
    {
        abort_unless($request->user()->can('prh.ver'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);

        $edificio->load(['unidades.propietario']);

        return view('admin.prh.edificios.show', compact('edificio'));
    }

    public function edit(Request $request, PrhEdificio $edificio): View
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);

        return view('admin.prh.edificios.edit', compact('edificio'));
    }

    public function update(Request $request, PrhEdificio $edificio): RedirectResponse
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'codigo'      => ['required', 'string', 'max:30'],
            'nombre'      => ['required', 'string', 'max:200'],
            'direccion'   => ['nullable', 'string', 'max:500'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'activo'      => ['boolean'],
        ]);

        $edificio->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.prh.edificios.show', $edificio)
            ->with('status', 'Edificio actualizado.');
    }

    public function destroy(Request $request, PrhEdificio $edificio): RedirectResponse
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);
        abort_unless($edificio->compania_id === $this->companiaActivaId($request), 404);

        if ($edificio->unidades()->exists()) {
            return back()->withErrors(['destroy' => 'No se puede eliminar: el edificio tiene unidades registradas.']);
        }

        $edificio->delete();

        return redirect()->route('admin.prh.edificios.index')
            ->with('status', 'Edificio eliminado.');
    }
}
