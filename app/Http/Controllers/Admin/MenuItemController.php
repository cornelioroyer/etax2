<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Administración del menú lateral (core_menu_items). Catálogo GLOBAL del
 * sistema: solo super_admin (middleware 'admin'). El render lo consume
 * App\Services\MenuBuilder; aquí se mantiene el árbol (alta, edición, orden,
 * activación y borrado con guardia de hijos).
 */
class MenuItemController extends Controller
{
    public function index(): View
    {
        $arbol = $this->aplanarTodo();

        return view('admin.menu-items.index', compact('arbol'));
    }

    public function create(): View
    {
        return view('admin.menu-items.create', [
            'item' => new MenuItem(),
            'padres' => $this->opcionesPadre(null),
            'rutas' => $this->nombresDeRuta(),
            'permisos' => $this->nombresDePermiso(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request, null);

        $data['orden'] = (int) MenuItem::where('parent_id', $data['parent_id'] ?? null)->max('orden') + 10;
        $data['created_by'] = $request->user()->id;

        MenuItem::create($data);

        return redirect()->route('admin.menu-items.index')->with('status', 'Opción de menú creada.');
    }

    public function edit(MenuItem $menuItem): View
    {
        return view('admin.menu-items.edit', [
            'item' => $menuItem,
            'padres' => $this->opcionesPadre($menuItem),
            'rutas' => $this->nombresDeRuta(),
            'permisos' => $this->nombresDePermiso(),
        ]);
    }

    public function update(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $data = $this->validar($request, $menuItem);

        // Guardia anti-ciclo: el padre no puede ser el propio ítem ni un descendiente.
        $prohibidos = array_merge([$menuItem->id], $this->idsDescendientes($menuItem->id));
        if ($data['parent_id'] !== null && in_array((int) $data['parent_id'], $prohibidos, true)) {
            return back()->withInput()->withErrors(['parent_id' => 'El padre no puede ser la propia opción ni uno de sus descendientes.']);
        }

        $data['updated_by'] = $request->user()->id;

        $menuItem->update($data);

        return redirect()->route('admin.menu-items.index')->with('status', 'Opción de menú actualizada.');
    }

    public function toggle(MenuItem $menuItem): RedirectResponse
    {
        $menuItem->update(['activo' => ! $menuItem->activo]);

        return back()->with('status', $menuItem->activo ? 'Opción activada.' : 'Opción inactivada.');
    }

    /** Reordena intercambiando el 'orden' con el hermano adyacente. */
    public function mover(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $dir = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        $hermanos = MenuItem::where('parent_id', $menuItem->parent_id)->orderBy('orden')->get();
        $idx = $hermanos->search(fn ($s) => $s->id === $menuItem->id);
        $vecino = $dir === 'up' ? $hermanos->get($idx - 1) : $hermanos->get($idx + 1);

        if ($vecino) {
            DB::transaction(function () use ($menuItem, $vecino) {
                $tmp = $menuItem->orden;
                $menuItem->update(['orden' => $vecino->orden]);
                $vecino->update(['orden' => $tmp]);
            });
        }

        return back();
    }

    public function destroy(MenuItem $menuItem): RedirectResponse
    {
        if ($menuItem->children()->exists()) {
            return back()->withErrors(['menu' => 'No se puede eliminar: la opción tiene subopciones. Muévalas o elimínelas primero.']);
        }

        $menuItem->delete();

        return redirect()->route('admin.menu-items.index')->with('status', 'Opción de menú eliminada.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Reglas de validación compartidas. $actual = ítem en edición (para
     * exceptuar su propia 'clave' del unique), o null al crear.
     */
    private function validar(Request $request, ?MenuItem $actual): array
    {
        $idActual = $actual?->id ?? 'NULL';

        $data = $request->validate([
            'etiqueta' => ['required', 'string', 'max:255'],
            'clave' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.-]+$/i', "unique:core_menu_items,clave,{$idActual},id"],
            'parent_id' => ['nullable', 'integer', 'exists:core_menu_items,id'],
            'icono' => ['nullable', 'string', 'max:4000'],
            'ruta_nombre' => ['nullable', 'string', 'max:255'],
            'ruta_params' => ['nullable', 'string', 'max:1000'],
            'ruta_activa_patron' => ['nullable', 'string', 'max:255'],
            'activa_query_key' => ['nullable', 'string', 'max:50'],
            'activa_query_val' => ['nullable', 'string', 'max:50'],
            'dispatch_evento' => ['nullable', 'string', 'max:100'],
            'permiso' => ['nullable', 'string', 'max:100'],
            'modulo' => ['nullable', 'string', 'max:50'],
            'solo_admin' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
        ]);

        // ruta_params: texto JSON → array (o null). Se valida que sea objeto JSON.
        $params = trim((string) ($data['ruta_params'] ?? ''));
        if ($params !== '') {
            $decoded = json_decode($params, true);
            if (! is_array($decoded)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'ruta_params' => 'Debe ser un objeto JSON válido, ej. {"tipo":"PROVEEDOR"}.',
                ]);
            }
            $data['ruta_params'] = $decoded;
        } else {
            $data['ruta_params'] = null;
        }

        $data['solo_admin'] = $request->boolean('solo_admin');
        $data['activo'] = $request->boolean('activo');
        // Normaliza parent_id: '' o null → null (no depender de ConvertEmptyStringsToNull).
        $data['parent_id'] = ($data['parent_id'] ?? '') === '' ? null : (int) $data['parent_id'];

        return $data;
    }

