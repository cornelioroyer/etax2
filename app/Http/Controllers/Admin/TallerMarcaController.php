<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\TallerMarca;
use App\Models\TallerTaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallerMarcaController extends Controller
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

        $marcas = TallerMarca::whereHas('taller', fn ($q) => $q->where('compania_id', $companiaId))
            ->with('taller')
            ->when($tallerId, fn ($q) => $q->where('taller_id', $tallerId))
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%")
                ->orWhere('codigo', 'ilike', "%{$search}%"))
            ->withCount('modelos')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        $talleres     = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerActual = $tallerId ? TallerTaller::find($tallerId) : null;

        return view('admin.taller.marcas.index', compact('marcas', 'talleres', 'tallerActual', 'search', 'tallerId'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $talleres   = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerId   = $request->input('taller_id');
        return view('admin.taller.marcas.create', compact('talleres', 'tallerId'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);

        $data = $request->validate([
            'taller_id' => ['required', 'integer', 'exists:taller_talleres,id'],
            'codigo'    => ['required', 'string', 'max:30'],
            'nombre'    => ['required', 'string', 'max:200'],
        ]);

        $this->resolverTaller($request, (int) $data['taller_id']);

        $marca = TallerMarca::create([
            ...$data,
            'activo'     => true,
            'created_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.marcas.index', ['taller_id' => $marca->taller_id])
            ->with('status', "Marca {$marca->nombre} creada.");
    }

    public function edit(Request $request, TallerMarca $marca): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $marca->taller_id);
        $companiaId = $this->companiaActivaId($request);
        $talleres   = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        return view('admin.taller.marcas.edit', compact('marca', 'talleres'));
    }

    public function update(Request $request, TallerMarca $marca): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $marca->taller_id);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:30'],
            'nombre' => ['required', 'string', 'max:200'],
            'activo' => ['boolean'],
        ]);

        $marca->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.taller.marcas.index', ['taller_id' => $marca->taller_id])
            ->with('status', 'Marca actualizada.');
    }

    public function destroy(Request $request, TallerMarca $marca): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $marca->taller_id);

        if ($marca->modelos()->exists()) {
            return back()->withErrors(['destroy' => 'No se puede eliminar: la marca tiene modelos registrados.']);
        }

        $tallerId = $marca->taller_id;
        $marca->delete();

        return redirect()->route('admin.taller.marcas.index', ['taller_id' => $tallerId])
            ->with('status', 'Marca eliminada.');
    }
}
