<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Garantiza que el usuario tenga acceso a la compañía por defecto
     * (rol "usuario" en la compañía 1) si aún no tiene ningún rol.
     * Se invoca al registrarse o al entrar con Google.
     */
    public function asegurarAccesoDefault(int $companiaId = 1): void
    {
        $yaTieneRol = DB::table('seg_usuarios_roles')
            ->where('model_type', self::class)
            ->where('model_id', $this->id)
            ->exists();

        if ($yaTieneRol) {
            return;
        }

        $rolId = DB::table('seg_roles')->where('name', 'usuario')->value('id');
        $companiaExiste = DB::table('core_companias')->where('id', $companiaId)->exists();

        if (! $rolId || ! $companiaExiste) {
            return; // entorno sin datos (ej. tests) — no hay default que asignar
        }

        DB::table('seg_usuarios_roles')->insert([
            'rol_id' => $rolId,
            'model_type' => self::class,
            'model_id' => $this->id,
            'compania_id' => $companiaId,
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Compañías a las que el usuario tiene acceso.
     * Super admin (is_admin) accede a todas; el resto, a las compañías
     * donde tiene algún rol asignado (seg_usuarios_roles.compania_id).
     */
    public function companiasAccesibles(): Collection
    {
        if ($this->is_admin) {
            return Compania::orderBy('nombre')->get();
        }

        // Asignación GLOBAL (rol con compania_id NULL): acceso a todas.
        if ($this->tieneAsignacionGlobal()) {
            return Compania::orderBy('nombre')->get();
        }

        $ids = DB::table('seg_usuarios_roles')
            ->where('model_type', self::class)
            ->where('model_id', $this->id)
            ->whereNotNull('compania_id')
            ->pluck('compania_id')
            ->unique();

        return Compania::whereIn('id', $ids)->orderBy('nombre')->get();
    }

    /** ¿El usuario tiene algún rol asignado de forma global (todas las compañías)? */
    public function tieneAsignacionGlobal(): bool
    {
        return DB::table('seg_usuarios_roles_globales')
            ->where('user_id', $this->id)
            ->exists();
    }

    /**
     * Permisos DENEGADOS al usuario en una compañía (override negativo).
     * Memoizado por compañía para no golpear la BD en cada llamada a can()
     * desde el Gate::before. Devuelve nombres de permiso (ej. "ventas.crear").
     *
     * @var array<int, array<int, string>>
     */
    protected array $cachePermisosDenegados = [];

    /**
     * @return array<int, string>
     */
    public function permisosDenegados(int $companiaId): array
    {
        if (! array_key_exists($companiaId, $this->cachePermisosDenegados)) {
            $this->cachePermisosDenegados[$companiaId] = DB::table('seg_usuarios_permisos_denegados')
                ->join('seg_permisos', 'seg_permisos.id', '=', 'seg_usuarios_permisos_denegados.permiso_id')
                ->where('seg_usuarios_permisos_denegados.model_type', self::class)
                ->where('seg_usuarios_permisos_denegados.model_id', $this->id)
                ->where('seg_usuarios_permisos_denegados.compania_id', $companiaId)
                ->pluck('seg_permisos.name')
                ->all();
        }

        return $this->cachePermisosDenegados[$companiaId];
    }

    /**
     * ¿El permiso está denegado para este usuario en la compañía indicada?
     */
    public function tienePermisoDenegado(string $permiso, ?int $companiaId): bool
    {
        if (! $companiaId) {
            return false;
        }

        return in_array($permiso, $this->permisosDenegados($companiaId), true);
    }

    /**
     * Limpia la memoización de denegados (tras actualizarlos).
     */
    public function olvidarPermisosDenegados(): void
    {
        $this->cachePermisosDenegados = [];
    }

    /**
     * Permisos otorgados de forma GLOBAL (a todas las compañías): vía roles
     * asignados con compania_id NULL, o permisos directos con compania_id NULL.
     * Memoizado por petición. Aplica en CUALQUIER compañía (incl. futuras).
     *
     * @var array<int, string>|null
     */
    protected ?array $cachePermisosGlobales = null;

    /**
     * @return array<int, string>
     */
    public function permisosGlobales(): array
    {
        if ($this->cachePermisosGlobales === null) {
            $rolIds = DB::table('seg_usuarios_roles_globales')
                ->where('user_id', $this->id)
                ->pluck('rol_id');

            $this->cachePermisosGlobales = $rolIds->isEmpty() ? [] : DB::table('seg_roles_permisos')
                ->join('seg_permisos', 'seg_permisos.id', '=', 'seg_roles_permisos.permiso_id')
                ->whereIn('seg_roles_permisos.rol_id', $rolIds)
                ->pluck('seg_permisos.name')
                ->unique()
                ->values()
                ->all();
        }

        return $this->cachePermisosGlobales;
    }

    /** ¿El usuario tiene este permiso de forma global (todas las compañías)? */
    public function tienePermisoGlobal(string $permiso): bool
    {
        return in_array($permiso, $this->permisosGlobales(), true);
    }

    /** Limpia la memoización de permisos globales (tras cambiarlos). */
    public function olvidarPermisosGlobales(): void
    {
        $this->cachePermisosGlobales = null;
    }

    /**
     * Compañías que este usuario administra (rol admin_compania).
     */
    public function companiasAdministradas(): Collection
    {
        if ($this->is_admin) {
            return Compania::orderBy('nombre')->get();
        }

        $rolAdminId = DB::table('seg_roles')->where('name', 'admin_compania')->value('id');

        // admin_compania asignado de forma global → administra todas.
        $adminGlobal = DB::table('seg_usuarios_roles_globales')
            ->where('user_id', $this->id)
            ->where('rol_id', $rolAdminId)
            ->exists();

        if ($adminGlobal) {
            return Compania::orderBy('nombre')->get();
        }

        $ids = DB::table('seg_usuarios_roles')
            ->where('model_type', self::class)
            ->where('model_id', $this->id)
            ->where('rol_id', $rolAdminId)
            ->whereNotNull('compania_id')
            ->pluck('compania_id')
            ->unique();

        return Compania::whereIn('id', $ids)->orderBy('nombre')->get();
    }
}
