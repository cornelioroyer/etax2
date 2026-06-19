<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda el monto de retención (ITBMS/ISR) aplicado en un pago a proveedor.
 * La retención se descuenta del efectivo entregado al proveedor y se traslada
 * como pasivo a la DGI; el saldo de la factura se reduce por el total aplicado
 * (efectivo + retención). Columna informativa para trazabilidad y reportes de
 * retención; el efecto contable real vive en el asiento del pago.
 *
 * Aditiva (columna nullable con default 0): segura en la BD compartida dev/prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cxp_documentos') && ! Schema::hasColumn('cxp_documentos', 'retencion')) {
            Schema::table('cxp_documentos', function (Blueprint $table) {
                $table->decimal('retencion', 18, 2)->default(0)->after('impuesto');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cxp_documentos') && Schema::hasColumn('cxp_documentos', 'retencion')) {
            Schema::table('cxp_documentos', function (Blueprint $table) {
                $table->dropColumn('retencion');
            });
        }
    }
};
