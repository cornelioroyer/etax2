<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En dev/prod audit_reaperturas ya existe (esquema maestro); en tests
     * (SQLite) hay que crearla para cubrir la reapertura de períodos.
     * Va con guarda hasTable (no-op en dev/prod).
     */
    public function up(): void
    {
        if (! Schema::hasTable('audit_reaperturas')) {
            Schema::create('audit_reaperturas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('periodo_id')->nullable();
                $table->text('motivo')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_reaperturas');
    }
};
