<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento de las importaciones de compras (Excel DGI) procesadas en
 * segundo plano por cola. La barra de progreso de la UI lee de esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cxp_importaciones')) {
            return;
        }

        Schema::create('cxp_importaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('compania_id')->index();
            $table->string('usuario')->nullable();
            $table->string('archivo');
            $table->string('ruta')->nullable();
            $table->string('estado', 20)->default('PENDIENTE'); // PENDIENTE, PROCESANDO, COMPLETADO, FALLIDO
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('procesadas')->default(0);
            $table->unsignedInteger('creadas')->default(0);
            $table->unsignedInteger('con_detalle')->default(0);
            $table->unsignedInteger('omitidas')->default(0);
            $table->text('errores')->nullable();
            $table->text('mensaje_error')->nullable();
            $table->timestamp('terminado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cxp_importaciones');
    }
};
