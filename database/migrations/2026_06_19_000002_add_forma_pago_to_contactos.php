<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contact_contactos', 'forma_pago')) {
            Schema::table('contact_contactos', function (Blueprint $table) {
                $table->string('forma_pago', 10)->nullable()->after('dv');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contact_contactos', 'forma_pago')) {
            Schema::table('contact_contactos', function (Blueprint $table) {
                $table->dropColumn('forma_pago');
            });
        }
    }
};
