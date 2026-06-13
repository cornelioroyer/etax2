<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduAsistencia;
use App\Models\EduAsignatura;
use App\Models\EduGrupo;
use App\Models\EduInstitucion;
use App\Models\EduMatricula;
use App\Models\EduPeriodoAcademico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduAsistenciaController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $grupoId     = $request->input('grupo_id');
        $asignaturaId= $request->input('asignatura_id');
        $fecha       = $request->input('fecha', now()->toDateString());

        $matriculas = collect();
        $asistencias = collect();

        if ($grupoId) {
            $grupo = EduGrupo::findOrFail($grupoId);
            abort_unless($grupo->institucion?->compania_id === $companiaId, 404);

            $matriculas = EduMatricula::where('grupo_id', $grupoId)
                ->where('estado', 'activo')
                ->with('estudiante.contacto')
                ->get();

            if ($asignaturaId && $fecha) {
                $asistencias = EduAsistencia::where('asignatura_id', $asignaturaId)
                    ->where('fecha', $fecha)
                    ->whereIn('matricula_id', $matriculas->pluck('id'))
                    ->get()
                    ->keyBy('matricula_id');
            }
        }

        $grupos     = EduGrupo::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();
        $asignaturas= EduAsignatura::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();

        return view('admin.edu.asistencias.index', compact(
            'grupos', 'asignaturas', 'matriculas', 'asistencias',
            'grupoId', 'asignaturaId', 'fecha'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'asignatura_id' => ['required', 'integer', 'exists:edu_asignaturas,id'],
            'fecha'         => ['required', 'date'],
            'asistencias'   => ['required', 'array'],
            'asistencias.*' => ['string', 'in:presente,ausente,justificado,tardanza'],
        ]);

        $asignatura = EduAsignatura::findOrFail($data['asignatura_id']);
        abort_unless($asignatura->institucion?->compania_id === $companiaId, 404);

        foreach ($data['asistencias'] as $matriculaId => $estado) {
            $matricula = EduMatricula::find($matriculaId);
            if (!$matricula || $matricula->institucion?->compania_id !== $companiaId) {
                continue;
            }

            EduAsistencia::updateOrCreate(
                [
                    'matricula_id'  => $matriculaId,
                    'asignatura_id' => $data['asignatura_id'],
                    'fecha'         => $data['fecha'],
                ],
                [
                    'institucion_id' => $matricula->institucion_id,
                    'estado'         => $estado,
                    'created_by'     => $request->user()->email,
                    'updated_by'     => $request->user()->email,
                ]
            );
        }

        return redirect()->route('admin.edu.asistencias.index', [
            'grupo_id'      => $request->input('grupo_id'),
            'asignatura_id' => $data['asignatura_id'],
            'fecha'         => $data['fecha'],
        ])->with('status', 'Asistencia guardada correctamente.');
    }
}
