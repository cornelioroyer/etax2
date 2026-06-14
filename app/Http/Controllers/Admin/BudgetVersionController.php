<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\BudgetVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BudgetVersionController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('presupuestos.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $search = trim($request->input('q', ''));

        $versiones = BudgetVersion::where('compania_id', $companiaId)
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%"))
            ->withCount('presupuestos')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('admin.presupuestos.versiones.index', compact('versiones', 'search'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        $this->companiaActivaId($request);

        return view('admin.presupuestos.versiones.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $this->validar($request, $companiaId);

        BudgetVersion::create([
            'compania_id' => $companiaId,
            'nombre'      => $data['nombre'],
            'activa'      => $request->boolean('activa'),
            'created_by'  => $request->user()->email,
        ]);

        return redirect()->route('admin.presupuestos.versiones.index')
            ->with('status', "Versión «{$data['nombre']}» creada.");
    }

    public function edit(Request $request, BudgetVersion $version): View
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($version->compania_id === $this->companiaActivaId($request), 404);

        return view('admin.presupuestos.versiones.edit', compact('version'));
    }

    public function update(Request $request, BudgetVersion $version): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($version->compania_id === $this->companiaActivaId($request), 404);

        $data = $this->validar($request, $version->compania_id, $version->id);

        $version->update([
            'nombre'     => $data['nombre'],
            'activa'     => $request->boolean('activa'),
            'updated_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.presupuestos.versiones.index')
            ->with('status', 'Versión actualizada.');
    }

    public function destroy(Request $request, BudgetVersion $version): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($version->compania_id === $this->companiaActivaId($request), 404);
        abort_if($version->presupuestos()->exists(), 422, 'No se puede eliminar: tiene presupuestos asociados.');

        $version->delete();

        return redirect()->route('admin.presupuestos.versiones.index')
            ->with('status', 'Versión eliminada.');
    }

    private function validar(Request $request, int $companiaId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('budget_versiones')
                    ->where(fn ($q) => $q->where('compania_id', $companiaId))
                    ->ignore($ignoreId),
            ],
            'activa' => ['nullable', 'boolean'],
        ]);
    }
}
