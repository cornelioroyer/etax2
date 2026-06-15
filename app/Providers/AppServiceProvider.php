<?php

namespace App\Providers;

use App\Models\AuditActividad;
use App\Observers\AuditObserver;
use App\Support\Fechas;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
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

            // companias.crear se exceptúa: crear una compañía nueva no modifica
            // datos de la compañía 1, y todos los usuarios pueden crearlas.
            if ((int) $companiaActiva === self::COMPANIA_SISTEMA
                && str_contains($ability, '.')
                && ! str_ends_with($ability, '.ver')
                && $ability !== 'companias.crear') {
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

        $this->registrarAuditoria();
    }

    /**
     * Bitácora de actividad de usuarios: observa todos los modelos del dominio
     * (crear/editar/eliminar) y escucha los eventos de autenticación. El modelo
     * AuditActividad se excluye para no auditarse a sí mismo (recursión).
     */
    private function registrarAuditoria(): void
    {
        foreach (glob(app_path('Models').'/*.php') as $archivo) {
            $clase = 'App\\Models\\'.basename($archivo, '.php');

            if ($clase === AuditActividad::class) {
                continue;
            }

            if (is_subclass_of($clase, Model::class)) {
                $clase::observe(AuditObserver::class);
            }
        }

        Event::listen(Login::class, fn (Login $e) => AuditActividad::registrar([
            'evento' => 'login', 'usuario' => $e->user,
        ]));

        Event::listen(Logout::class, fn (Logout $e) => AuditActividad::registrar([
            'evento' => 'logout', 'usuario' => $e->user,
        ]));

        Event::listen(Failed::class, fn (Failed $e) => AuditActividad::registrar([
            'evento' => 'login_fallido',
            'usuario_nombre' => $e->credentials['email'] ?? 'desconocido',
            'descripcion' => 'Intento de acceso fallido',
        ]));
    }
}
