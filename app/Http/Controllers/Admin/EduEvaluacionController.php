<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduAsignatura;
use App\Models\EduDocente;
use App\Models\EduEvaluacion;
use App\Models\EduGrupo;
use App\Models\EduInstitucion;
use App\Models\EduPeriodoAcademico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduEvaluacionController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $periodoId = $request->input('periodo_id');
        $grupoId   = $request->input('grupo_id');

        $evaluaciones = EduEvaluacion::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['asignatura', 'docente.contacto', 'grupo', 'periodo'])
            ->when($periodoId, fn ($q) => $q->where('periodo_id', $periodoId))
            ->when($grupoId, fn ($q) => $q->where('grupo_id', $grupoId))
            ->orderByDesc('fecha_evaluacion')
            ->paginate(25)
            ->withQueryString();

        $periodos = EduPeriodoAcademico::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderByDesc('anio')->get();
        $grupos   = EduGrupo::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();

        return view('admin.edu.evaluaciones.index', compact('evaluaciones', 'periodos', 'grupos', 'periodoId', 'grupoId'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $periodos      = EduPeriodoAcademico::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderByDesc('anio')->get();
        $asignaturas   = EduAsignatura::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();
        $docentes      = EduDocente::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->with('contacto')->get();
        $grupos        = EduGrupo::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();

        return view('admin.edu.evaluaciones.create', compact('instituciones', 'periodos', 'asignaturas', 'docentes', 'grupos'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $institIds  = EduInstitucion::where('compania_id', $companiaId)->pluck('id');

        $data = $request->validate([
            'institucion_id'   => ['required', 'integer', 'in:' . $institIds->implode(',')],
            'periodo_id'       => ['nullable', 'integer', 'exists:edu_periodos_academicos,id'],
            'asignatura_id'    => ['nullable', 'integer', 'exists:edu_asignaturas,id'],
            'docente_id'       => ['nullable', 'integer', 'exists:edu_docentes,id'],
            'grupo_id'         => ['nullable', 'integer', 'exists:edu_grupos,id'],
            'titulo'           => ['required', 'string', 'max:200'],
            'descripcion'      => ['nullable', 'string'],
            'tipo_evaluacion'  => ['nullable', 'string', 'max:50'],
            'fecha_evaluacion' => ['nullable', 'date'],
            'fecha_entrega'    => ['nullable', 'date'],
            'puntaje_maximo'   => ['nullable', 'numeric', 'min:0'],
            'porcentaje'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'estado'           => ['required', 'string', 'in:borrador,publicada,cerrada'],
            'visible_estudiante' => ['boolean'],
            'visible_acudiente'  => ['boolean'],
        ]);

        EduEvaluacion::create([...$data, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.edu.evaluaciones.index')
            ->with('status', 'Evaluación creada.');
    }

    public function show(Request $request, EduEvaluacion $evaluacion): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($evaluacion->institucion?->compania_id === $companiaId, 404);

        $evaluacion->load(['asignatura', 'docente.contacto', 'grupo', 'periodo', 'calificaciones.estudiante.contacto']);

        return view('admin.edu.evaluaciones.show', compact('evaluacion'));
    }

    public function update(Request $request, EduEvaluacion $evaluacion): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($evaluacion->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'titulo'             => ['required', 'string', 'max:200'],
            'descripcion'        => ['nullable', 'string'],
            'tipo_evaluacion'    => ['nullable', 'string', 'max:50'],
            'fecha_evaluacion'   => ['nullable', 'date'],
            'fecha_entrega'      => ['nullable', 'date'],
            'puntaje_maximo'     => ['nullable', 'numeric', 'min:0'],
            'porcentaje'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'estado'             => ['required', 'string', 'in:borrador,publicada,cerrada'],
            'visible_estudiante' => ['boolean'],
            'visible_acudiente'  => ['boolean'],
        ]);

        $evaluacion->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.evaluaciones.index')
            ->with('status', 'Evaluación actualizada.');
    }

    public function destroy(Request $request, EduEvaluacion $evaluacion): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($evaluacion->institucion?->compania_id === $companiaId, 404);

        $evaluacion->delete();

        return redirect()->route('admin.edu.evaluaciones.index')
            ->with('status', 'Evaluación eliminada.');
    }
}
