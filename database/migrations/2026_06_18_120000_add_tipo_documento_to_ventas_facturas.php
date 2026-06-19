<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unifica ventas en una sola tabla: ventas_facturas ahora guarda facturas y
 * notas de crédito, distinguidas por tipo_documento (como cxp_documentos en
 * compras). Aditiva: las columnas son compatibles hacia atrás.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas_facturas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas_facturas', 'tipo_documento')) {
                $table->string('tipo_documento', 30)->default('FACTURA');
            }
            if (! Schema::hasColumn('ventas_facturas', 'motivo')) {
                $table->text('motivo')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas_facturas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas_facturas', 'motivo')) {
                $table->dropColumn('motivo');
            }
            if (Schema::hasColumn('ventas_facturas', 'tipo_documento')) {
                $table->dropColumn('tipo_documento');
            }
        });
    }
};
