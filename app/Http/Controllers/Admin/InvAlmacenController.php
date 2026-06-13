<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\InvAlmacen;
use App\Models\InvExistencia;
use App\Models\InvMovimiento;
use App\Models\ItemProducto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvAlmacenController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $almacenes = InvAlmacen::where('compania_id', $companiaId)->orderBy('codigo')->get();

        return view('admin.inventario.almacenes.index', ['almacenes' => $almacenes]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('inventario.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:30',
                Rule::unique('inv_almacenes')->where('compania_id', $companiaId)],
            'nombre' => ['required', 'string', 'max:150'],
        ]);

        InvAlmacen::create([
            'compania_id' => $companiaId,
            'codigo'      => strtoupper($data['codigo']),
            'nombre'      => $data['nombre'],
            'activo'      => true,
            'created_by'  => $request->user()->email,
        ]);

        return back()->with('status', "Almacén {$data['codigo']} creado.");
    }

    public function update(Request $request, InvAlmacen $almacen): RedirectResponse
    {
        abort_unless($request->user()->can('inventario.gestionar'), 403);
        abort_unless($almacen->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate(['nombre' => ['required', 'string', 'max:150']]);

        $almacen->update(['nombre' => $data['nombre'], 'updated_by' => $request->user()->email]);

        return back()->with('status', "Almacén {$almacen->codigo} actualizado.");
    }

    public function toggle(Request $request, InvAlmacen $almacen): RedirectResponse
    {
        abort_unless($request->user()->can('inventario.gestionar'), 403);
        abort_unless($almacen->compania_id === $this->companiaActivaId($request), 404);

        $almacen->update(['activo' => ! $almacen->activo, 'updated_by' => $request->user()->email]);

        return back()->with('status', "Almacén {$almacen->codigo} ".($almacen->activo ? 'activado' : 'desactivado').'.');
    }

    public function existencias(Request $request, InvAlmacen $almacen): View
    {
        abort_unless($almacen->compania_id === $this->companiaActivaId($request), 404);

        $existencias = InvExistencia::with('item')
            ->where('almacen_id', $almacen->id)
            ->where('cantidad', '>', 0)
            ->get()
            ->sortBy('item.codigo');

        return view('admin.inventario.almacenes.existencias', [
            'almacen'    => $almacen,
            'existencias' => $existencias,
        ]);
    }
}
