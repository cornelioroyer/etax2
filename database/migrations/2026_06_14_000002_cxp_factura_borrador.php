<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía el CHECK de cxp_documentos.estado para admitir el estado
 * 'BORRADOR' (factura por pagar creada pero aún no contabilizada).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return; // SQLite (tests) no tiene el CHECK; nada que ampliar.
        }

        DB::statement('ALTER TABLE cxp_documentos DROP CONSTRAINT IF EXISTS cxp_documentos_estado_check');
        DB::statement("ALTER TABLE cxp_documentos ADD CONSTRAINT cxp_documentos_estado_check CHECK (estado IN ('BORRADOR', 'PENDIENTE', 'PARCIAL', 'PAGADO', 'ANULADO'))");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE cxp_documentos DROP CONSTRAINT IF EXISTS cxp_documentos_estado_check');
        DB::statement("ALTER TABLE cxp_documentos ADD CONSTRAINT cxp_documentos_estado_check CHECK (estado IN ('PENDIENTE', 'PARCIAL', 'PAGADO', 'ANULADO'))");
    }
};
