<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // compras_ordenes: campo libre para observaciones
        if (! Schema::hasColumn('compras_ordenes', 'observaciones')) {
            Schema::table('compras_ordenes', function (Blueprint $table) {
                $table->text('observaciones')->nullable()->after('total');
            });
        }

        // compras_ordenes_detalle: cuenta contable por línea (contrapartida al generar CxP)
        if (! Schema::hasColumn('compras_ordenes_detalle', 'cuenta_id')) {
            Schema::table('compras_ordenes_detalle', function (Blueprint $table) {
                $table->unsignedBigInteger('cuenta_id')->nullable()->after('impuesto_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('compras_ordenes_detalle', function (Blueprint $table) {
            $table->dropColumn('cuenta_id');
        });
        Schema::table('compras_ordenes', function (Blueprint $table) {
            $table->dropColumn('observaciones');
        });
    }
};
