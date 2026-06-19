<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaza una aplicación de CxP con el asiento que la respalda. Lo necesita el
 * anticipo a proveedor: al aplicarlo a una factura se postea un asiento propio
 * (Dr CXP / Cr Anticipos a proveedores) que debe poder reversarse al anular.
 *
 * Para cobros/pagos y notas de crédito la aplicación NO usa esta columna (su
 * asiento vive en el documento origen), así que queda nullable.
 *
 * Aditiva (columna nullable): segura en la BD compartida dev/prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cxp_aplicaciones') && ! Schema::hasColumn('cxp_aplicaciones', 'asiento_id')) {
            Schema::table('cxp_aplicaciones', function (Blueprint $table) {
                $table->unsignedBigInteger('asiento_id')->nullable()->after('monto_aplicado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cxp_aplicaciones') && Schema::hasColumn('cxp_aplicaciones', 'asiento_id')) {
            Schema::table('cxp_aplicaciones', function (Blueprint $table) {
                $table->dropColumn('asiento_id');
            });
        }
    }
};
