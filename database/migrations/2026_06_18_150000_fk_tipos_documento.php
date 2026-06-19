<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 2 (DIFERIDO) de la integridad referencial: agrega la columna constante
 * `auxiliar` a las tablas de documentos y la FK compuesta
 * (auxiliar, tipo_documento) → core_tipos_documento.
 *
 * Está GATEADA por la variable de entorno TIPOS_DOC_FK: por defecto es no-op,
 * porque toca tablas de la BD compartida dev/prod y conviene aplicarla
 * deliberadamente, después de confirmar que el backfill (migración anterior)
 * dejó cero tipos huérfanos. Para aplicarla: TIPOS_DOC_FK=1 php artisan migrate
 *
 * Solo PostgreSQL crea la FK; en SQLite (tests) se omite. Idempotente.
 */
return new class extends Migration
{
    /** tabla => auxiliar constante */
    private array $tablas = [
        'cxc_documentos'  => 'CXC',
        'cxp_documentos'  => 'CXP',
        'ventas_facturas' => 'CXC',
    ];

    public function up(): void
    {
        if (! env('TIPOS_DOC_FK')) {
            Log::info('FK tipos_documento diferida (TIPOS_DOC_FK no activada); migración no-op.');
            return;
        }

        if (! Schema::hasTable('core_tipos_documento')) {
            return;
        }

        foreach ($this->tablas as $tabla => $aux) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            // 1) Columna constante auxiliar (redundante por diseño: el submayor
            //    de cada tabla es fijo) para poder referenciar la PK compuesta.
            if (! Schema::hasColumn($tabla, 'auxiliar')) {
                Schema::table($tabla, function (Blueprint $t) use ($aux) {
                    $t->string('auxiliar', 20)->default($aux);
                });
            }
            DB::table($tabla)->whereNull('auxiliar')->update(['auxiliar' => $aux]);

            // 2) Solo Postgres y solo si no existe ya la FK.
            if (DB::connection()->getDriverName() !== 'pgsql') {
                continue;
            }

            $fk = $tabla.'_tipodoc_fk';
            $existe = DB::selectOne(
                'SELECT 1 FROM pg_constraint WHERE conname = ?',
                [$fk]
            );
            if ($existe) {
                continue;
            }

            DB::statement(
                "ALTER TABLE {$tabla}
                 ADD CONSTRAINT {$fk}
                 FOREIGN KEY (auxiliar, tipo_documento)
                 REFERENCES core_tipos_documento (auxiliar, tipo_documento)"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_keys($this->tablas) as $tabla) {
            DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT IF EXISTS {$tabla}_tipodoc_fk");
        }
    }
};
