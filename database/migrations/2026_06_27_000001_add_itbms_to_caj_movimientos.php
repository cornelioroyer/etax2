<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caja menuda: separa el ITBMS crédito fiscal del gasto en los egresos.
 * `itbms_monto` es la porción de ITBMS acreditable incluida en `monto`
 * (que sigue siendo el TOTAL que sale de la caja, base + impuesto), por lo
 * que el cálculo de saldo no cambia. `documento_ref` guarda el número de
 * comprobante/factura que respalda el gasto (soporte del crédito fiscal).
 * Guardas hasColumn: no-op donde ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caj_movimientos')) {
            return;
        }

        Schema::table('caj_movimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('caj_movimientos', 'itbms_monto')) {
                $table->decimal('itbms_monto', 18, 2)->default(0)->after('monto');
            }
            if (! Schema::hasColumn('caj_movimientos', 'documento_ref')) {
                $table->string('documento_ref', 60)->nullable()->after('itbms_monto');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('caj_movimientos')) {
            return;
        }

        Schema::table('caj_movimientos', function (Blueprint $table) {
            if (Schema::hasColumn('caj_movimientos', 'documento_ref')) {
                $table->dropColumn('documento_ref');
            }
            if (Schema::hasColumn('caj_movimientos', 'itbms_monto')) {
                $table->dropColumn('itbms_monto');
            }
        });
    }
};
