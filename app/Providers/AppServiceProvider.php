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
use App\Services\MenuBuilder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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

            // 1.5) Crear compañías es UNIVERSAL: cualquier usuario autenticado
            // (con rol o sin él) puede ver el módulo Compañías y crear una
            // compañía nueva, quedando como admin_compania de ella (ver
            // CompaniaController::store). Decisión de producto: no depende del
            // rol ni de la matriz de permisos. Solo se conceden lectura y crear;
            // eliminar/editar compañías y zonas siguen restringidos.
            // Crear no modifica datos de otra compañía y el directorio sigue
            // filtrado a las compañías accesibles (CompaniaController::index).
            //
            // Se cubren AMBOS modelos de permisos porque el enforcement nuevo
            // (AutorizarOpcion, global sobre el grupo web) consulta los abilities
            // por opción × acción, no los viejos:
            //   - viejo:  companias.ver | companias.crear
            //   - nuevo:  companias.*.acceder (ver directorio/formulario) y
            //             companias.crear.insertar (crear).
            // Quedan FUERA .actualizar/.borrar/.exportar/.imprimir y el reservado
            // companias.eliminar (no terminan en .acceder ni son el insert de crear).
            if ($ability === 'companias.crear' || $ability === 'companias.ver') {
                return true;
            }
            if (str_starts_with($ability, 'companias.')
                && (str_ends_with($ability, '.acceder') || $ability === 'companias.crear.insertar')) {
                return true;
            }

            // 2) Compañía 1 (sistema): solo lectura para no-super-admin, aunque
            // sean admin de otras compañías. El team de permisos (compañía activa)
            // lo fija el middleware EstablecerCompaniaActiva.
            $companiaActiva = app(\Spatie\Permission\PermissionRegistrar::class)->getPermissionsTeamId();

            // companias.crear se exceptúa: crear una compañía nueva no modifica
            // datos de la compañía 1, y todos los usuarios pueden crearlas.
            // Acciones de lectura permitidas en la compañía sistema: el modelo
            // viejo usa ".ver"; el nuevo (por opción) usa ".acceder" y, como
            // exportar/imprimir no modifican datos, también se consideran lectura.
            $sufijosLectura = ['.ver', '.acceder', '.exportar', '.imprimir'];
            $esLectura = false;
            foreach ($sufijosLectura as $sufijo) {
                if (str_ends_with($ability, $sufijo)) {
                    $esLectura = true;
                    break;
                }
            }
            if ((int) $companiaActiva === self::COMPANIA_SISTEMA
                && str_contains($ability, '.')
                && ! $esLectura
                && $ability !== 'companias.crear') {
                return false;
            }

            // 2.5) Override negativo por usuario: un permiso denegado a este
            // usuario en la compañía activa se rechaza aunque su rol lo otorgue.
            // No afecta al super_admin (ya retornó arriba). Aislado por compañía.
            if (str_contains($ability, '.')
                && method_exists($user, 'tienePermisoDenegado')
                && $user->tienePermisoDenegado($ability, $companiaActiva ? (int) $companiaActiva : null)) {
                return false;
            }

            // 2.6) Asignación GLOBAL: un rol/permiso otorgado con compania_id NULL
            // aplica en TODAS las compañías (presentes y futuras). Se evalúa
            // después de los denegados (una denegación por compañía sí lo bloquea).
            if (str_contains($ability, '.')
                && method_exists($user, 'tienePermisoGlobal')
                && $user->tienePermisoGlobal($ability)) {
                return true;
            }

            // 2.7) Compatibilidad del modelo viejo: los permisos por módulo
            // (modulo.ver|gestionar|crear|editar|eliminar y cxp.registrar_qr) se
            // evalúan contra el modelo nuevo (por opción × acción), que es la
            // fuente de verdad. Así el código que aún usa nombres viejos
            // (permission: en rutas, @can en vistas) sigue funcionando sin
            // reescribirse. Los RESERVADOS de plataforma NO se traducen.
            if (\App\Support\PermisoLegacy::esLegacy($ability)) {
                foreach (\App\Support\PermisoLegacy::candidatos($ability) as $permisoNuevo) {
                    if ($user->can($permisoNuevo)) {
                        return true;
                    }
                }

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

        // Menú lateral dirigido por BD: solo se arma cuando se renderiza el menú.
        // Si core_menu_items está vacía, MenuBuilder devuelve [] y el Blade cae
        // al menú estático heredado (fallback sin riesgo).
        View::composer('layouts.navigation', function ($view) {
            $view->with('arbolMenu', app(MenuBuilder::class)->build());
        });

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
