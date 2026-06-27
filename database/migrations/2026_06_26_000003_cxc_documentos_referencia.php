<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `referencia` a cxc_documentos (espejo de cxp_documentos).
 *
 * En un cobro (tipo PAGO) guarda el número de depósito / transferencia / cheque
 * recibido, para: (1) reconstruir el medio sin abrir el asiento, (2) dar
 * idempotencia al importador de cobros (no recargar el mismo depósito dos veces).
 * También queda disponible para el resto de documentos de CxC.
 *
 * Aditivo y nullable: seguro en la BD compartida dev/prod. La tabla
 * `cxc_documentos` vive en el esquema maestro PG; en SQLite (tests) la crea la
 * migración 2026_06_13_000001 y aquí se le agrega la columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cxc_documentos')) {
            return;
        }

        Schema::table('cxc_documentos', function (Blueprint $table) {
            if (! Schema::hasColumn('cxc_documentos', 'referencia')) {
                $table->string('referencia', 100)->nullable()->after('numero');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cxc_documentos')) {
            return;
        }

        Schema::table('cxc_documentos', function (Blueprint $table) {
            if (Schema::hasColumn('cxc_documentos', 'referencia')) {
                $table->dropColumn('referencia');
            }
        });
    }
};
