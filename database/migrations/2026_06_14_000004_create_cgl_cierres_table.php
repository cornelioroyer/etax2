<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En dev/prod cgl_cierres ya existe (esquema maestro); en tests
     * (SQLite) hay que crearla para cubrir el cierre/reapertura de
     * períodos. Va con guarda hasTable (no-op en dev/prod).
     */
    public function up(): void
    {
        if (! Schema::hasTable('cgl_cierres')) {
            Schema::create('cgl_cierres', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('periodo_id');
                $table->string('estado', 30)->default('PENDIENTE');
                $table->unsignedBigInteger('cerrado_por')->nullable();
                $table->timestampTz('fecha_cierre')->nullable();
                $table->text('observacion')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cgl_cierres');
    }
};
