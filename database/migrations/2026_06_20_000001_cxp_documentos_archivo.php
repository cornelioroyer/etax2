<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Respaldo documental de la CxP: ruta del archivo (foto de la factura subida
 * por el flujo de IA, o PDF oficial descargado de la DGI) y el disco donde vive
 * (normalmente s3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cxp_documentos', function (Blueprint $table) {
            if (! Schema::hasColumn('cxp_documentos', 'archivo_path')) {
                $table->string('archivo_path', 1024)->nullable()->after('cufe');
            }
            if (! Schema::hasColumn('cxp_documentos', 'archivo_disk')) {
                $table->string('archivo_disk', 30)->nullable()->after('archivo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cxp_documentos', function (Blueprint $table) {
            foreach (['archivo_path', 'archivo_disk'] as $col) {
                if (Schema::hasColumn('cxp_documentos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
