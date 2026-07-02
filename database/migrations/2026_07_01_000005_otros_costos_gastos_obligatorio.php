<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Default a nivel de BD: OTROS GASTOS Y MISCELANEOS VARIOS (62) = id 60.
        // Cubre inserciones que no indiquen el campo (importadores, EmparejaContactos).
        Schema::table('contact_contactos', function (Blueprint $table) {
            $table->bigInteger('otros_costos_gastos_id')->nullable()->default(60)->change();
        });

        // Backfill: proveedores existentes sin clasificación reciben el default.
        $proveedorIds = DB::table('contact_contactos_tipos as ct')
            ->join('contact_tipos as t', 't.id', '=', 'ct.tipo_id')
            ->where('t.codigo', 'PROVEEDOR')
            ->pluck('ct.contacto_id');

        DB::table('contact_contactos')
            ->whereNull('otros_costos_gastos_id')
            ->whereIn('id', $proveedorIds)
            ->update(['otros_costos_gastos_id' => 60]);
    }

    public function down(): void
    {
        // Solo quita el default; el backfill de datos no se revierte.
        Schema::table('contact_contactos', function (Blueprint $table) {
            $table->bigInteger('otros_costos_gastos_id')->nullable()->default(null)->change();
        });
    }
};
