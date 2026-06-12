<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Zona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZonaController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $zonas = Zona::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('description', 'ilike', "%{$search}%");
            })
            ->orderBy('description')
            ->paginate(15)
            ->withQueryString();

        return view('admin.zonas.index', compact('zonas', 'search'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->can('zonas.crear'), 403);

        return view('admin.zonas.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('zonas.crear'), 403);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
        ]);

        $data['created_by'] = $request->user()->email;

        Zona::create($data);

        return redirect()->route('admin.zonas.index')->with('status', 'Zona creada.');
    }

    public function edit(Zona $zona): View
    {
        abort_unless(auth()->user()->can('zonas.editar'), 403);

        return view('admin.zonas.edit', compact('zona'));
    }

    public function update(Request $request, Zona $zona): RedirectResponse
    {
        abort_unless($request->user()->can('zonas.editar'), 403);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
        ]);

        $data['updated_by'] = $request->user()->email;

        $zona->update($data);

        return redirect()->route('admin.zonas.index')->with('status', 'Zona actualizada.');
    }

    public function destroy(Zona $zona): RedirectResponse
    {
        abort_unless(auth()->user()->can('zonas.eliminar'), 403);

        if ($zona->companias()->exists()) {
            return back()->withErrors(['zona' => 'No se puede eliminar: la zona tiene compañías asociadas.']);
        }

        $zona->delete();

        return redirect()->route('admin.zonas.index')->with('status', 'Zona eliminada.');
    }
}
