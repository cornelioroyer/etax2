<?php

namespace App\Services;

use App\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * Arma el árbol del menú lateral a partir de core_menu_items, ya filtrado por
 * los permisos del usuario y la compañía activa, con la opción activa resuelta.
 *
 * Centraliza la lógica fuera del Blade. Las filas crudas (activas) se cachean
 * globalmente; el filtrado por permiso se hace por petición en memoria, así no
 * hay caché por-usuario que invalidar cuando cambian roles.
 *
 * Devuelve un árbol normalizado de arrays:
 *   ['label','icon','href','dispatch','active','children'=>[...]]
 * Si la tabla está vacía devuelve [] y el Blade cae al menú estático heredado.
 */
class MenuBuilder
{
    public function build(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $filas = $this->filas();

        if ($filas->isEmpty()) {
            return [];
        }

        $porPadre = $filas->groupBy(fn ($r) => (int) ($r->parent_id ?? 0));

        return $this->construirNivel($porPadre, 0, $user);
    }

    /** Filas activas, cacheadas (invalidadas por el modelo al guardar/borrar). */
    private function filas(): Collection
    {
        return Cache::remember(
            MenuItem::CACHE_KEY,
            3600,
            fn () => MenuItem::query()->where('activo', true)->orderBy('orden')->get()
        );
    }

    private function construirNivel(Collection $porPadre, int $padreId, $user): array
    {
        $items = [];

        foreach ($porPadre->get($padreId, collect()) as $fila) {
            if (! $this->visiblePara($fila, $user)) {
                continue;
            }

            $hijos = $this->construirNivel($porPadre, (int) $fila->id, $user);
            $esGrupo = $porPadre->has((int) $fila->id);

            // Podar: un grupo (tiene hijos en BD) sin hijos visibles y sin
            // destino propio no se muestra.
            if ($esGrupo && $hijos === [] && ! $fila->ruta_nombre && ! $fila->dispatch_evento) {
                continue;
            }

            $activo = $this->esActivo($fila)
                || collect($hijos)->contains('active', true);

            $items[] = [
                'label' => $fila->etiqueta,
                'icon' => $fila->icono,
                'href' => $this->resolverHref($fila),
                'dispatch' => $fila->dispatch_evento,
                'active' => $activo,
                'children' => $hijos,
            ];
        }

        return $items;
    }

    /** Reglas equivalentes al closure $can del Blade heredado. */
    private function visiblePara(MenuItem $fila, $user): bool
    {
        if ($fila->solo_admin) {
            return (bool) $user->is_admin;
        }

        if ($fila->permiso) {
            return $user->is_admin || $user->can($fila->permiso);
        }

        return true;
    }

    private function resolverHref(MenuItem $fila): ?string
    {
        if (! $fila->ruta_nombre || ! Route::has($fila->ruta_nombre)) {
            return null;
        }

        try {
            return route($fila->ruta_nombre, $fila->ruta_params ?? []);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function esActivo(MenuItem $fila): bool
    {
        if (! $fila->ruta_activa_patron) {
            return false;
        }

        $patrones = preg_split('/\s+/', trim($fila->ruta_activa_patron)) ?: [];

        if (! request()->routeIs(...$patrones)) {
            return false;
        }

        if ($fila->activa_query_key) {
            return request($fila->activa_query_key) === $fila->activa_query_val;
        }

        return true;
    }
}
