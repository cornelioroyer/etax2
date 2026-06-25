<?php

use App\Models\Compania;
use App\Services\FelConfiguracionDefault;
use App\Services\GeneradorAsientosRecurrentes;
use App\Services\GeneradorCxpRecurrentes;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
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

// Genera los asientos recurrentes vencidos (como BORRADOR) de TODAS las
// compañías hasta hoy (o hasta --fecha=YYYY-MM-DD). Idempotente: no duplica un
// vencimiento ya generado. Lo dispara el scheduler a diario (ver bootstrap/app.php),
// y también se puede correr a mano para ponerse al día.
Artisan::command('asientos:recurrentes {--fecha=}', function (GeneradorAsientosRecurrentes $generador) {
    $hasta = $this->option('fecha') ? Carbon::parse($this->option('fecha')) : Carbon::now();

    $r = $generador->generarPendientes($hasta, null, 'cron:asientos-recurrentes');

    $this->info("Asientos recurrentes: {$r['asientos']} asiento(s) en BORRADOR desde {$r['plantillas']} plantilla(s) (hasta {$hasta->toDateString()}).");
})->purpose('Genera los asientos recurrentes vencidos (BORRADOR) de todas las compañías');

// Genera las facturas de proveedor recurrentes vencidas (como BORRADOR) de TODAS
// las compañías hasta hoy (o hasta --fecha=YYYY-MM-DD). Idempotente: no duplica un
// vencimiento ya generado. Lo dispara el scheduler a diario (ver bootstrap/app.php).
// El contador revisa cada borrador en Facturas de Compras y lo contabiliza.
Artisan::command('cxp:recurrentes {--fecha=}', function (GeneradorCxpRecurrentes $generador) {
    $hasta = $this->option('fecha') ? Carbon::parse($this->option('fecha')) : Carbon::now();

    $r = $generador->generarPendientes($hasta, null, 'cron:cxp-recurrentes');

    $this->info("Facturas recurrentes CxP: {$r['facturas']} factura(s) en BORRADOR desde {$r['plantillas']} plantilla(s) (hasta {$hasta->toDateString()}).");
})->purpose('Genera las facturas de proveedor recurrentes vencidas (BORRADOR) de todas las compañías');
