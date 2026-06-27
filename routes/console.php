<?php

use App\Models\Compania;
use App\Services\FelConfiguracionDefault;
use App\Services\GeneradorAsientosRecurrentes;
use App\Services\GeneradorCxpRecurrentes;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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

    $this->info('['.Carbon::now()->toDateTimeString().'] '."Asientos recurrentes: {$r['asientos']} asiento(s) en BORRADOR desde {$r['plantillas']} plantilla(s) (hasta {$hasta->toDateString()}).");
})->purpose('Genera los asientos recurrentes vencidos (BORRADOR) de todas las compañías');

// Genera las facturas de proveedor recurrentes vencidas (como BORRADOR) de TODAS
// las compañías hasta hoy (o hasta --fecha=YYYY-MM-DD). Idempotente: no duplica un
// vencimiento ya generado. Lo dispara el scheduler a diario (ver bootstrap/app.php).
// El contador revisa cada borrador en Facturas de Compras y lo contabiliza.
Artisan::command('cxp:recurrentes {--fecha=}', function (GeneradorCxpRecurrentes $generador) {
    $hasta = $this->option('fecha') ? Carbon::parse($this->option('fecha')) : Carbon::now();

    $r = $generador->generarPendientes($hasta, null, 'cron:cxp-recurrentes');

    $this->info('['.Carbon::now()->toDateTimeString().'] '."Facturas recurrentes CxP: {$r['facturas']} factura(s) en BORRADOR desde {$r['plantillas']} plantilla(s) (hasta {$hasta->toDateString()}).");
})->purpose('Genera las facturas de proveedor recurrentes vencidas (BORRADOR) de todas las compañías');

// Verifica que la maquinaria de integridad contable —que vive en el esquema
// maestro de PostgreSQL, FUERA de las migraciones Laravel— esté presente en el
// entorno actual: el UNIQUE de cgl_saldos, los triggers y funciones que mantienen
// y protegen los saldos, y que no haya saldos duplicados. Útil tras provisionar
// un entorno nuevo o restaurar un respaldo. Exit code != 0 si falta algo (A3).
Artisan::command('contabilidad:verificar-integridad', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->warn('Solo aplica en PostgreSQL; entorno actual: '.DB::connection()->getDriverName().'. Omitido.');

        return 0;
    }

    $ok = true;
    $check = function (string $etiqueta, bool $existe) use (&$ok) {
        $this->line(sprintf('  [%s] %s', $existe ? ' OK  ' : 'FALTA', $etiqueta));
        if (! $existe) {
            $ok = false;
        }
    };

    $this->info('Verificando integridad contable en BD: '.DB::selectOne('select current_database() d')->d);

    // UNIQUE que evita saldos duplicados.
    $check('UNIQUE uq_cgl_saldos (compania,periodo,cuenta,contacto,centro_costo)', (bool) DB::selectOne(
        "SELECT 1 FROM pg_constraint WHERE conname='uq_cgl_saldos' AND conrelid='public.cgl_saldos'::regclass"));

    // Funciones de integridad.
    foreach ([
        'fn_actualizar_saldos'         => 'mantiene cgl_saldos al postear/anular',
        'fn_validar_asiento_posteado'  => 'valida cuadre y período al postear',
        'fn_proteger_asiento_posteado' => 'protege asientos posteados de UPDATE/DELETE',
        'fn_validar_detalle_asiento'   => 'valida líneas del asiento',
    ] as $fn => $desc) {
        $check("función {$fn}() — {$desc}", (bool) DB::selectOne(
            "SELECT 1 FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace WHERE n.nspname='public' AND p.proname=?", [$fn]));
    }

    // Triggers que enganchan esas funciones.
    foreach ([
        'trg_cgl_asientos_posteo',
        'trg_cgl_asientos_proteccion',
        'trg_cgl_asientos_saldos',
        'trg_cgl_asientos_detalle_validacion',
    ] as $trg) {
        $check("trigger {$trg}", (bool) DB::selectOne(
            'SELECT 1 FROM pg_trigger WHERE tgname=? AND NOT tgisinternal', [$trg]));
    }

    // Saldos duplicados (deberían ser 0 si el UNIQUE está activo).
    $dups = DB::selectOne(<<<'SQL'
        SELECT COUNT(*) AS n FROM (
            SELECT 1 FROM public.cgl_saldos
             GROUP BY compania_id, periodo_id, cuenta_id, contacto_id, centro_costo_id
            HAVING COUNT(*) > 1
        ) d
    SQL)->n;
    $check("sin saldos duplicados en cgl_saldos (encontrados: {$dups})", $dups == 0);

    $this->newLine();
    if ($ok) {
        $this->info('Integridad contable: OK — toda la maquinaria está presente.');

        return 0;
    }

    $this->error('Integridad contable: FALTAN objetos. Reaplica el esquema maestro (triggers/funciones/UNIQUE) en este entorno antes de operar.');

    return 1;
})->purpose('Verifica triggers, funciones y UNIQUE que protegen la integridad contable (cgl_saldos)');
