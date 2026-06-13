<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduDocente;
use App\Models\EduEstudiante;
use App\Models\EduGrado;
use App\Models\EduGrupo;
use App\Models\EduInstitucion;
use App\Models\EduMatricula;
use App\Models\EduNivelAcademico;
use App\Models\EduPeriodoAcademico;
use App\Models\EduPrograma;
use App\Models\EduSede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduMatriculaController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $periodoId = $request->input('periodo_id');
        $grupoId   = $request->input('grupo_id');
        $search    = trim($request->input('q', ''));

        $matriculas = EduMatricula::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['institucion', 'estudiante.contacto', 'periodo', 'grado', 'grupo'])
            ->when($periodoId, fn ($q) => $q->where('periodo_id', $periodoId))
            ->when($grupoId, fn ($q) => $q->where('grupo_id', $grupoId))
            ->when($search !== '', fn ($q) => $q->whereHas('estudiante.contacto', fn ($c) => $c
                ->where('nombre', 'ilike', "%{$search}%")))
            ->orderByDesc('fecha_matricula')
            ->paginate(25)
            ->withQueryString();

        $periodos  = EduPeriodoAcademico::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderByDesc('anio')->get();
        $grupos    = EduGrupo::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();

        return view('admin.edu.matriculas.index', compact('matriculas', 'periodos', 'grupos', 'periodoId', 'grupoId', 'search'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $periodos      = EduPeriodoAcademico::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderByDesc('anio')->get();
        $estudiantes   = EduEstudiante::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->with('contacto')->get();
        $sedes         = EduSede::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();
        $niveles       = EduNivelAcademico::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('orden')->get();
        $programas     = EduPrograma::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();
        $grados        = EduGrado::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('orden')->get();
        $grupos        = EduGrupo::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();

        return view('admin.edu.matriculas.create', compact(
            'instituciones', 'periodos', 'estudiantes', 'sedes', 'niveles', 'programas', 'grados', 'grupos'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $institIds  = EduInstitucion::where('compania_id', $companiaId)->pluck('id');

        $data = $request->validate([
            'institucion_id'  => ['required', 'integer', 'in:' . $institIds->implode(',')],
            'estudiante_id'   => ['required', 'integer', 'exists:edu_estudiantes,id'],
            'periodo_id'      => ['required', 'integer', 'exists:edu_periodos_academicos,id'],
            'sede_id'         => ['nullable', 'integer', 'exists:edu_sedes,id'],
            'nivel_id'        => ['nullable', 'integer', 'exists:edu_niveles_academicos,id'],
            'programa_id'     => ['nullable', 'integer', 'exists:edu_programas,id'],
            'grado_id'        => ['nullable', 'integer', 'exists:edu_grados,id'],
            'grupo_id'        => ['nullable', 'integer', 'exists:edu_grupos,id'],
            'fecha_matricula' => ['required', 'date'],
            'estado'          => ['required', 'string', 'in:activo,retirado,egresado,suspendido'],
        ]);

        EduMatricula::create([...$data, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.edu.matriculas.index')
            ->with('status', 'Matrícula registrada correctamente.');
    }

    public function show(Request $request, EduMatricula $matricula): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($matricula->institucion?->compania_id === $companiaId, 404);

        $matricula->load([
            'estudiante.contacto', 'institucion', 'periodo', 'sede',
            'nivel', 'programa', 'grado', 'grupo', 'detalles.asignatura',
        ]);

        return view('admin.edu.matriculas.show', compact('matricula'));
    }

    public function update(Request $request, EduMatricula $matricula): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($matricula->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'sede_id'         => ['nullable', 'integer', 'exists:edu_sedes,id'],
            'nivel_id'        => ['nullable', 'integer', 'exists:edu_niveles_academicos,id'],
            'programa_id'     => ['nullable', 'integer', 'exists:edu_programas,id'],
            'grado_id'        => ['nullable', 'integer', 'exists:edu_grados,id'],
            'grupo_id'        => ['nullable', 'integer', 'exists:edu_grupos,id'],
            'fecha_matricula' => ['required', 'date'],
            'estado'          => ['required', 'string', 'in:activo,retirado,egresado,suspendido'],
        ]);

        $matricula->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.matriculas.show', $matricula)
            ->with('status', 'Matrícula actualizada.');
    }

    public function destroy(Request $request, EduMatricula $matricula): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($matricula->institucion?->compania_id === $companiaId, 404);

        $matricula->delete();

        return redirect()->route('admin.edu.matriculas.index')
            ->with('status', 'Matrícula eliminada.');
    }
}
