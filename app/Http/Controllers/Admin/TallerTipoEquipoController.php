<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\TallerTaller;
use App\Models\TallerTipoEquipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallerTipoEquipoController extends Controller
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

        $tiposEquipo = TallerTipoEquipo::whereHas('taller', fn ($q) => $q->where('compania_id', $companiaId))
            ->with('taller')
            ->when($tallerId, fn ($q) => $q->where('taller_id', $tallerId))
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%")
                ->orWhere('codigo', 'ilike', "%{$search}%"))
            ->orderBy('codigo')
            ->paginate(20)
            ->withQueryString();

        $talleres     = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerActual = $tallerId ? TallerTaller::find($tallerId) : null;

        return view('admin.taller.tipos-equipo.index', compact('tiposEquipo', 'talleres', 'tallerActual', 'search', 'tallerId'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $talleres   = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerId   = $request->input('taller_id');
        return view('admin.taller.tipos-equipo.create', compact('talleres', 'tallerId'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);

        $data = $request->validate([
            'taller_id'        => ['required', 'integer', 'exists:taller_talleres,id'],
            'codigo'           => ['required', 'string', 'max:30'],
            'nombre'           => ['required', 'string', 'max:200'],
            'categoria'        => ['required', 'string', 'in:' . implode(',', array_keys(TallerTipoEquipo::CATEGORIAS))],
            'requiere_placa'   => ['boolean'],
            'requiere_vin'     => ['boolean'],
            'requiere_serie'   => ['boolean'],
            'requiere_medidor' => ['boolean'],
            'unidad_medidor'   => ['nullable', 'string', 'max:30'],
        ]);

        $this->resolverTaller($request, (int) $data['taller_id']);

        $tipo = TallerTipoEquipo::create([
            ...$data,
            'activo'     => true,
            'created_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.tipos-equipo.index', ['taller_id' => $tipo->taller_id])
            ->with('status', "Tipo de equipo {$tipo->nombre} creado.");
    }

    public function edit(Request $request, TallerTipoEquipo $tipoEquipo): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $tipoEquipo->taller_id);
        $companiaId = $this->companiaActivaId($request);
        $talleres   = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        return view('admin.taller.tipos-equipo.edit', compact('tipoEquipo', 'talleres'));
    }

    public function update(Request $request, TallerTipoEquipo $tipoEquipo): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $tipoEquipo->taller_id);

        $data = $request->validate([
            'codigo'           => ['required', 'string', 'max:30'],
            'nombre'           => ['required', 'string', 'max:200'],
            'categoria'        => ['required', 'string', 'in:' . implode(',', array_keys(TallerTipoEquipo::CATEGORIAS))],
            'requiere_placa'   => ['boolean'],
            'requiere_vin'     => ['boolean'],
            'requiere_serie'   => ['boolean'],
            'requiere_medidor' => ['boolean'],
            'unidad_medidor'   => ['nullable', 'string', 'max:30'],
            'activo'           => ['boolean'],
        ]);

        $tipoEquipo->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.taller.tipos-equipo.index', ['taller_id' => $tipoEquipo->taller_id])
            ->with('status', 'Tipo de equipo actualizado.');
    }

    public function destroy(Request $request, TallerTipoEquipo $tipoEquipo): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $tipoEquipo->taller_id);

        if ($tipoEquipo->modelos()->exists() || $tipoEquipo->sintomas()->exists()) {
            return back()->withErrors(['destroy' => 'No se puede eliminar: tiene modelos o síntomas asociados.']);
        }

        $tallerId = $tipoEquipo->taller_id;
        $tipoEquipo->delete();

        return redirect()->route('admin.taller.tipos-equipo.index', ['taller_id' => $tallerId])
            ->with('status', 'Tipo de equipo eliminado.');
    }
}
