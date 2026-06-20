<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caj_arqueos', function (Blueprint $table) {
            $table->unsignedBigInteger('asiento_id')->nullable()->after('estado');
            $table->foreign('asiento_id')->references('id')->on('cgl_asientos')->nullOnDelete();
        });

        Schema::table('bco_cheques', function (Blueprint $table) {
            $table->unsignedBigInteger('asiento_id')->nullable()->after('estado');
            $table->foreign('asiento_id')->references('id')->on('cgl_asientos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bco_cheques', function (Blueprint $table) {
            $table->dropForeign(['asiento_id']);
            $table->dropColumn('asiento_id');
        });

        Schema::table('caj_arqueos', function (Blueprint $table) {
            $table->dropForeign(['asiento_id']);
            $table->dropColumn('asiento_id');
        });
    }
};
