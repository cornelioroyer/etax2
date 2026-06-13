<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\TallerSintoma;
use App\Models\TallerTaller;
use App\Models\TallerTipoEquipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallerSintomaController extends Controller
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

        $sintomas = TallerSintoma::whereHas('taller', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['taller', 'tipoEquipo'])
            ->when($tallerId, fn ($q) => $q->where('taller_id', $tallerId))
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%")
                ->orWhere('codigo', 'ilike', "%{$search}%"))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        $talleres     = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerActual = $tallerId ? TallerTaller::find($tallerId) : null;

        return view('admin.taller.sintomas.index', compact('sintomas', 'talleres', 'tallerActual', 'search', 'tallerId'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId  = $this->companiaActivaId($request);
        $talleres    = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerId    = $request->input('taller_id');
        $tiposEquipo = $tallerId ? TallerTipoEquipo::where('taller_id', $tallerId)->orderBy('nombre')->get() : collect();
        return view('admin.taller.sintomas.create', compact('talleres', 'tallerId', 'tiposEquipo'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);

        $data = $request->validate([
            'taller_id'      => ['required', 'integer', 'exists:taller_talleres,id'],
            'tipo_equipo_id' => ['nullable', 'integer', 'exists:taller_tipos_equipo,id'],
            'codigo'         => ['required', 'string', 'max:30'],
            'nombre'         => ['required', 'string', 'max:200'],
            'descripcion'    => ['nullable', 'string', 'max:1000'],
        ]);

        $this->resolverTaller($request, (int) $data['taller_id']);

        $sintoma = TallerSintoma::create([
            ...$data,
            'activo'     => true,
            'created_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.sintomas.index', ['taller_id' => $sintoma->taller_id])
            ->with('status', "Síntoma {$sintoma->nombre} creado.");
    }

    public function edit(Request $request, TallerSintoma $sintoma): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $sintoma->taller_id);
        $companiaId  = $this->companiaActivaId($request);
        $talleres    = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tiposEquipo = TallerTipoEquipo::where('taller_id', $sintoma->taller_id)->orderBy('nombre')->get();
        return view('admin.taller.sintomas.edit', compact('sintoma', 'talleres', 'tiposEquipo'));
    }

    public function update(Request $request, TallerSintoma $sintoma): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $sintoma->taller_id);

        $data = $request->validate([
            'tipo_equipo_id' => ['nullable', 'integer', 'exists:taller_tipos_equipo,id'],
            'codigo'         => ['required', 'string', 'max:30'],
            'nombre'         => ['required', 'string', 'max:200'],
            'descripcion'    => ['nullable', 'string', 'max:1000'],
            'activo'         => ['boolean'],
        ]);

        $sintoma->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.taller.sintomas.index', ['taller_id' => $sintoma->taller_id])
            ->with('status', 'Síntoma actualizado.');
    }

    public function destroy(Request $request, TallerSintoma $sintoma): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $sintoma->taller_id);

        $tallerId = $sintoma->taller_id;
        $sintoma->delete();

        return redirect()->route('admin.taller.sintomas.index', ['taller_id' => $tallerId])
            ->with('status', 'Síntoma eliminado.');
    }
}
