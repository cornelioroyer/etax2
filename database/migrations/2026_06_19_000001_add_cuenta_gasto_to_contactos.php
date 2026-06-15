<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contact_contactos', 'cuenta_gasto_id')) {
            Schema::table('contact_contactos', function (Blueprint $table) {
                $table->unsignedBigInteger('cuenta_gasto_id')->nullable()->after('distrito');
                $table->foreign('cuenta_gasto_id')->references('id')->on('cgl_cuentas')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contact_contactos', 'cuenta_gasto_id')) {
            Schema::table('contact_contactos', function (Blueprint $table) {
                $table->dropForeign(['cuenta_gasto_id']);
                $table->dropColumn('cuenta_gasto_id');
            });
        }
    }
};
