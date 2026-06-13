<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\TallerEspecialidad;
use App\Models\TallerTaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallerEspecialidadController extends Controller
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

        $especialidades = TallerEspecialidad::whereHas('taller', fn ($q) => $q->where('compania_id', $companiaId))
            ->with('taller')
            ->when($tallerId, fn ($q) => $q->where('taller_id', $tallerId))
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%")
                ->orWhere('codigo', 'ilike', "%{$search}%"))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        $talleres     = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerActual = $tallerId ? TallerTaller::find($tallerId) : null;

        return view('admin.taller.especialidades.index', compact('especialidades', 'talleres', 'tallerActual', 'search', 'tallerId'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $talleres   = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerId   = $request->input('taller_id');
        return view('admin.taller.especialidades.create', compact('talleres', 'tallerId'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);

        $data = $request->validate([
            'taller_id'   => ['required', 'integer', 'exists:taller_talleres,id'],
            'codigo'      => ['required', 'string', 'max:30'],
            'nombre'      => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->resolverTaller($request, (int) $data['taller_id']);

        $esp = TallerEspecialidad::create([
            ...$data,
            'activo'     => true,
            'created_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.especialidades.index', ['taller_id' => $esp->taller_id])
            ->with('status', "Especialidad {$esp->nombre} creada.");
    }

    public function edit(Request $request, TallerEspecialidad $especialidad): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $especialidad->taller_id);
        $companiaId = $this->companiaActivaId($request);
        $talleres   = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        return view('admin.taller.especialidades.edit', compact('especialidad', 'talleres'));
    }

    public function update(Request $request, TallerEspecialidad $especialidad): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $especialidad->taller_id);

        $data = $request->validate([
            'codigo'      => ['required', 'string', 'max:30'],
            'nombre'      => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'activo'      => ['boolean'],
        ]);

        $especialidad->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.taller.especialidades.index', ['taller_id' => $especialidad->taller_id])
            ->with('status', 'Especialidad actualizada.');
    }

    public function destroy(Request $request, TallerEspecialidad $especialidad): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $especialidad->taller_id);

        $tallerId = $especialidad->taller_id;
        $especialidad->delete();

        return redirect()->route('admin.taller.especialidades.index', ['taller_id' => $tallerId])
            ->with('status', 'Especialidad eliminada.');
    }
}
