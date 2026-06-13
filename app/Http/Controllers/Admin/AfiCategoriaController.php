<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\AfiCategoria;
use App\Models\CuentaContable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AfiCategoriaController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('activos.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $categorias = AfiCategoria::where('compania_id', $companiaId)
            ->with(['cuentaActivo', 'cuentaDepreciacionAcum', 'cuentaGastoDepreciacion'])
            ->orderBy('codigo')
            ->get();

        $cuentas = CuentaContable::where('compania_id', $companiaId)
            ->where('acepta_movimientos', true)
            ->orderBy('codigo')
            ->get();

        return view('admin.activos.categorias.index', compact('categorias', 'cuentas'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('activos.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'codigo'                       => ['required', 'string', 'max:30'],
            'nombre'                       => ['required', 'string', 'max:150'],
            'vida_util_meses_default'      => ['nullable', 'integer', 'min:1', 'max:600'],
            'cuenta_activo_id'             => ['nullable', 'integer'],
            'cuenta_depreciacion_acum_id'  => ['nullable', 'integer'],
            'cuenta_gasto_depreciacion_id' => ['nullable', 'integer'],
        ]);

        $codigo = strtoupper(trim($data['codigo']));

        $exists = AfiCategoria::where('compania_id', $companiaId)
            ->where('codigo', $codigo)
            ->exists();

        if ($exists) {
            return back()->withErrors(['codigo' => 'Ya existe una categoría con ese código.'])->withInput();
        }

        AfiCategoria::create(array_merge($data, [
            'compania_id' => $companiaId,
            'codigo'      => $codigo,
            'created_by'  => $request->user()->email,
        ]));

        return back()->with('status', 'Categoría creada.');
    }

    public function update(Request $request, AfiCategoria $categoria): RedirectResponse
    {
        abort_unless($request->user()->can('activos.gestionar'), 403);
        abort_unless($categoria->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'nombre'                       => ['required', 'string', 'max:150'],
            'vida_util_meses_default'      => ['nullable', 'integer', 'min:1', 'max:600'],
            'cuenta_activo_id'             => ['nullable', 'integer'],
            'cuenta_depreciacion_acum_id'  => ['nullable', 'integer'],
            'cuenta_gasto_depreciacion_id' => ['nullable', 'integer'],
        ]);

        $categoria->update(array_merge($data, ['updated_by' => $request->user()->email]));

        return back()->with('status', 'Categoría actualizada.');
    }

    public function destroy(Request $request, AfiCategoria $categoria): RedirectResponse
    {
        abort_unless($request->user()->can('activos.gestionar'), 403);
        abort_unless($categoria->compania_id === $this->companiaActivaId($request), 404);

        if ($categoria->activos()->exists()) {
            return back()->withErrors(['categoria' => 'No se puede eliminar: tiene activos asociados.']);
        }

        $categoria->delete();

        return back()->with('status', 'Categoría eliminada.');
    }
}
