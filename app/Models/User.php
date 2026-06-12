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

        $ids = DB::table('seg_usuarios_roles')
            ->where('model_type', self::class)
            ->where('model_id', $this->id)
            ->whereNotNull('compania_id')
            ->pluck('compania_id')
            ->unique();

        return Compania::whereIn('id', $ids)->orderBy('nombre')->get();
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
