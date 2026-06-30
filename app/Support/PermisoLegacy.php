<?php

namespace App\Support;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Cache;

/**
 * Compatibilidad del modelo de permisos VIEJO (por módulo: modulo.ver,
 * modulo.gestionar, ...) sobre el modelo NUEVO (por opción × acción).
 *
 * El modelo nuevo es la ÚNICA fuente de verdad. Este shim permite que los
 * nombres viejos que siguen apareciendo en el código (middleware `permission:`
 * en web.php, `@can` en vistas, checks en controladores) se evalúen contra el
 * modelo nuevo, sin reescribir cientos de llamadas ni dejar rutas sin proteger.
 *
 * Se usa desde el Gate::before (AppServiceProvider). Los permisos RESERVADOS de
 * plataforma NO se traducen (se resuelven normalmente: solo super_admin).
 */
class PermisoLegacy
{
    /** Vocabulario de acciones del modelo viejo (disjunto del nuevo). */
    public const ACCIONES_VIEJAS = ['ver', 'gestionar', 'crear', 'editar', 'eliminar', 'registrar_qr'];

    /** Permisos viejos reservados de plataforma: no se traducen. */
    public const RESERVADOS = ['companias.eliminar', 'zonas.crear', 'zonas.editar', 'zonas.eliminar'];

    /** Casos especiales viejo → nuevo (cuando el mapa por módulo no aplica). */
    public const ALIAS = ['cxp.registrar_qr' => ['compras.qr_cufe.insertar']];

    /** Acción vieja → acciones nuevas equivalentes. */
    private const MAP_ACCION = [
        'ver' => ['acceder'],
        'gestionar' => ['insertar', 'actualizar', 'borrar'],
        'crear' => ['insertar'],
        'editar' => ['actualizar'],
        'eliminar' => ['borrar'],
    ];

    /** ¿El ability es un permiso del modelo viejo traducible? */
    public static function esLegacy(string $ability): bool
    {
        if (in_array($ability, self::RESERVADOS, true)) {
            return false;
        }
        $partes = explode('.', $ability);
        $ultimo = end($partes) ?: '';

        return in_array($ultimo, self::ACCIONES_VIEJAS, true);
    }

    /**
     * Permisos NUEVOS (clave.accion) cuya posesión satisface el permiso viejo
     * (semántica OR). Vacío si no hay traducción.
     *
     * @return array<int,string>
     */
    public static function candidatos(string $ability): array
    {
        if (isset(self::ALIAS[$ability])) {
            return self::ALIAS[$ability];
        }

        $partes = explode('.', $ability);
        $accion = array_pop($partes);
        $modulo = $partes[0] ?? '';

        $accionesNuevas = self::MAP_ACCION[$accion] ?? [];
        if (! $accionesNuevas || $modulo === '') {
            return [];
        }

        $salida = [];
        foreach (self::opcionesDeModulo($modulo) as $clave) {
            foreach ($accionesNuevas as $a) {
                $salida[] = "$clave.$a";
            }
        }

        return $salida;
    }

    /** Mapa módulo (viejo) → claves de opción del menú, cacheado. */
    private static function opcionesDeModulo(string $modulo): array
    {
        $mapa = Cache::remember(MenuItem::CACHE_KEY.':modulo_opciones', 3600, function () {
            $mapa = [];
            $items = MenuItem::query()
                ->where('activo', true)
                ->whereNotNull('ruta_nombre')
                ->get(['clave', 'permiso', 'solo_admin']);
            foreach ($items as $it) {
                if ($it->solo_admin) {
                    continue;
                }
                $tok = $it->permiso ? explode('.', explode('|', $it->permiso)[0])[0] : explode('.', $it->clave)[0];
                $mapa[$tok][] = $it->clave;
            }

            return $mapa;
        });

        return $mapa[$modulo] ?? [];
    }
}
