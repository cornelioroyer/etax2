<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda el CUFE (Código Único de Factura Electrónica) en la propia factura de
 * venta, no solo dentro de `extra`. Permite reconsultar la factura en la DGI,
 * mostrarla y deduplicar las importaciones por una columna de primer nivel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ventas_facturas', 'cufe')) {
            Schema::table('ventas_facturas', function (Blueprint $table) {
                $table->string('cufe', 120)->nullable()->after('numero');
            });
        }

        // Backfill: traer el CUFE que las importaciones previas dejaron en extra.
        // El operador JSON `->>'` es de PostgreSQL; en SQLite (tests) no existe y
        // además no hay datos previos que rellenar, así que se omite.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("UPDATE ventas_facturas SET cufe = extra->>'cufe' WHERE cufe IS NULL AND extra->>'cufe' IS NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ventas_facturas', 'cufe')) {
            Schema::table('ventas_facturas', function (Blueprint $table) {
                $table->dropColumn('cufe');
            });
        }
    }
};
