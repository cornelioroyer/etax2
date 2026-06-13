<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduConceptoCobro;
use App\Models\EduEstudiante;
use App\Models\EduGrado;
use App\Models\EduGrupo;
use App\Models\EduInstitucion;
use App\Models\EduNivelAcademico;
use App\Models\EduPlanCobro;
use App\Models\EduPrograma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduPlanCobroController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $planes = EduPlanCobro::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['institucion', 'concepto'])
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $conceptos     = EduConceptoCobro::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();
        $niveles       = EduNivelAcademico::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('orden')->get();
        $programas     = EduPrograma::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();
        $grados        = EduGrado::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();
        $grupos        = EduGrupo::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();

        return view('admin.edu.planes-cobro.index', compact(
            'planes', 'instituciones', 'conceptos', 'niveles', 'programas', 'grados', 'grupos'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $institIds  = EduInstitucion::where('compania_id', $companiaId)->pluck('id');

        $data = $request->validate([
            'institucion_id'    => ['required', 'integer', 'in:' . $institIds->implode(',')],
            'concepto_id'       => ['nullable', 'integer', 'exists:edu_conceptos_cobro,id'],
            'codigo'            => ['required', 'string', 'max:30'],
            'nombre'            => ['required', 'string', 'max:200'],
            'descripcion'       => ['nullable', 'string'],
            'aplica_a'          => ['nullable', 'string', 'in:todos,nivel,programa,grado,grupo,estudiante'],
            'nivel_id'          => ['nullable', 'integer', 'exists:edu_niveles_academicos,id'],
            'programa_id'       => ['nullable', 'integer', 'exists:edu_programas,id'],
            'grado_id'          => ['nullable', 'integer', 'exists:edu_grados,id'],
            'grupo_id'          => ['nullable', 'integer', 'exists:edu_grupos,id'],
            'estudiante_id'     => ['nullable', 'integer', 'exists:edu_estudiantes,id'],
            'frecuencia'        => ['nullable', 'string', 'max:50'],
            'cantidad_cuotas'   => ['nullable', 'integer', 'min:1'],
            'dia_vencimiento'   => ['nullable', 'integer', 'min:1', 'max:31'],
            'fecha_inicio'      => ['nullable', 'date'],
            'fecha_fin'         => ['nullable', 'date'],
            'monto'             => ['required', 'numeric', 'min:0'],
            'generar_automatico'=> ['boolean'],
        ]);

        EduPlanCobro::create([...$data, 'activo' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.edu.planes-cobro.index')
            ->with('status', 'Plan de cobro creado.');
    }

    public function update(Request $request, EduPlanCobro $plan): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($plan->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'codigo'            => ['required', 'string', 'max:30'],
            'nombre'            => ['required', 'string', 'max:200'],
            'descripcion'       => ['nullable', 'string'],
            'aplica_a'          => ['nullable', 'string', 'in:todos,nivel,programa,grado,grupo,estudiante'],
            'frecuencia'        => ['nullable', 'string', 'max:50'],
            'cantidad_cuotas'   => ['nullable', 'integer', 'min:1'],
            'dia_vencimiento'   => ['nullable', 'integer', 'min:1', 'max:31'],
            'fecha_inicio'      => ['nullable', 'date'],
            'fecha_fin'         => ['nullable', 'date'],
            'monto'             => ['required', 'numeric', 'min:0'],
            'generar_automatico'=> ['boolean'],
            'activo'            => ['boolean'],
        ]);

        $plan->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.planes-cobro.index')
            ->with('status', 'Plan de cobro actualizado.');
    }

    public function destroy(Request $request, EduPlanCobro $plan): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($plan->institucion?->compania_id === $companiaId, 404);

        $plan->delete();

        return redirect()->route('admin.edu.planes-cobro.index')
            ->with('status', 'Plan de cobro eliminado.');
    }
}
