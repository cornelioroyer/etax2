<?php

namespace App\Support;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Resuelve, para una ruta del panel admin, la OPCIÓN (clave de core_menu_items)
 * y la ACCIÓN estándar (acceder/insertar/borrar/actualizar/exportar/imprimir)
 * que le corresponden en el modelo de permisos "por opción".
 *
 * La opción se deduce del patrón de ruta activa que cada ítem del menú ya
 * declara (ruta_activa_patron, p.ej. "admin.cxp.facturas.*"). Cuando varias
 * opciones casan (una más específica anidada en otra, p.ej. desde-cufe dentro
 * de facturas), gana la de patrón más específico.
 *
 * La acción se deduce del sufijo del nombre de ruta (index/store/destroy/...)
 * con respaldo en el verbo HTTP. Hay alias para acciones de negocio (anular,
 * contabilizar, postear → actualizar; plantilla/xlsx → exportar; etc.).
 *
 * Si la ruta no pertenece a ninguna opción (helpers AJAX, adjuntos, etc.) se
 * devuelve null y el middleware NO aplica restricción (fail-open): esas rutas
 * conservan su autorización propia en el controlador.
 */
class OpcionResolver
{
    /** Sufijo de nombre de ruta → acción estándar. */
    private const SUFIJO_ACCION = [
        'index' => 'acceder',
        'show' => 'acceder',
        'estado' => 'acceder',
        'progreso' => 'acceder',
        'datos' => 'acceder',
        'buscar' => 'acceder',
        'consultar-cufe' => 'acceder',

        'create' => 'insertar',
        'store' => 'insertar',
        'importar' => 'insertar',
        'importar-generico' => 'insertar',
        'importar-proveedores' => 'insertar',
        'importar-saldos' => 'insertar',
        'cufe-desde-foto' => 'insertar',
        'desde-cufe' => 'insertar',

        'edit' => 'actualizar',
        'update' => 'actualizar',
        'anular' => 'actualizar',
        'corregir' => 'actualizar',
        'contabilizar' => 'actualizar',
        'postear' => 'actualizar',
        'toggle' => 'actualizar',
        'pausar' => 'actualizar',
        'reactivar' => 'actualizar',
        'generar' => 'actualizar',
        'generar-todos' => 'actualizar',
        'generar-todos.' => 'actualizar',
        'reabrir' => 'actualizar',
        'cerrar' => 'actualizar',
        'reversar' => 'actualizar',
        'mover' => 'actualizar',
        'aplicar-plantilla' => 'actualizar',

        'destroy' => 'borrar',

        'imprimir' => 'imprimir',
        'pdf' => 'imprimir',

        'plantilla' => 'exportar',
        'plantilla-xlsx' => 'exportar',
        'plantilla-proveedores' => 'exportar',
        'plantilla-proveedores-xlsx' => 'exportar',
        'export' => 'exportar',
        'exportar' => 'exportar',
        'xlsx' => 'exportar',
        'excel' => 'exportar',
        'descargar' => 'exportar',
        'download' => 'exportar',
        'archivo' => 'exportar',
    ];

    /** Verbo HTTP → acción de respaldo cuando el sufijo no está mapeado. */
    private const METODO_ACCION = [
        'GET' => 'acceder',
        'HEAD' => 'acceder',
        'POST' => 'insertar',
        'PUT' => 'actualizar',
        'PATCH' => 'actualizar',
        'DELETE' => 'borrar',
    ];

    /**
     * @return array{clave:string,accion:string}|null
     */
    public static function resolver(?string $rutaNombre, string $metodo): ?array
    {
        if (! $rutaNombre || ! str_starts_with($rutaNombre, 'admin.')) {
            return null;
        }

        $clave = self::opcionDeRuta($rutaNombre);
        if (! $clave) {
            return null;
        }

        return ['clave' => $clave, 'accion' => self::accionDeRuta($rutaNombre, $metodo)];
    }

    /** Opción (clave) cuyo patrón de ruta activa casa de forma más específica. */
    private static function opcionDeRuta(string $rutaNombre): ?string
    {
        $opciones = Cache::remember(MenuItem::CACHE_KEY.':opciones', 3600, function () {
            return MenuItem::query()
                ->where('activo', true)
                ->whereNotNull('ruta_activa_patron')
                ->get(['clave', 'ruta_activa_patron'])
                ->map(fn ($i) => [
                    'clave' => $i->clave,
                    'patrones' => preg_split('/\s+/', trim($i->ruta_activa_patron)) ?: [],
                ])
                ->all();
        });

        $mejor = null;
        $mejorPuntaje = -1;

        foreach ($opciones as $op) {
            foreach ($op['patrones'] as $patron) {
                if (Str::is($patron, $rutaNombre)) {
                    // Especificidad = nº de caracteres literales del patrón (sin
                    // los comodines '*'); el patrón más largo/específico gana,
                    // p.ej. "...facturas.desde-cufe*" vence a "...facturas.*".
                    $puntaje = strlen(str_replace('*', '', $patron));
                    if ($puntaje > $mejorPuntaje) {
                        $mejorPuntaje = $puntaje;
                        $mejor = $op['clave'];
                    }
                }
            }
        }

        return $mejor;
    }

    /** Acción estándar a partir del sufijo del nombre de ruta o el verbo HTTP. */
    private static function accionDeRuta(string $rutaNombre, string $metodo): string
    {
        $sufijo = self::sufijo($rutaNombre);

        if (isset(self::SUFIJO_ACCION[$sufijo])) {
            return self::SUFIJO_ACCION[$sufijo];
        }

        return self::METODO_ACCION[strtoupper($metodo)] ?? 'acceder';
    }

    /**
     * Último segmento significativo del nombre de ruta. Ignora ".form" final
     * (p.ej. "desde-cufe.form" → "desde-cufe") para que el GET del formulario
     * herede la acción de la operación.
     */
    private static function sufijo(string $rutaNombre): string
    {
        $partes = explode('.', $rutaNombre);
        $ultimo = end($partes) ?: '';

        if ($ultimo === 'form' && count($partes) >= 2) {
            $ultimo = $partes[count($partes) - 2];
        }

        return $ultimo;
    }
}
