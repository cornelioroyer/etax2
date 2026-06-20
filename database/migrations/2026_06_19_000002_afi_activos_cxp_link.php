<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afi_activos', function (Blueprint $table) {
            $table->unsignedBigInteger('cxp_detalle_id')->nullable()->after('asiento_compra_id');
            $table->foreign('cxp_detalle_id')->references('id')->on('cxp_documentos_detalle')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('afi_activos', function (Blueprint $table) {
            $table->dropForeign(['cxp_detalle_id']);
            $table->dropColumn('cxp_detalle_id');
        });
    }
};
