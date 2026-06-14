<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            // Módulo compañías
            'companias.ver',
            'companias.crear',
            'companias.editar',
            'companias.eliminar',
            // Módulo zonas
            'zonas.ver',
            'zonas.crear',
            'zonas.editar',
            'zonas.eliminar',
            // Gestión de usuarios de la compañía (nivel admin_compania)
            'usuarios_compania.gestionar',
            // Módulos operativos
            'contabilidad.ver',
            'contabilidad.gestionar',
            'contabilidad.crear',
            'contabilidad.editar',
            'contabilidad.eliminar',
            'compras.ver',
            'compras.gestionar',
            'ventas.ver',
            'ventas.gestionar',
            'bancos.ver',
            'bancos.gestionar',
            'caja.ver',
            'caja.gestionar',
            'inventario.ver',
            'inventario.gestionar',
            'activos.ver',
            'activos.gestionar',
            'taller.ver',
            'taller.gestionar',
            'ph.ver',
            'ph.gestionar',
            'edu.ver',
            'edu.gestionar',
            'dimensiones.ver',
            'dimensiones.gestionar',
            'reportes.ver',
            'ia.ver',
            'contactos.ver',
            'contactos.gestionar',
            'contactos.crear',
            'contactos.editar',
            'contactos.eliminar',
            'cxc.ver',
            'cxc.gestionar',
            'cxp.ver',
            'cxp.gestionar',
            'fel.ver',
            'fel.gestionar',
            'usuarios_compania.ver',
            // Visibilidad de campos sensibles
            'companias.campo.facturacion_fiscal',
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        // Limpiar caché para que syncPermissions lea los permisos recién creados desde la BD
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Roles globales (team_id null): se asignan a usuarios POR compañía.
        // Admin de compañía: administra SUS compañías (ver/editar/crear) y los
        // usuarios de ellas, con todo lo operativo. NO tiene permisos de nivel
        // sistema (eso es exclusivo del super_admin / is_admin): no puede borrar
        // compañías ni gestionar el catálogo de zonas.
        $permisosSoloSistema = [
            'companias.eliminar',
            'zonas.crear',
            'zonas.editar',
            'zonas.eliminar',
        ];
        $adminCompania = Role::findOrCreate('admin_compania', 'web');
        $adminCompania->syncPermissions(array_values(array_diff($permisos, $permisosSoloSistema)));

        $usuario = Role::findOrCreate('usuario', 'web');
        $usuario->syncPermissions([
            'companias.ver',
            'zonas.ver',
            'contabilidad.ver',
            'compras.ver',
            'ventas.ver',
            'bancos.ver',
            'caja.ver',
            'inventario.ver',
            'activos.ver',
            'taller.ver',
            'ph.ver',
            'edu.ver',
            'dimensiones.ver',
            'reportes.ver',
            'ia.ver',
            'contactos.ver',
            'cxc.ver',
            'cxp.ver',
            'fel.ver',
        ]);
    }
}
