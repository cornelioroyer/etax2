<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\TallerMarca;
use App\Models\TallerModelo;
use App\Models\TallerTaller;
use App\Models\TallerTipoEquipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallerModeloController extends Controller
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

        $modelos = TallerModelo::whereHas('taller', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['taller', 'marca', 'tipoEquipo'])
            ->when($tallerId, fn ($q) => $q->where('taller_id', $tallerId))
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%")
                ->orWhere('codigo', 'ilike', "%{$search}%"))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        $talleres     = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerActual = $tallerId ? TallerTaller::find($tallerId) : null;

        return view('admin.taller.modelos.index', compact('modelos', 'talleres', 'tallerActual', 'search', 'tallerId'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $talleres   = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerId   = $request->input('taller_id');
        $marcas     = $tallerId ? TallerMarca::where('taller_id', $tallerId)->orderBy('nombre')->get() : collect();
        $tiposEquipo= $tallerId ? TallerTipoEquipo::where('taller_id', $tallerId)->orderBy('nombre')->get() : collect();
        return view('admin.taller.modelos.create', compact('talleres', 'tallerId', 'marcas', 'tiposEquipo'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);

        $data = $request->validate([
            'taller_id'      => ['required', 'integer', 'exists:taller_talleres,id'],
            'marca_id'       => ['required', 'integer', 'exists:taller_marcas,id'],
            'tipo_equipo_id' => ['nullable', 'integer', 'exists:taller_tipos_equipo,id'],
            'codigo'         => ['required', 'string', 'max:30'],
            'nombre'         => ['required', 'string', 'max:200'],
            'anio_desde'     => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'anio_hasta'     => ['nullable', 'integer', 'min:1900', 'max:2100'],
        ]);

        $this->resolverTaller($request, (int) $data['taller_id']);

        $modelo = TallerModelo::create([
            ...$data,
            'activo'     => true,
            'created_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.modelos.index', ['taller_id' => $modelo->taller_id])
            ->with('status', "Modelo {$modelo->nombre} creado.");
    }

    public function edit(Request $request, TallerModelo $modelo): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $modelo->taller_id);
        $companiaId = $this->companiaActivaId($request);
        $talleres   = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $marcas     = TallerMarca::where('taller_id', $modelo->taller_id)->orderBy('nombre')->get();
        $tiposEquipo= TallerTipoEquipo::where('taller_id', $modelo->taller_id)->orderBy('nombre')->get();
        return view('admin.taller.modelos.edit', compact('modelo', 'talleres', 'marcas', 'tiposEquipo'));
    }

    public function update(Request $request, TallerModelo $modelo): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $modelo->taller_id);

        $data = $request->validate([
            'marca_id'       => ['required', 'integer', 'exists:taller_marcas,id'],
            'tipo_equipo_id' => ['nullable', 'integer', 'exists:taller_tipos_equipo,id'],
            'codigo'         => ['required', 'string', 'max:30'],
            'nombre'         => ['required', 'string', 'max:200'],
            'anio_desde'     => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'anio_hasta'     => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'activo'         => ['boolean'],
        ]);

        $modelo->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.taller.modelos.index', ['taller_id' => $modelo->taller_id])
            ->with('status', 'Modelo actualizado.');
    }

    public function destroy(Request $request, TallerModelo $modelo): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $modelo->taller_id);

        $tallerId = $modelo->taller_id;
        $modelo->delete();

        return redirect()->route('admin.taller.modelos.index', ['taller_id' => $tallerId])
            ->with('status', 'Modelo eliminado.');
    }
}
