<?php

use App\Models\AuditActividad;
use App\Models\Compania;
use App\Models\User;
use App\Services\FelConfiguracionDefault;
use App\Services\GeneradorAsientosRecurrentes;
use App\Services\GeneradorCxpRecurrentes;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

    $fallas = [];
    $check = function (string $etiqueta, bool $existe) use (&$fallas) {
        $this->line(sprintf('  [%s] %s', $existe ? ' OK  ' : 'FALTA', $etiqueta));
        if (! $existe) {
            $fallas[] = $etiqueta;
        }
    };

    $bd = DB::selectOne('select current_database() d')->d;
    $this->info('Verificando integridad contable en BD: '.$bd);

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

    // Contenido de funciones críticas: que existan no basta — alguien podría
    // recrear una función con un cuerpo viejo y perder una regla. Se busca un
    // marcador estable del cuerpo (prosrc). Solo se evalúa si la función existe
    // (su ausencia ya la reporta el bloque anterior).
    $contiene = function (string $fn, string $marcador): bool {
        $row = DB::selectOne(
            'SELECT p.prosrc AS src FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace WHERE n.nspname=\'public\' AND p.proname=?',
            [$fn]
        );

        return $row !== null && str_contains($row->src, $marcador);
    };

    foreach ([
        ['fn_proteger_asiento_posteado', 'no se puede anular un asiento en un periodo cerrado', 'bloquea anular en período cerrado (A4)'],
        ['fn_validar_asiento_posteado',  'no se puede postear',                                  'bloquea postear en período cerrado'],
        ['fn_validar_asiento_posteado',  'descuadrado',                                          'rechaza asiento descuadrado al postear'],
    ] as [$fn, $marcador, $regla]) {
        $check("contenido {$fn}() — {$regla}", $contiene($fn, $marcador));
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
    if (empty($fallas)) {
        $this->info('Integridad contable: OK — toda la maquinaria está presente.');

        return 0;
    }

    $this->error('Integridad contable: FALTAN '.count($fallas).' objeto(s). Reaplica el esquema maestro (triggers/funciones/UNIQUE) en este entorno antes de operar.');

    // Dejar constancia en la bitácora de auditoría (visible en Auditoría global,
    // super_admin). Es un evento de SISTEMA: en CLI no hay usuario autenticado,
    // por eso se pasa usuario_nombre explícito (si no, registrar() lo descarta).
    // compania_id null = evento global (la integridad de cgl_saldos no es por compañía).
    try {
        AuditActividad::registrar([
            'compania_id' => null,
            'usuario_nombre' => 'sistema (verificación de integridad)',
            'evento' => 'integridad_contable_fallo',
            'entidad' => 'Integridad contable',
            'entidad_tabla' => 'cgl_saldos',
            'descripcion' => 'Faltan '.count($fallas)." objeto(s) de integridad en BD {$bd}: ".implode('; ', $fallas),
            'valores_nuevos' => ['bd' => $bd, 'faltantes' => $fallas],
        ]);
    } catch (\Throwable $e) {
        Log::error('verificar-integridad: no se pudo registrar en auditoría: '.$e->getMessage());
    }

    // Notificar por correo a los super_admin (is_admin). El correo es PUSH; el
    // log queda como respaldo. Un fallo de envío NO cambia el exit code: la
    // inconsistencia ya quedó reportada y debe resolverse igual.
    $destinatarios = User::query()
        ->where('is_admin', true)
        ->whereNotNull('email')
        ->pluck('email')
        ->all();

    if (empty($destinatarios)) {
        $this->warn('No hay super_admin con correo; no se envió notificación (revisa storage/logs/integridad.log).');

        return 1;
    }

    $cuerpo = "Se detectaron inconsistencias en la integridad contable de eTax2.\n\n"
        ."Base de datos: {$bd}\n"
        ."Fecha:         ".date('Y-m-d H:i:s')."\n\n"
        ."Objetos faltantes o con problema:\n  - ".implode("\n  - ", $fallas)."\n\n"
        ."Acción recomendada: reaplica el esquema maestro de PostgreSQL en este "
        ."entorno (UNIQUE uq_cgl_saldos, triggers trg_cgl_asientos_* y funciones "
        ."fn_*) y vuelve a ejecutar:\n  php artisan contabilidad:verificar-integridad\n";

    try {
        Mail::raw($cuerpo, function ($mensaje) use ($destinatarios, $bd) {
            $mensaje->to($destinatarios)
                ->subject("[eTax2] ALERTA: inconsistencia de integridad contable ({$bd})");
        });
        $this->info('Notificación enviada a: '.implode(', ', $destinatarios));
    } catch (\Throwable $e) {
        // No romper el comando si el correo falla; dejar rastro para diagnóstico.
        $this->error('No se pudo enviar la notificación por correo: '.$e->getMessage());
        Log::error('verificar-integridad: fallo al enviar correo de alerta: '.$e->getMessage());
    }

    return 1;
})->purpose('Verifica triggers, funciones y UNIQUE que protegen la integridad contable (cgl_saldos); notifica por correo a super_admin si falta algo');
