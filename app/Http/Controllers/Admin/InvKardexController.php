<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\InvAlmacen;
use App\Models\InvKardex;
use App\Models\ItemProducto;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvKardexController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);
        $itemId     = $request->query('item_id');
        $almacenId  = $request->query('almacen_id');
        $desde      = $request->query('desde', now()->startOfMonth()->toDateString());
        $hasta      = $request->query('hasta', now()->toDateString());

        $kardex = InvKardex::with(['item', 'almacen'])
            ->where('compania_id', $companiaId)
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId))
            ->when($almacenId, fn ($q) => $q->where('almacen_id', $almacenId))
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderBy('fecha')->orderBy('id')
            ->paginate(50)->withQueryString();

        $items    = ItemProducto::where('compania_id', $companiaId)->where('activo', true)->orderBy('codigo')->get();
        $almacenes = InvAlmacen::where('compania_id', $companiaId)->where('activo', true)->orderBy('codigo')->get();

        return view('admin.inventario.kardex.index', compact('kardex', 'items', 'almacenes', 'itemId', 'almacenId', 'desde', 'hasta'));
    }
}
