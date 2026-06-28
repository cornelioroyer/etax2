<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento de las RESTAURACIONES de un respaldo lógico de compañía.
 *
 * Simétrica a `respaldos`: cada fila representa el proceso (en cola) de tomar un
 * ZIP de respaldo (subido o ya existente en disco) y volcarlo en una compañía
 * NUEVA de la MISMA instancia eTax2. La barra de progreso de la UI lee de aquí.
 *
 * La restauración SIEMPRE crea una compañía destino nueva (no se restaura sobre
 * una existente) para evitar choques de claves únicas y mezcla de datos; el id
 * de esa compañía se registra en compania_destino_id al completar con éxito.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restauraciones')) {
            return;
        }

        Schema::create('restauraciones', function (Blueprint $table) {
            $table->id();
            $table->string('usuario')->nullable();            // quien la solicitó
            $table->string('estado', 20)->default('PENDIENTE'); // PENDIENTE/PROCESANDO/COMPLETADO/FALLIDO

            // Origen del respaldo
            $table->unsignedBigInteger('respaldo_id')->nullable();   // si vino de un respaldo del sistema
            $table->string('origen')->nullable();                    // nombre del .zip de origen
            $table->unsignedBigInteger('compania_origen_id')->nullable(); // del manifest (informativo)
            $table->string('compania_origen_nombre')->nullable();
            $table->string('archivo_tmp')->nullable();               // ruta temporal del zip a procesar

            // Destino (compañía nueva creada por la restauración)
            $table->string('compania_destino_nombre')->nullable();   // nombre a crear
            $table->unsignedBigInteger('compania_destino_id')->nullable()->index(); // se llena al terminar

            // Progreso
            $table->unsignedInteger('total_tablas')->default(0);
            $table->unsignedInteger('tablas_procesadas')->default(0);
            $table->unsignedBigInteger('total_filas')->default(0);

            $table->text('reporte')->nullable();      // JSON: tablas/columnas omitidas, conteos
            $table->text('mensaje_error')->nullable();
            $table->timestamp('terminado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restauraciones');
    }
};
