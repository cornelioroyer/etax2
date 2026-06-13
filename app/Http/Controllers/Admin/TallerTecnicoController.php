<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\TallerEspecialidad;
use App\Models\TallerTaller;
use App\Models\TallerTecnico;
use App\Models\TallerTecnicoEspecialidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallerTecnicoController extends Controller
{
    use ConCompaniaActiva;

    private function resolverTaller(Request $request, int $tallerId): TallerTaller
    {
        $taller = TallerTaller::findOrFail($tallerId);
        abort_unless($taller->compania_id === $this->companiaActivaId($request), 404);
        return $taller;
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('taller.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $tallerId = $request->input('taller_id');
        $search   = trim($request->input('q', ''));

        $tecnicos = TallerTecnico::whereHas('taller', fn ($q) => $q->where('compania_id', $companiaId))
            ->with('taller')
            ->when($tallerId, fn ($q) => $q->where('taller_id', $tallerId))
            ->when($search !== '', fn ($q) => $q->where('nombre_publico', 'ilike', "%{$search}%")
                ->orWhere('codigo', 'ilike', "%{$search}%"))
            ->withCount('especialidades')
            ->orderBy('nombre_publico')
            ->paginate(20)
            ->withQueryString();

        $talleres     = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerActual = $tallerId ? TallerTaller::find($tallerId) : null;

        return view('admin.taller.tecnicos.index', compact('tecnicos', 'talleres', 'tallerActual', 'search', 'tallerId'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $talleres   = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerId   = $request->input('taller_id');
        return view('admin.taller.tecnicos.create', compact('talleres', 'tallerId'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);

        $data = $request->validate([
            'taller_id'            => ['required', 'integer', 'exists:taller_talleres,id'],
            'codigo'               => ['required', 'string', 'max:30'],
            'nombre_publico'       => ['required', 'string', 'max:200'],
            'tipo_tecnico'         => ['required', 'string', 'in:' . implode(',', array_keys(TallerTecnico::TIPOS))],
            'costo_hora'           => ['nullable', 'numeric', 'min:0'],
            'precio_hora'          => ['nullable', 'numeric', 'min:0'],
            'capacidad_horas_dia'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->resolverTaller($request, (int) $data['taller_id']);

        $tecnico = TallerTecnico::create([
            ...$data,
            'activo'     => true,
            'created_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.tecnicos.show', $tecnico)
            ->with('status', "Técnico {$tecnico->nombre_publico} creado.");
    }

    public function show(Request $request, TallerTecnico $tecnico): View
    {
        abort_unless($request->user()->can('taller.ver'), 403);
        $this->resolverTaller($request, $tecnico->taller_id);

        $tecnico->load(['taller', 'especialidades.especialidad']);
        $especialidadesDisponibles = TallerEspecialidad::where('taller_id', $tecnico->taller_id)
            ->orderBy('nombre')
            ->get();

        return view('admin.taller.tecnicos.show', compact('tecnico', 'especialidadesDisponibles'));
    }

    public function edit(Request $request, TallerTecnico $tecnico): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $tecnico->taller_id);
        $companiaId = $this->companiaActivaId($request);
        $talleres   = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        return view('admin.taller.tecnicos.edit', compact('tecnico', 'talleres'));
    }

    public function update(Request $request, TallerTecnico $tecnico): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $tecnico->taller_id);

        $data = $request->validate([
            'codigo'              => ['required', 'string', 'max:30'],
            'nombre_publico'      => ['required', 'string', 'max:200'],
            'tipo_tecnico'        => ['required', 'string', 'in:' . implode(',', array_keys(TallerTecnico::TIPOS))],
            'costo_hora'          => ['nullable', 'numeric', 'min:0'],
            'precio_hora'         => ['nullable', 'numeric', 'min:0'],
            'capacidad_horas_dia' => ['nullable', 'numeric', 'min:0'],
            'activo'              => ['boolean'],
        ]);

        $tecnico->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.taller.tecnicos.show', $tecnico)
            ->with('status', 'Técnico actualizado.');
    }

    public function destroy(Request $request, TallerTecnico $tecnico): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $tecnico->taller_id);

        $tallerId = $tecnico->taller_id;
        $tecnico->especialidades()->delete();
        $tecnico->delete();

        return redirect()->route('admin.taller.tecnicos.index', ['taller_id' => $tallerId])
            ->with('status', 'Técnico eliminado.');
    }

    // ── Gestión inline de especialidades ────────────────────────────────────

    public function storeEspecialidad(Request $request, TallerTecnico $tecnico): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $tecnico->taller_id);

        $data = $request->validate([
            'especialidad_id' => ['required', 'integer', 'exists:taller_especialidades,id'],
            'nivel'           => ['nullable', 'string', 'max:50'],
        ]);

        // Evitar duplicado
        TallerTecnicoEspecialidad::firstOrCreate(
            ['tecnico_id' => $tecnico->id, 'especialidad_id' => $data['especialidad_id']],
            ['nivel' => $data['nivel'] ?? null, 'activo' => true]
        );

        return redirect()->route('admin.taller.tecnicos.show', $tecnico)
            ->with('status', 'Especialidad agregada.');
    }

    public function destroyEspecialidad(Request $request, TallerTecnico $tecnico, TallerTecnicoEspecialidad $especialidad): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $tecnico->taller_id);
        abort_unless($especialidad->tecnico_id === $tecnico->id, 404);

        $especialidad->delete();

        return redirect()->route('admin.taller.tecnicos.show', $tecnico)
            ->with('status', 'Especialidad removida.');
    }
}
