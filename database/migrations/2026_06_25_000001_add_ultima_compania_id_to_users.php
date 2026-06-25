<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'ultima_compania_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('ultima_compania_id')
                    ->nullable()
                    ->after('is_active')
                    ->constrained('core_companias')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'ultima_compania_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('ultima_compania_id');
            });
        }
    }
};
