<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\BudgetEscenario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetEscenarioController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('presupuestos.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $search = trim($request->input('q', ''));

        $escenarios = BudgetEscenario::where('compania_id', $companiaId)
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%"))
            ->withCount('presupuestos')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('admin.presupuestos.escenarios.index', compact('escenarios', 'search'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        $this->companiaActivaId($request);

        return view('admin.presupuestos.escenarios.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
        ]);

        BudgetEscenario::create([
            ...$data,
            'compania_id' => $companiaId,
            'created_by'  => $request->user()->email,
        ]);

        return redirect()->route('admin.presupuestos.escenarios.index')
            ->with('status', "Escenario «{$data['nombre']}» creado.");
    }

    public function edit(Request $request, BudgetEscenario $escenario): View
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($escenario->compania_id === $this->companiaActivaId($request), 404);

        return view('admin.presupuestos.escenarios.edit', compact('escenario'));
    }

    public function update(Request $request, BudgetEscenario $escenario): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($escenario->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
        ]);

        $escenario->update([
            ...$data,
            'updated_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.presupuestos.escenarios.index')
            ->with('status', 'Escenario actualizado.');
    }

    public function destroy(Request $request, BudgetEscenario $escenario): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($escenario->compania_id === $this->companiaActivaId($request), 404);
        abort_if($escenario->presupuestos()->exists(), 422, 'No se puede eliminar: tiene presupuestos asociados.');

        $escenario->delete();

        return redirect()->route('admin.presupuestos.escenarios.index')
            ->with('status', 'Escenario eliminado.');
    }
}
