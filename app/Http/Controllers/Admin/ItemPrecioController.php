<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\ItemPrecio;
use App\Models\ItemProducto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ItemPrecioController extends Controller
{
    use ConCompaniaActiva;

    public function store(Request $request, ItemProducto $item): RedirectResponse
    {
        abort_unless($request->user()->can('inventario.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);
        abort_unless($item->compania_id === $companiaId, 404);

        $data = $request->validate([
            'lista'        => ['required', 'string', 'max:50'],
            'precio'       => ['required', 'numeric', 'min:0'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin'    => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        ItemPrecio::create(['item_id' => $item->id, ...$data, 'created_by' => $request->user()->email]);

        return back()->with('status', "Precio lista '{$data['lista']}' agregado.");
    }

    public function update(Request $request, ItemProducto $item, ItemPrecio $precio): RedirectResponse
    {
        abort_unless($request->user()->can('inventario.gestionar'), 403);
        abort_unless($item->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($precio->item_id === $item->id, 404);

        $data = $request->validate([
            'precio'    => ['required', 'numeric', 'min:0'],
            'fecha_fin' => ['nullable', 'date'],
        ]);

        $precio->update([...$data, 'updated_by' => $request->user()->email]);

        return back()->with('status', 'Precio actualizado.');
    }

    public function destroy(Request $request, ItemProducto $item, ItemPrecio $precio): RedirectResponse
    {
        abort_unless($request->user()->can('inventario.gestionar'), 403);
        abort_unless($item->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($precio->item_id === $item->id, 404);

        $precio->delete();

        return back()->with('status', 'Precio eliminado.');
    }
}
