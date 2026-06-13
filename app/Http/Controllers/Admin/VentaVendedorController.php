<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Models\VentaComision;
use App\Models\VentaVendedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VentaVendedorController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $vendedores = VentaVendedor::with('contacto')
            ->where('compania_id', $companiaId)
            ->orderBy('codigo')
            ->get();

        return view('admin.ventas.vendedores.index', compact('vendedores'));
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);
        $contactos  = Contacto::where('compania_id', $companiaId)->where('activo', true)->orderBy('nombre')->get();

        return view('admin.ventas.vendedores.create', compact('contactos'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('ventas.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo'      => ['required', 'string', 'max:30',
                Rule::unique('ventas_vendedores')->where('compania_id', $companiaId)],
            'contacto_id' => ['nullable', 'integer', 'exists:contact_contactos,id'],
        ]);

        VentaVendedor::create([
            'compania_id' => $companiaId,
            'codigo'      => strtoupper($data['codigo']),
            'contacto_id' => $data['contacto_id'] ?? null,
            'activo'      => true,
            'created_by'  => $request->user()->email,
        ]);

        return redirect()->route('admin.ventas.vendedores.index')->with('status', "Vendedor {$data['codigo']} creado.");
    }

    public function show(Request $request, VentaVendedor $vendedor): View
    {
        abort_unless($vendedor->compania_id === $this->companiaActivaId($request), 404);

        $comisiones = VentaComision::with('factura')
            ->where('vendedor_id', $vendedor->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.ventas.vendedores.show', compact('vendedor', 'comisiones'));
    }

    public function update(Request $request, VentaVendedor $vendedor): RedirectResponse
    {
        abort_unless($request->user()->can('ventas.gestionar'), 403);
        abort_unless($vendedor->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'contacto_id' => ['nullable', 'integer', 'exists:contact_contactos,id'],
        ]);

        $vendedor->update([...$data, 'updated_by' => $request->user()->email]);

        return back()->with('status', 'Vendedor actualizado.');
    }

    public function toggle(Request $request, VentaVendedor $vendedor): RedirectResponse
    {
        abort_unless($request->user()->can('ventas.gestionar'), 403);
        abort_unless($vendedor->compania_id === $this->companiaActivaId($request), 404);

        $vendedor->update(['activo' => ! $vendedor->activo, 'updated_by' => $request->user()->email]);

        return back()->with('status', "Vendedor {$vendedor->codigo} " . ($vendedor->activo ? 'activado' : 'desactivado') . '.');
    }
}
