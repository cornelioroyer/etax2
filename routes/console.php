<?php

use App\Models\Compania;
use App\Services\FelConfiguracionDefault;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backfill: aplica la configuración FEL por defecto (tokens demo HKA) a las
// compañías que todavía no tengan una. Idempotente: no pisa configs existentes.
Artisan::command('fel:config-default', function (FelConfiguracionDefault $servicio) {
    $creadas = 0;

    Compania::query()->orderBy('id')->each(function (Compania $compania) use ($servicio, &$creadas) {
        if ($servicio->aplicar($compania->id, 'console:fel:config-default')) {
            $creadas++;
            $this->info("  FEL por defecto aplicada a compañía {$compania->id} — {$compania->nombre}");
        }
    });

    $this->info("Listo. Configuraciones FEL creadas: {$creadas}.");
})->purpose('Aplica la configuración FEL por defecto a las compañías sin configuración');
