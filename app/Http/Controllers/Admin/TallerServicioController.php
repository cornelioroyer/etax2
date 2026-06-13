<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\TallerEspecialidad;
use App\Models\TallerServicioEstandar;
use App\Models\TallerTaller;
use App\Models\TallerTipoEquipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallerServicioController extends Controller
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

        $servicios = TallerServicioEstandar::whereHas('taller', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['taller', 'tipoEquipo', 'especialidad'])
            ->when($tallerId, fn ($q) => $q->where('taller_id', $tallerId))
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%")
                ->orWhere('codigo', 'ilike', "%{$search}%"))
            ->orderBy('codigo')
            ->paginate(20)
            ->withQueryString();

        $talleres     = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerActual = $tallerId ? TallerTaller::find($tallerId) : null;

        return view('admin.taller.servicios.index', compact('servicios', 'talleres', 'tallerActual', 'search', 'tallerId'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId  = $this->companiaActivaId($request);
        $talleres    = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerId    = $request->input('taller_id');
        $tiposEquipo = $tallerId ? TallerTipoEquipo::where('taller_id', $tallerId)->orderBy('nombre')->get() : collect();
        $especialidades = $tallerId ? TallerEspecialidad::where('taller_id', $tallerId)->orderBy('nombre')->get() : collect();
        return view('admin.taller.servicios.create', compact('talleres', 'tallerId', 'tiposEquipo', 'especialidades'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);

        $data = $request->validate([
            'taller_id'           => ['required', 'integer', 'exists:taller_talleres,id'],
            'tipo_equipo_id'      => ['nullable', 'integer', 'exists:taller_tipos_equipo,id'],
            'especialidad_id'     => ['nullable', 'integer', 'exists:taller_especialidades,id'],
            'codigo'              => ['required', 'string', 'max:30'],
            'nombre'              => ['required', 'string', 'max:200'],
            'descripcion'         => ['nullable', 'string', 'max:1000'],
            'tiempo_estimado_min' => ['nullable', 'integer', 'min:0'],
            'precio_base'         => ['nullable', 'numeric', 'min:0'],
            'costo_base'          => ['nullable', 'numeric', 'min:0'],
            'requiere_aprobacion' => ['boolean'],
            'garantia_dias'       => ['nullable', 'integer', 'min:0'],
        ]);

        $this->resolverTaller($request, (int) $data['taller_id']);

        $servicio = TallerServicioEstandar::create([
            ...$data,
            'activo'     => true,
            'created_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.servicios.index', ['taller_id' => $servicio->taller_id])
            ->with('status', "Servicio {$servicio->nombre} creado.");
    }

    public function edit(Request $request, TallerServicioEstandar $servicio): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $servicio->taller_id);
        $companiaId     = $this->companiaActivaId($request);
        $talleres       = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tiposEquipo    = TallerTipoEquipo::where('taller_id', $servicio->taller_id)->orderBy('nombre')->get();
        $especialidades = TallerEspecialidad::where('taller_id', $servicio->taller_id)->orderBy('nombre')->get();
        return view('admin.taller.servicios.edit', compact('servicio', 'talleres', 'tiposEquipo', 'especialidades'));
    }

    public function update(Request $request, TallerServicioEstandar $servicio): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $servicio->taller_id);

        $data = $request->validate([
            'tipo_equipo_id'      => ['nullable', 'integer', 'exists:taller_tipos_equipo,id'],
            'especialidad_id'     => ['nullable', 'integer', 'exists:taller_especialidades,id'],
            'codigo'              => ['required', 'string', 'max:30'],
            'nombre'              => ['required', 'string', 'max:200'],
            'descripcion'         => ['nullable', 'string', 'max:1000'],
            'tiempo_estimado_min' => ['nullable', 'integer', 'min:0'],
            'precio_base'         => ['nullable', 'numeric', 'min:0'],
            'costo_base'          => ['nullable', 'numeric', 'min:0'],
            'requiere_aprobacion' => ['boolean'],
            'garantia_dias'       => ['nullable', 'integer', 'min:0'],
            'activo'              => ['boolean'],
        ]);

        $servicio->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.taller.servicios.index', ['taller_id' => $servicio->taller_id])
            ->with('status', 'Servicio actualizado.');
    }

    public function destroy(Request $request, TallerServicioEstandar $servicio): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $servicio->taller_id);

        $tallerId = $servicio->taller_id;
        $servicio->delete();

        return redirect()->route('admin.taller.servicios.index', ['taller_id' => $tallerId])
            ->with('status', 'Servicio eliminado.');
    }
}
