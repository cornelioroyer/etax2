<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tipo de compra DGI del proveedor: '1' Local, '2' Importaciones.
        // Default a nivel de BD para inserciones que no lo indiquen (importadores).
        if (! Schema::hasColumn('contact_contactos', 'tipo_compra')) {
            Schema::table('contact_contactos', function (Blueprint $table) {
                $table->string('tipo_compra', 10)->nullable()->default('1')->after('otros_costos_gastos_id');
            });
        }

        // Backfill: proveedores existentes sin tipo de compra quedan como Local.
        $proveedorIds = DB::table('contact_contactos_tipos as ct')
            ->join('contact_tipos as t', 't.id', '=', 'ct.tipo_id')
            ->where('t.codigo', 'PROVEEDOR')
            ->pluck('ct.contacto_id');

        DB::table('contact_contactos')
            ->whereNull('tipo_compra')
            ->whereIn('id', $proveedorIds)
            ->update(['tipo_compra' => '1']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('contact_contactos', 'tipo_compra')) {
            Schema::table('contact_contactos', function (Blueprint $table) {
                $table->dropColumn('tipo_compra');
            });
        }
    }
};
