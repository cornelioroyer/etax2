<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etiqueta las aplicaciones de un crédito a favor (anticipo / nota de crédito)
 * con el pago que las orquestó. Cuando un pago aplica créditos además del
 * efectivo, esas aplicaciones tienen documento_origen_id = el crédito (no el
 * pago), por lo que sin esta columna no se podían vincular al pago para
 * anularlo/corregirlo como una sola operación.
 *
 * pago_id es null en las aplicaciones que no nacieron dentro de un pago
 * (p. ej. aplicar un anticipo desde su propia pantalla). Aditiva y nullable:
 * segura en la BD compartida dev/prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cxp_aplicaciones') && ! Schema::hasColumn('cxp_aplicaciones', 'pago_id')) {
            Schema::table('cxp_aplicaciones', function (Blueprint $table) {
                $table->unsignedBigInteger('pago_id')->nullable()->after('documento_destino_id');
                $table->index('pago_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cxp_aplicaciones') && Schema::hasColumn('cxp_aplicaciones', 'pago_id')) {
            Schema::table('cxp_aplicaciones', function (Blueprint $table) {
                $table->dropIndex(['pago_id']);
                $table->dropColumn('pago_id');
            });
        }
    }
};
