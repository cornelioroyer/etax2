<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EstablecerCompaniaActiva;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Bitácora de auditoría de las corridas nocturnas: cada comando anexa su
        // salida (cuántos generó y hasta qué fecha) a storage/logs/recurrentes.log.
        // Una línea por corrida → permite confirmar "¿corrió anoche y qué hizo?".
        $logRecurrentes = storage_path('logs/recurrentes.log');

        // Genera a diario los asientos recurrentes vencidos como BORRADOR para
        // que el contador los revise y postee. withoutOverlapping evita que dos
        // corridas se pisen si una se atrasa.
        $schedule->command('asientos:recurrentes')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->appendOutputTo($logRecurrentes);

        // Genera a diario las facturas de proveedor recurrentes vencidas como
        // BORRADOR (alquiler, servicios fijos); el contador las revisa y contabiliza.
        $schedule->command('cxp:recurrentes')
            ->dailyAt('02:10')
            ->withoutOverlapping()
            ->appendOutputTo($logRecurrentes);

        // Verificación diaria de la maquinaria de integridad contable (UNIQUE,
        // triggers y funciones que protegen cgl_saldos; viven en el esquema
        // maestro PG, fuera de las migraciones). La salida (OK / FALTA) se anexa
        // a storage/logs/integridad.log; si el comando falla (exit != 0),
        // onFailure marca una línea de ALERTA para que salte a la vista.
        $logIntegridad = storage_path('logs/integridad.log');

        $schedule->command('contabilidad:verificar-integridad')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->appendOutputTo($logIntegridad)
            ->onFailure(function () use ($logIntegridad) {
                file_put_contents(
                    $logIntegridad,
                    '['.date('Y-m-d H:i:s').'] ALERTA: la verificación de integridad contable FALLÓ (faltan objetos del esquema maestro). Revisa el detalle de esta corrida.'.PHP_EOL,
                    FILE_APPEND
                );
            });
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->appendToGroup('web', EstablecerCompaniaActiva::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
