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
            // Módulos preparados para crecimiento
            'contabilidad.ver',
            'compras.ver',
            'compras.gestionar',
            'ventas.ver',
            'ventas.gestionar',
            'bancos.ver',
            'bancos.gestionar',
            'inventario.ver',
            'activos.ver',
            'reportes.ver',
            'ia.ver',
            // Visibilidad de campos sensibles
            'companias.campo.facturacion_fiscal',
        ];

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        // Roles globales (team_id null): se asignan a usuarios POR compañía.
        $adminCompania = Role::findOrCreate('admin_compania', 'web');
        $adminCompania->syncPermissions($permisos);

        $usuario = Role::findOrCreate('usuario', 'web');
        $usuario->syncPermissions([
            'companias.ver',
            'zonas.ver',
            'contabilidad.ver',
            'compras.ver',
            'ventas.ver',
            'bancos.ver',
            'inventario.ver',
            'activos.ver',
            'reportes.ver',
            'ia.ver',
        ]);
    }
}
