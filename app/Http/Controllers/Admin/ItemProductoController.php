<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\CuentaContable;
use App\Models\ItemCategoria;
use App\Models\ItemProducto;
use App\Models\ItemUnidadMedida;
use App\Models\TaxImpuesto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemProductoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'tipo'         => ['nullable', Rule::in(['PRODUCTO', 'SERVICIO'])],
            'categoria_id' => ['nullable', 'integer'],
            'activo'       => ['nullable', Rule::in(['1', '0'])],
            'q'            => ['nullable', 'string', 'max:100'],
        ]);

        $items = ItemProducto::with(['categoria', 'unidadMedida', 'impuesto'])
            ->where('compania_id', $companiaId)
            ->when($filtros['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when($filtros['categoria_id'] ?? null, fn ($q, $v) => $q->where('categoria_id', $v))
            ->when(isset($filtros['activo']) && $filtros['activo'] !== null, fn ($q) => $q->where('activo', (bool) $filtros['activo']))
            ->when($filtros['q'] ?? null, function ($q, $texto) {
                $b = '%'.mb_strtolower($texto).'%';
                $q->where(fn ($q) => $q
                    ->whereRaw('LOWER(codigo) LIKE ?', [$b])
                    ->orWhereRaw('LOWER(nombre) LIKE ?', [$b])
                );
            })
            ->orderBy('codigo')
            ->paginate(30)
            ->withQueryString();

        return view('admin.items.index', [
            'items'      => $items,
            'filtros'    => $filtros,
            'categorias' => ItemCategoria::where('compania_id', $companiaId)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        return view('admin.items.create', $this->formData($companiaId));
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);

        $data = $this->validar($request, $companiaId);

        $item = ItemProducto::create($data + [
            'compania_id' => $companiaId,
            'activo'      => true,
            'created_by'  => $request->user()->email,
        ]);

        return redirect()->route('admin.items.index')
            ->with('status', "Producto/servicio {$item->codigo} — {$item->nombre} creado.");
    }

    public function edit(Request $request, ItemProducto $item): View
    {
        abort_unless($item->compania_id === $this->companiaActivaId($request), 404);

        return view('admin.items.edit', ['item' => $item] + $this->formData($item->compania_id));
    }

    public function update(Request $request, ItemProducto $item): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        abort_unless($item->compania_id === $companiaId, 404);

        $data = $this->validar($request, $companiaId, $item->id);

        $item->update($data + ['updated_by' => $request->user()->email]);

        return redirect()->route('admin.items.index')
            ->with('status', "Producto/servicio {$item->codigo} — {$item->nombre} actualizado.");
    }

    public function toggle(Request $request, ItemProducto $item): RedirectResponse
    {
        abort_unless($item->compania_id === $this->companiaActivaId($request), 404);

        $item->update(['activo' => ! $item->activo, 'updated_by' => $request->user()->email]);

        return back()->with('status', "{$item->nombre} ".($item->activo ? 'activado' : 'desactivado').'.');
    }

    private function validar(Request $request, int $companiaId, ?int $excludeId = null): array
    {
        return $request->validate([
            'codigo'             => ['required', 'string', 'max:50',
                Rule::unique('item_productos_servicios')->where('compania_id', $companiaId)->ignore($excludeId)],
            'nombre'             => ['required', 'string', 'max:200'],
            'descripcion'        => ['nullable', 'string', 'max:2000'],
            'tipo'               => ['required', Rule::in(['PRODUCTO', 'SERVICIO'])],
            'categoria_id'       => ['nullable', 'integer', Rule::exists('item_categorias', 'id')->where('compania_id', $companiaId)],
            'unidad_medida_id'   => ['nullable', 'integer', Rule::exists('item_unidades_medida', 'id')],
            'precio_venta'       => ['nullable', 'numeric', 'min:0'],
            'costo'              => ['nullable', 'numeric', 'min:0'],
            'cuenta_ingreso_id'  => ['nullable', 'integer', Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId)],
            'cuenta_gasto_id'    => ['nullable', 'integer', Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId)],
            'impuesto_id'        => ['nullable', 'integer'],
        ]);
    }

    private function formData(int $companiaId): array
    {
        return [
            'categorias'   => ItemCategoria::where('compania_id', $companiaId)->orderBy('nombre')->get(['id', 'nombre']),
            'unidades'     => ItemUnidadMedida::orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'impuestos'    => TaxImpuesto::itbmsGlobales()->orderBy('porcentaje')->get(),
            'cuentas'      => CuentaContable::where('compania_id', $companiaId)->where('activa', true)->where('permite_movimiento', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
        ];
    }
}
