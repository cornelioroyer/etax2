<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento de los respaldos lógicos por compañía generados en segundo
 * plano por cola. Cada fila representa un ZIP descargable que contiene los
 * datos de UNA compañía (filtrados por compania_id). La barra de progreso de
 * la UI lee de esta tabla, igual que cxp_importaciones / ventas_importaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('respaldos')) {
            return;
        }

        Schema::create('respaldos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('compania_id')->index();
            $table->string('usuario')->nullable();           // nombre/email de quien lo solicitó
            $table->string('estado', 20)->default('PENDIENTE'); // PENDIENTE, PROCESANDO, COMPLETADO, FALLIDO
            $table->string('archivo')->nullable();           // nombre del .zip
            $table->string('ruta')->nullable();              // ruta en el disco (no pública)
            $table->string('disco', 30)->nullable();         // disco de filesystems donde quedó
            $table->unsignedBigInteger('bytes')->default(0); // tamaño del zip
            $table->unsignedInteger('total_tablas')->default(0);
            $table->unsignedInteger('tablas_procesadas')->default(0);
            $table->unsignedBigInteger('total_filas')->default(0);
            $table->string('checksum', 64)->nullable();      // sha256 del zip
            $table->text('mensaje_error')->nullable();
            $table->timestamp('terminado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respaldos');
    }
};
