<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caja menuda: permite adjuntar el comprobante (foto/PDF del recibo) al
 * movimiento, con el mismo patrón que la foto de factura CxP: se guarda el
 * archivo en el disco de adjuntos y se persiste su ruta + disco en la fila.
 * Guardas hasColumn: no-op donde ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caj_movimientos')) {
            return;
        }

        Schema::table('caj_movimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('caj_movimientos', 'archivo_path')) {
                $table->string('archivo_path', 255)->nullable()->after('documento_ref');
            }
            if (! Schema::hasColumn('caj_movimientos', 'archivo_disk')) {
                $table->string('archivo_disk', 30)->nullable()->after('archivo_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('caj_movimientos')) {
            return;
        }

        Schema::table('caj_movimientos', function (Blueprint $table) {
            if (Schema::hasColumn('caj_movimientos', 'archivo_disk')) {
                $table->dropColumn('archivo_disk');
            }
            if (Schema::hasColumn('caj_movimientos', 'archivo_path')) {
                $table->dropColumn('archivo_path');
            }
        });
    }
};
