<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Rol de seguridad (tabla seg_roles) — extiende el de spatie/permission solo para
 * exponer la columna `descripcion` (etiqueta amigable del catálogo administrable).
 *
 * No se registra como modelo por defecto en config/permission.php a propósito: la
 * resolución de permisos del resto de la app sigue usando el modelo de Spatie. Este
 * modelo se usa únicamente donde administramos el catálogo (RoleController). Como
 * Eloquent hace SELECT *, el atributo `descripcion` igual queda disponible en las
 * instancias del modelo de Spatie para solo-lectura.
 */
class Role extends SpatieRole
{
    /**
     * Roles base referenciados en código (Gate, seeder, UsuarioCompaniaController):
     * no se pueden renombrar ni eliminar desde el catálogo.
     */
    public const PROTEGIDOS = ['admin_compania', 'usuario'];

    /**
     * Permisos de nivel plataforma, reservados al super_admin (que hace bypass del
     * Gate). No se ofrecen en la matriz de roles para que un rol operativo no pueda
     * escalar a privilegios de sistema.
     */
    public const PERMISOS_RESERVADOS = [
        'companias.eliminar',
        'zonas.crear',
        'zonas.editar',
        'zonas.eliminar',
    ];

    protected $fillable = [
        'name',
        'descripcion',
        'guard_name',
    ];

    public function esProtegido(): bool
    {
        return in_array($this->name, self::PROTEGIDOS, true);
    }

    /** Etiqueta para mostrar: la descripción si existe, si no el nombre legible. */
    public function etiqueta(): string
    {
        if (filled($this->descripcion)) {
            return $this->descripcion;
        }

        return match ($this->name) {
            'admin_compania' => 'Administrador de compañía',
            'usuario'        => 'Usuario',
            default          => ucfirst(str_replace('_', ' ', $this->name)),
        };
    }
}
