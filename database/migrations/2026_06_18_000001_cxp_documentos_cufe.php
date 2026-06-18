<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda el CUFE completo (Código Único de Factura Electrónica, ~66 chars)
 * de la factura electrónica recibida, para poder reconsultarla en la DGI
 * y como clave de deduplicación global de las compras importadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cxp_documentos', 'cufe')) {
            Schema::table('cxp_documentos', function (Blueprint $table) {
                $table->string('cufe', 120)->nullable()->after('numero');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cxp_documentos', 'cufe')) {
            Schema::table('cxp_documentos', function (Blueprint $table) {
                $table->dropColumn('cufe');
            });
        }
    }
};
