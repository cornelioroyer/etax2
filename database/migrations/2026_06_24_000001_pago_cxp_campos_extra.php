<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos extra del pago a proveedor (cxp_documentos, tipo PAGO):
 *
 * - cuenta_pago_id  : cuenta contable (banco/caja) desde la que se pagó, para
 *                     reconstruir el medio de pago sin abrir el asiento y para
 *                     poder "corregir" un pago (anular + reabrir prellenado).
 * - referencia      : número de cheque / transferencia / observación del medio.
 * - retencion_itbms : porción de la retención que corresponde a ITBMS.
 * - retencion_isr   : porción de la retención que corresponde a ISR.
 *
 * La columna existente `retencion` se mantiene como el TOTAL retenido
 * (itbms + isr) por compatibilidad con reportes y la vista. El descuento por
 * pronto pago reutiliza la columna existente `descuento` (en un PAGO siempre
 * estaba en 0).
 *
 * Todo aditivo (nullable / default 0): seguro en la BD compartida dev/prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cxp_documentos')) {
            return;
        }

        Schema::table('cxp_documentos', function (Blueprint $table) {
            if (! Schema::hasColumn('cxp_documentos', 'cuenta_pago_id')) {
                $table->unsignedBigInteger('cuenta_pago_id')->nullable()->after('asiento_id');
            }
            if (! Schema::hasColumn('cxp_documentos', 'referencia')) {
                $table->string('referencia', 100)->nullable()->after('numero');
            }
            if (! Schema::hasColumn('cxp_documentos', 'retencion_itbms')) {
                $table->decimal('retencion_itbms', 18, 2)->default(0)->after('retencion');
            }
            if (! Schema::hasColumn('cxp_documentos', 'retencion_isr')) {
                $table->decimal('retencion_isr', 18, 2)->default(0)->after('retencion_itbms');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cxp_documentos')) {
            return;
        }

        Schema::table('cxp_documentos', function (Blueprint $table) {
            foreach (['cuenta_pago_id', 'referencia', 'retencion_itbms', 'retencion_isr'] as $col) {
                if (Schema::hasColumn('cxp_documentos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
