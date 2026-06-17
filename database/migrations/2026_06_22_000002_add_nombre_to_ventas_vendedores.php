<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ventas_vendedores') && ! Schema::hasColumn('ventas_vendedores', 'nombre')) {
            Schema::table('ventas_vendedores', function (Blueprint $table) {
                $table->string('nombre', 200)->nullable()->after('codigo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ventas_vendedores') && Schema::hasColumn('ventas_vendedores', 'nombre')) {
            Schema::table('ventas_vendedores', function (Blueprint $table) {
                $table->dropColumn('nombre');
            });
        }
    }
};
