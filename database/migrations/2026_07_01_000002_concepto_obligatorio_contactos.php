<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Default a nivel de BD: cualquier inserción que no indique concepto
        // (importadores, EmparejaContactos, etc.) recibe '1' = Compra o Adquisiciones.
        Schema::table('contact_contactos', function (Blueprint $table) {
            $table->string('concepto', 250)->nullable()->default('1')->change();
        });

        // Backfill: proveedores existentes sin concepto reciben '1'.
        $proveedorIds = DB::table('contact_contactos_tipos as ct')
            ->join('contact_tipos as t', 't.id', '=', 'ct.tipo_id')
            ->where('t.codigo', 'PROVEEDOR')
            ->pluck('ct.contacto_id');

        DB::table('contact_contactos')
            ->whereNull('concepto')
            ->whereIn('id', $proveedorIds)
            ->update(['concepto' => '1']);
    }

    public function down(): void
    {
        // Solo quita el default; el backfill de datos no se revierte
        // (no se puede distinguir un '1' asignado de uno elegido).
        Schema::table('contact_contactos', function (Blueprint $table) {
            $table->string('concepto', 250)->nullable()->default(null)->change();
        });
    }
};
