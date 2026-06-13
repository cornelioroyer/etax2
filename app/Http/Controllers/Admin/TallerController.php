<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\TallerTaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallerController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('taller.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $search = trim($request->input('q', ''));
        $talleres = TallerTaller::where('compania_id', $companiaId)
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%")
                ->orWhere('codigo', 'ilike', "%{$search}%"))
            ->withCount(['sucursales', 'tecnicos'])
            ->orderBy('codigo')
            ->paginate(20)
            ->withQueryString();

        return view('admin.taller.talleres.index', compact('talleres', 'search'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        return view('admin.taller.talleres.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo'      => ['required', 'string', 'max:30'],
            'nombre'      => ['required', 'string', 'max:200'],
            'tipo_taller' => ['required', 'string', 'in:' . implode(',', array_keys(TallerTaller::TIPOS))],
            'direccion'   => ['nullable', 'string', 'max:500'],
            'telefono'    => ['nullable', 'string', 'max:50'],
            'email'       => ['nullable', 'email', 'max:200'],
        ]);

        $taller = TallerTaller::create([
            ...$data,
            'compania_id' => $companiaId,
            'activo'      => true,
            'created_by'  => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.talleres.show', $taller)
            ->with('status', "Taller {$taller->nombre} creado.");
    }

    public function show(Request $request, TallerTaller $taller): View
    {
        abort_unless($request->user()->can('taller.ver'), 403);
        abort_unless($taller->compania_id === $this->companiaActivaId($request), 404);

        $taller->load(['sucursales', 'areas.sucursal', 'tecnicos', 'tiposEquipo', 'marcas', 'especialidades']);

        return view('admin.taller.talleres.show', compact('taller'));
    }

    public function edit(Request $request, TallerTaller $taller): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($taller->compania_id === $this->companiaActivaId($request), 404);

        return view('admin.taller.talleres.edit', compact('taller'));
    }

    public function update(Request $request, TallerTaller $taller): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($taller->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'codigo'      => ['required', 'string', 'max:30'],
            'nombre'      => ['required', 'string', 'max:200'],
            'tipo_taller' => ['required', 'string', 'in:' . implode(',', array_keys(TallerTaller::TIPOS))],
            'direccion'   => ['nullable', 'string', 'max:500'],
            'telefono'    => ['nullable', 'string', 'max:50'],
            'email'       => ['nullable', 'email', 'max:200'],
            'activo'      => ['boolean'],
        ]);

        $taller->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.taller.talleres.show', $taller)
            ->with('status', 'Taller actualizado.');
    }

    public function destroy(Request $request, TallerTaller $taller): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($taller->compania_id === $this->companiaActivaId($request), 404);

        if ($taller->sucursales()->exists()) {
            return back()->withErrors(['destroy' => 'No se puede eliminar: el taller tiene sucursales registradas.']);
        }

        $taller->delete();

        return redirect()->route('admin.taller.talleres.index')
            ->with('status', 'Taller eliminado.');
    }
}
