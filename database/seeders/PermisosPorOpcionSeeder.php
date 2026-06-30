<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Modelo de permisos POR OPCIÓN (estilo Scriptcase).
 *
 * Cada opción del menú (core_menu_items con ruta_nombre) expone SIEMPRE las 6
 * acciones estándar: acceder, insertar, borrar, actualizar, exportar, imprimir.
 * El permiso se nombra "{clave_opcion}.{accion}".
 *
 * FASE 1 (aditiva e idempotente): crea el catálogo nuevo y traduce al nuevo
 * modelo TODAS las concesiones del modelo viejo, sin tocar los permisos viejos:
 *   - permisos de los roles globales (admin_compania, usuario),
 *   - permisos directos de usuario por compañía (seg_usuarios_permisos),
 *   - permisos denegados por usuario por compañía (seg_usuarios_permisos_denegados).
 *
 * Mapa: acceder/exportar/imprimir = el holder satisface la visibilidad del ítem;
 * insertar = modulo.gestionar|crear; actualizar = modulo.gestionar|editar;
 * borrar = modulo.gestionar|eliminar. Casos semánticos puntuales en ALIAS_ESPECIAL.
 */
class PermisosPorOpcionSeeder extends Seeder
{
    /** Las 6 acciones estándar, en todas las opciones. */
    public const ACCIONES = ['acceder', 'insertar', 'borrar', 'actualizar', 'exportar', 'imprimir'];

    /**
     * Casos especiales: permiso VIEJO → permisos NUEVOS exactos (clave.accion).
     * Para semánticas que el mapa por módulo no captura. registrar_qr es
     * "insertar" sobre la opción compras.qr_cufe (registrar factura por QR).
     */
    public const ALIAS_ESPECIAL = [
        'cxp.registrar_qr' => ['compras.qr_cufe.insertar'],
    ];

    /** @var array<int,object> opciones (clave, modulo, visTokens) */
    private array $opciones = [];

    /** @var array<string,int> name => id de permisos nuevos */
    private array $idPermiso = [];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $items = DB::table('core_menu_items')
            ->whereNotNull('ruta_nombre')
            ->orderBy('orden')
            ->get(['clave', 'permiso', 'solo_admin']);

