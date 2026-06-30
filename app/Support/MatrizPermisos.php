<?php

namespace App\Support;

use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

/**
 * Construye la matriz de permisos "por opción × acción" para las pantallas de
 * Roles y de Permisos de usuario: opciones (filas) agrupadas por su grupo de
 * menú raíz, cada una con las 6 acciones estándar y el id/nombre de su permiso.
 *
 * Excluye las opciones solo_admin (exclusivas del super_admin) y las acciones
 * RESERVADAS de plataforma (equivalentes nuevas de Role::PERMISOS_RESERVADOS),
 * que no debe poder otorgar un admin de compañía.
 */
class MatrizPermisos
{
    /** accion => etiqueta visible (orden de columnas). */
    public const ACCIONES = [
        'acceder' => 'Acceder',
        'insertar' => 'Insertar',
        'borrar' => 'Borrar',
        'actualizar' => 'Actualizar',
        'exportar' => 'Exportar',
        'imprimir' => 'Imprimir',
    ];

    /** Permisos nuevos reservados de plataforma (no se muestran ni se asignan). */
    public const RESERVADOS = [
        'configuracion.zonas.insertar',
        'configuracion.zonas.actualizar',
        'configuracion.zonas.borrar',
        'companias.directorio.borrar',
    ];

    /**
     * @return array<int,array{titulo:string,opciones:array<int,array{clave:string,etiqueta:string,acciones:array<string,array{name:string,id:?int,reservado:bool}>}>}>
     */
    public static function grupos(): array
    {
        $items = MenuItem::query()->where('activo', true)->orderBy('orden')->get();
        $porId = $items->keyBy('id');
        $idPermiso = DB::table('seg_permisos')->pluck('id', 'name');

        $raiz = function ($it) use ($porId) {
            $cur = $it;
            while ($cur->parent_id && $porId->has($cur->parent_id)) {
                $cur = $porId[$cur->parent_id];
            }

            return $cur;
        };

        $grupos = [];
        foreach ($items as $it) {
            if (! $it->ruta_nombre || $it->solo_admin) {
                continue;
            }
            $r = $raiz($it);

            $acciones = [];
            foreach (array_keys(self::ACCIONES) as $a) {
                $name = $it->clave.'.'.$a;
                $acciones[$a] = [
                    'name' => $name,
                    'id' => $idPermiso[$name] ?? null,
                    'reservado' => in_array($name, self::RESERVADOS, true),
                ];
            }

            $grupos[$r->id]['titulo'] = $r->etiqueta;
            $grupos[$r->id]['opciones'][] = [
                'clave' => $it->clave,
                'etiqueta' => $it->etiqueta,
                'acciones' => $acciones,
            ];
        }

        // Secciones y, dentro de cada una, sus opciones ordenadas alfabéticamente
        // (plegando acentos/ñ para un orden correcto en español sin depender de la
        // extensión intl).
        foreach ($grupos as &$grupo) {
            usort($grupo['opciones'], fn ($a, $b) => strcmp(self::claveOrden($a['etiqueta']), self::claveOrden($b['etiqueta'])));
        }
        unset($grupo);

        uasort($grupos, fn ($a, $b) => strcmp(self::claveOrden($a['titulo']), self::claveOrden($b['titulo'])));

        return array_values($grupos);
    }

    /** Normaliza un título para ordenar: minúsculas y acentos/ñ plegados. */
    private static function claveOrden(string $titulo): string
    {
        return strtr(mb_strtolower($titulo, 'UTF-8'), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'nz',
        ]);
    }
}
