<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduInstitucion;
use App\Models\EduPeriodoAcademico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduPeriodoAcademicoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $periodos = EduPeriodoAcademico::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with('institucion')
            ->orderByDesc('anio')
            ->orderBy('fecha_inicio')
            ->paginate(25)
            ->withQueryString();

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();

        return view('admin.edu.periodos.index', compact('periodos', 'instituciones'));
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
            'anio'           => ['required', 'integer', 'min:2000', 'max:2099'],
            'fecha_inicio'   => ['required', 'date'],
            'fecha_fin'      => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'estado'         => ['required', 'string', 'in:abierto,cerrado,archivado'],
        ]);

        EduPeriodoAcademico::create([...$data, 'activo' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.edu.periodos.index')
            ->with('status', 'Período académico creado.');
    }

    public function update(Request $request, EduPeriodoAcademico $periodo): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($periodo->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'codigo'       => ['required', 'string', 'max:30'],
            'nombre'       => ['required', 'string', 'max:200'],
            'anio'         => ['required', 'integer', 'min:2000', 'max:2099'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'estado'       => ['required', 'string', 'in:abierto,cerrado,archivado'],
            'activo'       => ['boolean'],
        ]);

        $periodo->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.periodos.index')
            ->with('status', 'Período actualizado.');
    }

    public function destroy(Request $request, EduPeriodoAcademico $periodo): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($periodo->institucion?->compania_id === $companiaId, 404);

        $periodo->delete();

        return redirect()->route('admin.edu.periodos.index')
            ->with('status', 'Período eliminado.');
    }
}
