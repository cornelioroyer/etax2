<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduInstitucion;
use App\Models\EduSede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduSedeController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $sedes = EduSede::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with('institucion')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();

        return view('admin.edu.sedes.index', compact('sedes', 'instituciones'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $institIds = EduInstitucion::where('compania_id', $companiaId)->pluck('id');

        $data = $request->validate([
            'institucion_id' => ['required', 'integer', 'in:' . $institIds->implode(',')],
            'codigo'         => ['required', 'string', 'max:30'],
            'nombre'         => ['required', 'string', 'max:200'],
            'direccion'      => ['nullable', 'string', 'max:300'],
            'telefono'       => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:150'],
        ]);

        EduSede::create([...$data, 'activo' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.edu.sedes.index')
            ->with('status', 'Sede creada correctamente.');
    }

    public function update(Request $request, EduSede $sede): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($sede->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'codigo'    => ['required', 'string', 'max:30'],
            'nombre'    => ['required', 'string', 'max:200'],
            'direccion' => ['nullable', 'string', 'max:300'],
            'telefono'  => ['nullable', 'string', 'max:50'],
            'email'     => ['nullable', 'email', 'max:150'],
            'activo'    => ['boolean'],
        ]);

        $sede->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.sedes.index')
            ->with('status', 'Sede actualizada.');
    }

    public function destroy(Request $request, EduSede $sede): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($sede->institucion?->compania_id === $companiaId, 404);

        $sede->delete();

        return redirect()->route('admin.edu.sedes.index')
            ->with('status', 'Sede eliminada.');
    }
}
