<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de actividad de usuarios: registra quién hizo qué (crear/editar/
 * eliminar de cualquier modelo, además de login/logout), con los valores
 * antes/después para poder auditar cada cambio. Tabla nueva en dev/prod; va
 * con guarda hasTable por el entorno multimáquina (no-op si ya existe).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_actividad')) {
            return;
        }

        Schema::create('audit_actividad', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('compania_id')->nullable()->index();
            $table->unsignedBigInteger('usuario_id')->nullable()->index();
            $table->string('usuario_nombre', 200)->nullable();
            $table->string('evento', 30)->index();          // created/updated/deleted/login/logout/login_fallido
            $table->string('entidad', 120)->nullable()->index(); // class basename, ej. Asiento
            $table->string('entidad_tabla', 120)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('metodo', 10)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_actividad');
    }
};