        // 1) Crear el catálogo: {clave}.{accion} para cada opción y acción.
        foreach ($items as $it) {
            foreach (self::ACCIONES as $accion) {
                Permission::findOrCreate($it->clave.'.'.$accion, 'web');
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->idPermiso = DB::table('seg_permisos')->pluck('id', 'name')->all();

        // Opciones asignables por rol/usuario (las solo_admin son del super_admin).
        foreach ($items as $it) {
            if ($it->solo_admin) {
                continue;
            }
            $visTokens = $it->permiso ? array_map('trim', explode('|', $it->permiso)) : [];
            $this->opciones[] = (object) [
                'clave' => $it->clave,
                'modulo' => $visTokens ? explode('.', $visTokens[0])[0] : explode('.', $it->clave)[0],
                'vis' => $visTokens,
            ];
        }

        $this->limpiarReservados();
        $this->migrarRoles();
        $this->migrarPermisosDirectos();
        $this->migrarDenegados();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Quita de roles y usuarios cualquier permiso nuevo RESERVADO de plataforma
     * que una corrida previa hubiera otorgado (corrige la escalada en que
     * admin_compania recibía las 6 acciones sobre Zonas/eliminar compañía).
     */
    private function limpiarReservados(): void
    {
        $ids = collect(\App\Support\MatrizPermisos::RESERVADOS)
            ->map(fn ($n) => $this->idPermiso[$n] ?? null)
            ->filter()->values()->all();

        if (! $ids) {
            return;
        }

        DB::table('seg_roles_permisos')->whereIn('permiso_id', $ids)->delete();
        DB::table('seg_usuarios_permisos')->whereIn('permiso_id', $ids)->delete();
    }

    /** Roles globales: traduce sus permisos viejos al nuevo modelo. */
    private function migrarRoles(): void
    {
        $roles = DB::table('seg_roles')->whereIn('name', ['admin_compania', 'usuario'])->pluck('id', 'name');

        foreach ($roles as $name => $rolId) {
            $viejos = DB::table('seg_roles_permisos')
                ->join('seg_permisos', 'seg_permisos.id', '=', 'seg_roles_permisos.permiso_id')
                ->where('seg_roles_permisos.rol_id', $rolId)
                ->pluck('seg_permisos.name')->all();

            foreach ($this->permisosConcedidos($viejos) as $permName) {
                $this->insertarSiFalta('seg_roles_permisos', [
                    'rol_id' => $rolId,
                    'permiso_id' => $this->idPermiso[$permName],
                ]);
            }
        }
    }

    /** Permisos directos de usuario (extras sobre el rol), por compañía. */
    private function migrarPermisosDirectos(): void
    {
        $grupos = DB::table('seg_usuarios_permisos')
            ->join('seg_permisos', 'seg_permisos.id', '=', 'seg_usuarios_permisos.permiso_id')
            ->get(['seg_usuarios_permisos.model_type', 'seg_usuarios_permisos.model_id',
                'seg_usuarios_permisos.compania_id', 'seg_permisos.name'])
            ->groupBy(fn ($r) => $r->model_type.'|'.$r->model_id.'|'.$r->compania_id);

        foreach ($grupos as $filas) {
            $f = $filas->first();
            $viejos = $filas->pluck('name')->all();
            foreach ($this->permisosConcedidos($viejos) as $permName) {
                $this->insertarSiFalta('seg_usuarios_permisos', [
                    'permiso_id' => $this->idPermiso[$permName],
                    'model_type' => $f->model_type,
                    'model_id' => $f->model_id,
                    'compania_id' => $f->compania_id,
                ]);
            }
        }
    }

    /** Permisos denegados por usuario, por compañía (override negativo). */
    private function migrarDenegados(): void
    {
        $grupos = DB::table('seg_usuarios_permisos_denegados')
            ->join('seg_permisos', 'seg_permisos.id', '=', 'seg_usuarios_permisos_denegados.permiso_id')
            ->get(['seg_usuarios_permisos_denegados.model_type', 'seg_usuarios_permisos_denegados.model_id',
                'seg_usuarios_permisos_denegados.compania_id', 'seg_permisos.name'])
            ->groupBy(fn ($r) => $r->model_type.'|'.$r->model_id.'|'.$r->compania_id);

        foreach ($grupos as $filas) {
            $f = $filas->first();
            foreach ($filas->pluck('name') as $viejo) {
                foreach ($this->permisosDenegados($viejo) as $permName) {
                    $this->insertarSiFalta('seg_usuarios_permisos_denegados', [
                        'permiso_id' => $this->idPermiso[$permName],
                        'model_type' => $f->model_type,
                        'model_id' => $f->model_id,
                        'compania_id' => $f->compania_id,
                    ]);
                }
            }
        }
    }

    /**
     * Traduce el conjunto de permisos viejos de un holder (rol o usuario) al
     * conjunto de permisos nuevos que se le conceden.
     *
     * @param  array<int,string>  $viejos
     * @return array<int,string>  nombres de permisos nuevos existentes en el catálogo
     */
    private function permisosConcedidos(array $viejos): array
    {
        $tiene = fn (string $p) => in_array($p, $viejos, true);
        $nuevos = [];

        foreach ($this->opciones as $op) {
            $puedeAcceder = empty($op->vis) || collect($op->vis)->contains(fn ($t) => $tiene($t));
            if ($puedeAcceder) {
                $nuevos[] = "$op->clave.acceder";
                $nuevos[] = "$op->clave.exportar";
                $nuevos[] = "$op->clave.imprimir";
            }
            if ($tiene("$op->modulo.gestionar") || $tiene("$op->modulo.crear")) {
                $nuevos[] = "$op->clave.insertar";
            }
            if ($tiene("$op->modulo.gestionar") || $tiene("$op->modulo.editar")) {
                $nuevos[] = "$op->clave.actualizar";
            }
            if ($tiene("$op->modulo.gestionar") || $tiene("$op->modulo.eliminar")) {
                $nuevos[] = "$op->clave.borrar";
            }
        }

        foreach (self::ALIAS_ESPECIAL as $viejo => $destinos) {
            if ($tiene($viejo)) {
                $nuevos = array_merge($nuevos, $destinos);
            }
        }

        // Excluir reservados de plataforma (solo super_admin) y quedarse con los
        // que existen en el catálogo.
        $reservados = \App\Support\MatrizPermisos::RESERVADOS;

        return array_values(array_unique(array_filter(
            $nuevos,
            fn ($n) => isset($this->idPermiso[$n]) && ! in_array($n, $reservados, true)
        )));
    }

    /**
     * Traduce UN permiso viejo denegado a los permisos nuevos a denegar. A
     * diferencia de conceder, denegar no usa la visibilidad por OR: deniega la
     * contribución directa de ese permiso (acceder para .ver; writes para los
     * demás), sobre las opciones de su módulo.
     *
     * @return array<int,string>
     */
    private function permisosDenegados(string $viejo): array
    {
        if (isset(self::ALIAS_ESPECIAL[$viejo])) {
            return array_values(array_filter(self::ALIAS_ESPECIAL[$viejo], fn ($n) => isset($this->idPermiso[$n])));
        }

        [$modulo, $accion] = array_pad(explode('.', $viejo, 2), 2, '');
        $mapa = [
            'ver' => ['acceder', 'exportar', 'imprimir'],
            'gestionar' => ['insertar', 'actualizar', 'borrar'],
            'crear' => ['insertar'],
            'editar' => ['actualizar'],
            'eliminar' => ['borrar'],
        ];
        $acciones = $mapa[$accion] ?? [];
        if (! $acciones) {
            return [];
        }

        $nuevos = [];
        foreach ($this->opciones as $op) {
            if ($op->modulo !== $modulo) {
                continue;
            }
            foreach ($acciones as $a) {
                $nuevos[] = "$op->clave.$a";
            }
        }

        return array_values(array_unique(array_filter($nuevos, fn ($n) => isset($this->idPermiso[$n]))));
    }

    /** Inserta una fila si no existe ya (idempotencia). */
    private function insertarSiFalta(string $tabla, array $cols): void
    {
        if (! DB::table($tabla)->where($cols)->exists()) {
            DB::table($tabla)->insert($cols);
        }
    }
}
