<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contact_contactos', 'concepto')) {
            Schema::table('contact_contactos', function (Blueprint $table) {
                $table->string('concepto', 250)->nullable()->after('cuenta_gasto_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contact_contactos', 'concepto')) {
            Schema::table('contact_contactos', function (Blueprint $table) {
                $table->dropColumn('concepto');
            });
        }
    }
};
