<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contact_contactos', 'dias_credito')) {
            Schema::table('contact_contactos', function (Blueprint $table) {
                $table->smallInteger('dias_credito')->nullable()->after('forma_pago');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contact_contactos', 'dias_credito')) {
            Schema::table('contact_contactos', function (Blueprint $table) {
                $table->dropColumn('dias_credito');
            });
        }
    }
};
