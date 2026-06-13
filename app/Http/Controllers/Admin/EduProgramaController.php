<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduInstitucion;
use App\Models\EduNivelAcademico;
use App\Models\EduPrograma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduProgramaController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $programas = EduPrograma::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['institucion', 'nivel'])
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $niveles = EduNivelAcademico::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->orderBy('orden')->get();

        return view('admin.edu.programas.index', compact('programas', 'instituciones', 'niveles'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $institIds  = EduInstitucion::where('compania_id', $companiaId)->pluck('id');

        $data = $request->validate([
            'institucion_id'   => ['required', 'integer', 'in:' . $institIds->implode(',')],
            'nivel_id'         => ['nullable', 'integer', 'exists:edu_niveles_academicos,id'],
            'codigo'           => ['required', 'string', 'max:30'],
            'nombre'           => ['required', 'string', 'max:200'],
            'tipo_programa'    => ['nullable', 'string', 'max:50'],
            'duracion_periodos'=> ['nullable', 'integer', 'min:1'],
        ]);

        EduPrograma::create([...$data, 'activo' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.edu.programas.index')
            ->with('status', 'Programa creado.');
    }

    public function update(Request $request, EduPrograma $programa): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($programa->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'nivel_id'         => ['nullable', 'integer', 'exists:edu_niveles_academicos,id'],
            'codigo'           => ['required', 'string', 'max:30'],
            'nombre'           => ['required', 'string', 'max:200'],
            'tipo_programa'    => ['nullable', 'string', 'max:50'],
            'duracion_periodos'=> ['nullable', 'integer', 'min:1'],
            'activo'           => ['boolean'],
        ]);

        $programa->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.programas.index')
            ->with('status', 'Programa actualizado.');
    }

    public function destroy(Request $request, EduPrograma $programa): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($programa->institucion?->compania_id === $companiaId, 404);

        $programa->delete();

        return redirect()->route('admin.edu.programas.index')
            ->with('status', 'Programa eliminado.');
    }
}
