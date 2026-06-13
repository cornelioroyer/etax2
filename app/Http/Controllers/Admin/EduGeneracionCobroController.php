<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduGeneracionCobro;
use App\Models\EduInstitucion;
use App\Models\EduPeriodoAcademico;
use App\Models\EduPlanCobro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduGeneracionCobroController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $periodoId = $request->input('periodo_id');

        $generaciones = EduGeneracionCobro::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['institucion', 'periodo', 'planCobro'])
            ->when($periodoId, fn ($q) => $q->where('periodo_id', $periodoId))
            ->orderByDesc('fecha_generacion')
            ->paginate(25)
            ->withQueryString();

        $periodos  = EduPeriodoAcademico::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderByDesc('anio')->get();
        $planes    = EduPlanCobro::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->where('activo', true)->orderBy('nombre')->get();
        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();

        return view('admin.edu.generaciones-cobro.index', compact('generaciones', 'periodos', 'planes', 'instituciones', 'periodoId'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $institIds  = EduInstitucion::where('compania_id', $companiaId)->pluck('id');

        $data = $request->validate([
            'institucion_id'    => ['required', 'integer', 'in:' . $institIds->implode(',')],
            'periodo_id'        => ['nullable', 'integer', 'exists:edu_periodos_academicos,id'],
            'plan_cobro_id'     => ['required', 'integer', 'exists:edu_planes_cobro,id'],
            'anio'              => ['required', 'integer', 'min:2000', 'max:2099'],
            'mes'               => ['nullable', 'integer', 'min:1', 'max:12'],
            'numero_cuota'      => ['nullable', 'integer', 'min:1'],
            'total_cuotas'      => ['nullable', 'integer', 'min:1'],
            'fecha_vencimiento' => ['required', 'date'],
            'descripcion'       => ['nullable', 'string'],
        ]);

        EduGeneracionCobro::create([
            ...$data,
            'fecha_generacion' => now()->toDateString(),
            'estado'           => 'pendiente',
            'created_by'       => $request->user()->email,
        ]);

        return redirect()->route('admin.edu.generaciones-cobro.index')
            ->with('status', 'Generación de cobro creada.');
    }

    public function destroy(Request $request, EduGeneracionCobro $generacion): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($generacion->institucion?->compania_id === $companiaId, 404);

        $generacion->delete();

        return redirect()->route('admin.edu.generaciones-cobro.index')
            ->with('status', 'Generación eliminada.');
    }
}
