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
