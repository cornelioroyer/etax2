<?php

namespace App\Providers;

use App\Support\Fechas;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Compañía del sistema (WIN SOFT CORP). Reservada: solo el super_admin
     * tiene privilegios plenos en ella; cualquier otro usuario solo puede ver.
     */
    public const COMPANIA_SISTEMA = 1;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Único Gate::before de la app: reemplaza el auto-registro de
        // spatie/permission (desactivado en config/permission.php con
        // register_permission_check_method=false) para controlar el orden:
        //   1) super_admin (is_admin) pasa todo;
        //   2) en la compañía 1 (sistema) los no-super-admin solo pueden VER;
        //   3) resolución normal de permisos por rol/compañía (igual que Spatie).
        Gate::before(function (Authorizable $user, string $ability, array &$args = []) {
            // 1) Super admin (creadores de la plataforma).
            if ($user->is_admin) {
                return true;
            }

            // 2) Compañía 1 (sistema): solo lectura para no-super-admin, aunque
            // sean admin de otras compañías. El team de permisos (compañía activa)
            // lo fija el middleware EstablecerCompaniaActiva.
            $companiaActiva = app(\Spatie\Permission\PermissionRegistrar::class)->getPermissionsTeamId();

            if ((int) $companiaActiva === self::COMPANIA_SISTEMA
                && str_contains($ability, '.')
                && ! str_ends_with($ability, '.ver')) {
                return false;
            }

            // 3) Resolución estándar de spatie/permission (replicada).
            if (is_string($args[0] ?? null) && ! class_exists($args[0])) {
                $guard = array_shift($args);
            }

            if (method_exists($user, 'checkPermissionTo')) {
                return $user->checkPermissionTo($ability, $guard ?? null) ?: null;
            }

            return null;
        });

        // Fechas en la interfaz: timestamps en GMT-5 (Panamá), fechas puras sin convertir.
        Blade::directive('fechaHora', fn ($expr) => "<?php echo \App\Support\Fechas::hora($expr); ?>");
        Blade::directive('fecha', fn ($expr) => "<?php echo \App\Support\Fechas::fecha($expr); ?>");
    }
}
