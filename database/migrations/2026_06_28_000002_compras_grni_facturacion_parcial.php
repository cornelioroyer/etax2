<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase B de compras:
 *  - Recepción mueve inventario contra cuenta puente GRNI (Mercancía recibida
 *    no facturada): la recepción necesita almacén destino y guarda su asiento.
 *  - Facturación parcial y varias facturas por OC: se rastrea la cantidad
 *    facturada por línea de la orden, y cada CxP referencia su OC (1:N).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras_recepciones', function (Blueprint $table) {
            if (! Schema::hasColumn('compras_recepciones', 'almacen_id')) {
                $table->unsignedBigInteger('almacen_id')->nullable()->after('proveedor_id');
            }
            if (! Schema::hasColumn('compras_recepciones', 'asiento_id')) {
                $table->unsignedBigInteger('asiento_id')->nullable()->after('estado');
            }
        });

        Schema::table('compras_ordenes_detalle', function (Blueprint $table) {
            if (! Schema::hasColumn('compras_ordenes_detalle', 'cantidad_facturada')) {
                $table->decimal('cantidad_facturada', 18, 4)->default(0)->after('cantidad');
            }
        });

        Schema::table('cxp_documentos', function (Blueprint $table) {
            if (! Schema::hasColumn('cxp_documentos', 'orden_id')) {
                $table->unsignedBigInteger('orden_id')->nullable()->after('proveedor_id');
                $table->index('orden_id');
            }
        });

        Schema::table('cxp_documentos_detalle', function (Blueprint $table) {
            if (! Schema::hasColumn('cxp_documentos_detalle', 'orden_detalle_id')) {
                $table->unsignedBigInteger('orden_detalle_id')->nullable()->after('documento_id');
                $table->index('orden_detalle_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('compras_recepciones', function (Blueprint $table) {
            foreach (['almacen_id', 'asiento_id'] as $col) {
                if (Schema::hasColumn('compras_recepciones', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('compras_ordenes_detalle', function (Blueprint $table) {
            if (Schema::hasColumn('compras_ordenes_detalle', 'cantidad_facturada')) {
                $table->dropColumn('cantidad_facturada');
            }
        });

        Schema::table('cxp_documentos', function (Blueprint $table) {
            if (Schema::hasColumn('cxp_documentos', 'orden_id')) {
                $table->dropIndex(['orden_id']);
                $table->dropColumn('orden_id');
            }
        });

        Schema::table('cxp_documentos_detalle', function (Blueprint $table) {
            if (Schema::hasColumn('cxp_documentos_detalle', 'orden_detalle_id')) {
                $table->dropIndex(['orden_detalle_id']);
                $table->dropColumn('orden_detalle_id');
            }
        });
    }
};
