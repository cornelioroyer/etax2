<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El índice único (compania_id, proveedor_id, tipo_documento, numero) impedía
 * volver a registrar una factura cuyo único registro previo había sido ANULADO
 * (provocaba un error 23505 al re-registrar). Se reemplaza por un índice único
 * PARCIAL que excluye los documentos ANULADO: así se conserva el histórico de
 * anulaciones y se permite re-registrar el mismo número, manteniendo la regla de
 * que no puede haber dos documentos VIGENTES con el mismo número del proveedor.
 *
 * No destructivo: el índice parcial es menos restrictivo que el actual, por lo que
 * su creación nunca falla (el constraint vigente garantiza cero duplicados previos).
 */
return new class extends Migration
{
    private string $constraint = 'cxp_documentos_compania_id_proveedor_id_tipo_documento_nume_key';
    private string $indiceParcial = 'cxp_documentos_numero_vigente_uniq';

    public function up(): void
    {
        DB::statement("ALTER TABLE cxp_documentos DROP CONSTRAINT IF EXISTS {$this->constraint}");
        DB::statement(
            "CREATE UNIQUE INDEX IF NOT EXISTS {$this->indiceParcial} ".
            'ON cxp_documentos (compania_id, proveedor_id, tipo_documento, numero) '.
            "WHERE estado <> 'ANULADO'"
        );
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS {$this->indiceParcial}");
        DB::statement(
            "ALTER TABLE cxp_documentos ADD CONSTRAINT {$this->constraint} ".
            'UNIQUE (compania_id, proveedor_id, tipo_documento, numero)'
        );
    }
};
