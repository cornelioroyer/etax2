<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\TallerArea;
use App\Models\TallerSucursal;
use App\Models\TallerTaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallerAreaController extends Controller
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

        $areas = TallerArea::whereHas('taller', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['taller', 'sucursal'])
            ->when($tallerId, fn ($q) => $q->where('taller_id', $tallerId))
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%")
                ->orWhere('codigo', 'ilike', "%{$search}%"))
            ->orderBy('codigo')
            ->paginate(20)
            ->withQueryString();

        $talleres     = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerActual = $tallerId ? TallerTaller::find($tallerId) : null;

        return view('admin.taller.areas.index', compact('areas', 'talleres', 'tallerActual', 'search', 'tallerId'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $talleres   = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerId   = $request->input('taller_id');
        $sucursales = $tallerId
            ? TallerSucursal::where('taller_id', $tallerId)->orderBy('nombre')->get()
            : collect();
        return view('admin.taller.areas.create', compact('talleres', 'sucursales', 'tallerId'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);

        $data = $request->validate([
            'taller_id'   => ['required', 'integer', 'exists:taller_talleres,id'],
            'sucursal_id' => ['nullable', 'integer', 'exists:taller_sucursales,id'],
            'codigo'      => ['required', 'string', 'max:30'],
            'nombre'      => ['required', 'string', 'max:200'],
            'tipo_area'   => ['required', 'string', 'in:' . implode(',', array_keys(TallerArea::TIPOS))],
            'capacidad'   => ['nullable', 'integer', 'min:0'],
        ]);

        $this->resolverTaller($request, (int) $data['taller_id']);

        $area = TallerArea::create([
            ...$data,
            'activo'     => true,
            'created_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.areas.index', ['taller_id' => $area->taller_id])
            ->with('status', "Área {$area->nombre} creada.");
    }

    public function edit(Request $request, TallerArea $area): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $area->taller_id);
        $companiaId = $this->companiaActivaId($request);
        $talleres   = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $sucursales = TallerSucursal::where('taller_id', $area->taller_id)->orderBy('nombre')->get();
        return view('admin.taller.areas.edit', compact('area', 'talleres', 'sucursales'));
    }

    public function update(Request $request, TallerArea $area): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $area->taller_id);

        $data = $request->validate([
            'sucursal_id' => ['nullable', 'integer', 'exists:taller_sucursales,id'],
            'codigo'      => ['required', 'string', 'max:30'],
            'nombre'      => ['required', 'string', 'max:200'],
            'tipo_area'   => ['required', 'string', 'in:' . implode(',', array_keys(TallerArea::TIPOS))],
            'capacidad'   => ['nullable', 'integer', 'min:0'],
            'activo'      => ['boolean'],
        ]);

        $area->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.taller.areas.index', ['taller_id' => $area->taller_id])
            ->with('status', 'Área actualizada.');
    }

    public function destroy(Request $request, TallerArea $area): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $area->taller_id);

        $tallerId = $area->taller_id;
        $area->delete();

        return redirect()->route('admin.taller.areas.index', ['taller_id' => $tallerId])
            ->with('status', 'Área eliminada.');
    }
}