    /** Lista [id => etiqueta indentada] para el selector de padre, sin self+descendientes. */
    private function opcionesPadre(?MenuItem $excluir): Collection
    {
        $prohibidos = $excluir ? array_merge([$excluir->id], $this->idsDescendientes($excluir->id)) : [];

        $opciones = collect();
        foreach ($this->aplanarTodo() as $item) {
            if (in_array($item->id, $prohibidos, true)) {
                continue;
            }
            $opciones->put($item->id, str_repeat('— ', $item->_depth).$item->etiqueta);
        }

        return $opciones;
    }

    /** Árbol completo aplanado en pre-orden, con _depth, _isFirst, _isLast. */
    private function aplanarTodo(): Collection
    {
        $porPadre = MenuItem::orderBy('orden')->get()->groupBy(fn ($r) => (int) ($r->parent_id ?? 0));
        $out = collect();
        $this->aplanar($porPadre, 0, 0, $out);

        return $out;
    }

    private function aplanar(Collection $porPadre, int $padreId, int $depth, Collection $out): void
    {
        $hermanos = $porPadre->get($padreId, collect())->values();
        foreach ($hermanos as $i => $item) {
            $item->_depth = $depth;
            $item->_isFirst = $i === 0;
            $item->_isLast = $i === $hermanos->count() - 1;
            $out->push($item);
            $this->aplanar($porPadre, (int) $item->id, $depth + 1, $out);
        }
    }

    /** IDs de todos los descendientes de un ítem (para guardia anti-ciclo). */
    private function idsDescendientes(int $id): array
    {
        $hijos = MenuItem::where('parent_id', $id)->pluck('id')->all();
        $ids = $hijos;
        foreach ($hijos as $hijo) {
            $ids = array_merge($ids, $this->idsDescendientes((int) $hijo));
        }

        return $ids;
    }

    /** Nombres de rutas admin GET (datalist para ruta_nombre). */
    private function nombresDeRuta(): Collection
    {
        return collect(Route::getRoutes()->getRoutesByName())
            ->filter(fn ($r, $n) => ($n === 'dashboard' || str_starts_with($n, 'admin.'))
                && in_array('GET', $r->methods(), true))
            ->keys()
            ->sort()
            ->values();
    }

    /** Nombres de permisos existentes (datalist para permiso). */
    private function nombresDePermiso(): Collection
    {
        $tabla = config('permission.table_names.permissions', 'permissions');

        return DB::table($tabla)->orderBy('name')->pluck('name');
    }
}
