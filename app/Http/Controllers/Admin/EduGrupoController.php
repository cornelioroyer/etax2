<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\EduGrado;
use App\Models\EduGrupo;
use App\Models\EduInstitucion;
use App\Models\EduSede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EduGrupoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('edu.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $grupos = EduGrupo::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['institucion', 'sede', 'grado'])
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        $instituciones = EduInstitucion::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $sedes = EduSede::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();
        $grados = EduGrado::whereHas('institucion', fn ($q) => $q->where('compania_id', $companiaId))->orderBy('nombre')->get();

        return view('admin.edu.grupos.index', compact('grupos', 'instituciones', 'sedes', 'grados'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $institIds  = EduInstitucion::where('compania_id', $companiaId)->pluck('id');

        $data = $request->validate([
            'institucion_id' => ['required', 'integer', 'in:' . $institIds->implode(',')],
            'sede_id'        => ['nullable', 'integer', 'exists:edu_sedes,id'],
            'grado_id'       => ['nullable', 'integer', 'exists:edu_grados,id'],
            'codigo'         => ['required', 'string', 'max:30'],
            'nombre'         => ['required', 'string', 'max:200'],
            'jornada'        => ['nullable', 'string', 'in:manana,tarde,nocturna,fin_de_semana'],
            'capacidad'      => ['nullable', 'integer', 'min:0'],
        ]);

        EduGrupo::create([...$data, 'activo' => true, 'created_by' => $request->user()->email]);

        return redirect()->route('admin.edu.grupos.index')
            ->with('status', 'Grupo creado.');
    }

    public function update(Request $request, EduGrupo $grupo): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($grupo->institucion?->compania_id === $companiaId, 404);

        $data = $request->validate([
            'sede_id'   => ['nullable', 'integer', 'exists:edu_sedes,id'],
            'grado_id'  => ['nullable', 'integer', 'exists:edu_grados,id'],
            'codigo'    => ['required', 'string', 'max:30'],
            'nombre'    => ['required', 'string', 'max:200'],
            'jornada'   => ['nullable', 'string', 'in:manana,tarde,nocturna,fin_de_semana'],
            'capacidad' => ['nullable', 'integer', 'min:0'],
            'activo'    => ['boolean'],
        ]);

        $grupo->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.edu.grupos.index')
            ->with('status', 'Grupo actualizado.');
    }

    public function destroy(Request $request, EduGrupo $grupo): RedirectResponse
    {
        abort_unless($request->user()->can('edu.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($grupo->institucion?->compania_id === $companiaId, 404);

        $grupo->delete();

        return redirect()->route('admin.edu.grupos.index')
            ->with('status', 'Grupo eliminado.');
    }
}
