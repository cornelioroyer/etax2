<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\TallerSucursal;
use App\Models\TallerTaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallerSucursalController extends Controller
{
    use ConCompaniaActiva;

    private function resolverTaller(Request $request, ?int $tallerId = null): TallerTaller
    {
        $id = $tallerId ?? $request->input('taller_id');
        $taller = TallerTaller::findOrFail($id);
        abort_unless($taller->compania_id === $this->companiaActivaId($request), 404);
        return $taller;
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('taller.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $tallerId = $request->input('taller_id');
        $search   = trim($request->input('q', ''));

        $query = TallerSucursal::whereHas('taller', fn ($q) => $q->where('compania_id', $companiaId))
            ->with('taller')
            ->when($tallerId, fn ($q) => $q->where('taller_id', $tallerId))
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%")
                ->orWhere('codigo', 'ilike', "%{$search}%"))
            ->orderBy('codigo');

        $sucursales = $query->paginate(20)->withQueryString();
        $talleres   = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerActual = $tallerId ? TallerTaller::find($tallerId) : null;

        return view('admin.taller.sucursales.index', compact('sucursales', 'talleres', 'tallerActual', 'search', 'tallerId'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        $talleres   = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerId   = $request->input('taller_id');
        return view('admin.taller.sucursales.create', compact('talleres', 'tallerId'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);

        $data = $request->validate([
            'taller_id' => ['required', 'integer', 'exists:taller_talleres,id'],
            'codigo'    => ['required', 'string', 'max:30'],
            'nombre'    => ['required', 'string', 'max:200'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'telefono'  => ['nullable', 'string', 'max:50'],
            'email'     => ['nullable', 'email', 'max:200'],
        ]);

        $this->resolverTaller($request, (int) $data['taller_id']);

        $sucursal = TallerSucursal::create([
            ...$data,
            'activo'     => true,
            'created_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.sucursales.index', ['taller_id' => $sucursal->taller_id])
            ->with('status', "Sucursal {$sucursal->nombre} creada.");
    }

    public function edit(Request $request, TallerSucursal $sucursal): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $sucursal->taller_id);
        $companiaId = $this->companiaActivaId($request);
        $talleres   = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        return view('admin.taller.sucursales.edit', compact('sucursal', 'talleres'));
    }

    public function update(Request $request, TallerSucursal $sucursal): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $sucursal->taller_id);

        $data = $request->validate([
            'codigo'    => ['required', 'string', 'max:30'],
            'nombre'    => ['required', 'string', 'max:200'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'telefono'  => ['nullable', 'string', 'max:50'],
            'email'     => ['nullable', 'email', 'max:200'],
            'activo'    => ['boolean'],
        ]);

        $sucursal->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.taller.sucursales.index', ['taller_id' => $sucursal->taller_id])
            ->with('status', 'Sucursal actualizada.');
    }

    public function destroy(Request $request, TallerSucursal $sucursal): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $sucursal->taller_id);

        if ($sucursal->areas()->exists()) {
            return back()->withErrors(['destroy' => 'No se puede eliminar: la sucursal tiene áreas registradas.']);
        }

        $tallerId = $sucursal->taller_id;
        $sucursal->delete();

        return redirect()->route('admin.taller.sucursales.index', ['taller_id' => $tallerId])
            ->with('status', 'Sucursal eliminada.');
    }
}
