<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduAsignatura;
use App\Models\EduInstitucion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduAsignaturaController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $asignaturas = EduAsignatura::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with('institucion')
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();

        return view('admin.edu.asignaturas.index', compact('asignaturas', 'instituciones'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $institIds  = EduInstitucion::where('compania_id', $companiaId)->pluck('id');

        $data = $request->validate([
            'institucion_id' => ['required', 'integer', 'in:' . $institIds->implode(',')],
            'codigo'         => ['required', 'string', 'max:30'],
            'nombre'         => ['required', 'string', 'max:200'],
            'descripcion'    => ['nullable', 'string'],
            'creditos'       => ['nullable', 'numeric', 'min:0'],
            'horas_semanales'=> ['nullable', 'integer', 'min:0'],
        ]);

        EduAsignatura::create([...$data, 'activo' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.edu.asignaturas.index')
            ->with('status', 'Asignatura creada.');
    }

    public function update(Request $request, EduAsignatura $asignatura): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($asignatura->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'codigo'         => ['required', 'string', 'max:30'],
            'nombre'         => ['required', 'string', 'max:200'],
            'descripcion'    => ['nullable', 'string'],
            'creditos'       => ['nullable', 'numeric', 'min:0'],
            'horas_semanales'=> ['nullable', 'integer', 'min:0'],
            'activo'         => ['boolean'],
        ]);

        $asignatura->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.asignaturas.index')
            ->with('status', 'Asignatura actualizada.');
    }

    public function destroy(Request $request, EduAsignatura $asignatura): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($asignatura->institucion?->compania_id === $companiaId, 404);

        $asignatura->delete();

        return redirect()->route('admin.edu.asignaturas.index')
            ->with('status', 'Asignatura eliminada.');
    }
}
