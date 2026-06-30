<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asignación de rol GLOBAL: un usuario con un rol aquí lo tiene en TODAS las
 * compañías (presentes y futuras). Tabla aparte de seg_usuarios_roles porque
 * esa pertenece a Spatie/teams y su compania_id es parte de la PK (NOT NULL),
 * por lo que no admite una fila "global".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seg_usuarios_roles_globales')) {
            return;
        }

        Schema::create('seg_usuarios_roles_globales', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('rol_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'rol_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('rol_id')->references('id')->on('seg_roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seg_usuarios_roles_globales');
    }
};
