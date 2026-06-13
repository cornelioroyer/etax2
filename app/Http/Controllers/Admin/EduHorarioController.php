<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduGrupo;
use App\Models\EduHorario;
use App\Models\EduInstitucion;
use App\Models\EduPeriodoAcademico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduHorarioController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $horarios = EduHorario::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['institucion', 'periodo', 'grupo', 'detalles.asignatura', 'detalles.docente.contacto'])
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $periodos      = EduPeriodoAcademico::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderByDesc('anio')->get();
        $grupos        = EduGrupo::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();

        return view('admin.edu.horarios.index', compact('horarios', 'instituciones', 'periodos', 'grupos'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $institIds  = EduInstitucion::where('compania_id', $companiaId)->pluck('id');

        $data = $request->validate([
            'institucion_id' => ['required', 'integer', 'in:' . $institIds->implode(',')],
            'periodo_id'     => ['nullable', 'integer', 'exists:edu_periodos_academicos,id'],
            'grupo_id'       => ['nullable', 'integer', 'exists:edu_grupos,id'],
            'nombre'         => ['required', 'string', 'max:200'],
        ]);

        EduHorario::create([...$data, 'activo' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.edu.horarios.index')
            ->with('status', 'Horario creado.');
    }

    public function update(Request $request, EduHorario $horario): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($horario->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'periodo_id' => ['nullable', 'integer', 'exists:edu_periodos_academicos,id'],
            'grupo_id'   => ['nullable', 'integer', 'exists:edu_grupos,id'],
            'nombre'     => ['required', 'string', 'max:200'],
            'activo'     => ['boolean'],
        ]);

        $horario->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.horarios.index')
            ->with('status', 'Horario actualizado.');
    }

    public function destroy(Request $request, EduHorario $horario): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($horario->institucion?->compania_id === $companiaId, 404);

        $horario->delete();

        return redirect()->route('admin.edu.horarios.index')
            ->with('status', 'Horario eliminado.');
    }
}
