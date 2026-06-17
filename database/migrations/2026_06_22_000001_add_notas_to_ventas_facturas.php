<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ventas_facturas') && ! Schema::hasColumn('ventas_facturas', 'notas')) {
            Schema::table('ventas_facturas', function (Blueprint $table) {
                $table->text('notas')->nullable()->after('estado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ventas_facturas') && Schema::hasColumn('ventas_facturas', 'notas')) {
            Schema::table('ventas_facturas', function (Blueprint $table) {
                $table->dropColumn('notas');
            });
        }
    }
};
