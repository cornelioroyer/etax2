<?php

namespace App\Providers;

use App\Support\Fechas;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
        // Super admin (creadores de la plataforma): pasa todos los permisos.
        Gate::before(function ($user, $ability) {
            return $user->is_admin ? true : null;
        });

        // Fechas en la interfaz: timestamps en GMT-5 (Panamá), fechas puras sin convertir.
        Blade::directive('fechaHora', fn ($expr) => "<?php echo \App\Support\Fechas::hora($expr); ?>");
        Blade::directive('fecha', fn ($expr) => "<?php echo \App\Support\Fechas::fecha($expr); ?>");
    }
}
