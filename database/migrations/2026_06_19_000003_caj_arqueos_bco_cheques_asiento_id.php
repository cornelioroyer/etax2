<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // caj_arqueos / bco_cheques son del esquema maestro (solo pgsql); en
        // tests (SQLite) bco_cheques no existe. Guardas hasTable/hasColumn para
        // no romper la suite (no-op en dev/prod, donde la columna ya existe).
        if (Schema::hasTable('caj_arqueos') && ! Schema::hasColumn('caj_arqueos', 'asiento_id')) {
            Schema::table('caj_arqueos', function (Blueprint $table) {
                $table->unsignedBigInteger('asiento_id')->nullable()->after('estado');
                $table->foreign('asiento_id')->references('id')->on('cgl_asientos')->nullOnDelete();
            });
        }

        if (Schema::hasTable('bco_cheques') && ! Schema::hasColumn('bco_cheques', 'asiento_id')) {
            Schema::table('bco_cheques', function (Blueprint $table) {
                $table->unsignedBigInteger('asiento_id')->nullable()->after('estado');
                $table->foreign('asiento_id')->references('id')->on('cgl_asientos')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bco_cheques') && Schema::hasColumn('bco_cheques', 'asiento_id')) {
            Schema::table('bco_cheques', function (Blueprint $table) {
                $table->dropForeign(['asiento_id']);
                $table->dropColumn('asiento_id');
            });
        }

        if (Schema::hasTable('caj_arqueos') && Schema::hasColumn('caj_arqueos', 'asiento_id')) {
            Schema::table('caj_arqueos', function (Blueprint $table) {
                $table->dropForeign(['asiento_id']);
                $table->dropColumn('asiento_id');
            });
        }
    }
};
