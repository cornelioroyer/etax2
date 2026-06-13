<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\PhPropietario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PhPropietarioController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('ph.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $search = trim($request->input('q', ''));
        $propietarios = PhPropietario::where('compania_id', $companiaId)
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%")
                ->orWhere('identificacion', 'ilike', "%{$search}%"))
            ->withCount('unidades')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('admin.ph.propietarios.index', compact('propietarios', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'identificacion' => ['nullable', 'string', 'max:50'],
            'nombre'         => ['required', 'string', 'max:300'],
            'email'          => ['nullable', 'email', 'max:200'],
            'telefono'       => ['nullable', 'string', 'max:50'],
            'direccion'      => ['nullable', 'string', 'max:500'],
        ]);

        PhPropietario::create([
            ...$data,
            'compania_id' => $companiaId,
            'created_by'  => $request->user()->email,
        ]);

        return back()->with('status', 'Propietario registrado.');
    }

    public function update(Request $request, PhPropietario $propietario): RedirectResponse
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        abort_unless($propietario->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'identificacion' => ['nullable', 'string', 'max:50'],
            'nombre'         => ['required', 'string', 'max:300'],
            'email'          => ['nullable', 'email', 'max:200'],
            'telefono'       => ['nullable', 'string', 'max:50'],
            'direccion'      => ['nullable', 'string', 'max:500'],
            'activo'         => ['boolean'],
        ]);

        $propietario->update([...$data, 'updated_by' => $request->user()->email]);

        return back()->with('status', 'Propietario actualizado.');
    }

    public function destroy(Request $request, PhPropietario $propietario): RedirectResponse
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        abort_unless($propietario->compania_id === $this->companiaActivaId($request), 404);

        if ($propietario->unidades()->exists()) {
            return back()->withErrors(['destroy' => 'No se puede eliminar: el propietario tiene unidades asignadas.']);
        }

        $propietario->delete();

        return back()->with('status', 'Propietario eliminado.');
    }
}
