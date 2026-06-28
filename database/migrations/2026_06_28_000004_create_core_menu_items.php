<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menú lateral dirigido por base de datos: árbol jerárquico (N niveles vía
 * parent_id auto-referenciado) que reemplaza el array estático del Blade.
 * La visibilidad por usuario/compañía se resuelve en runtime con permiso/
 * solo_admin (no se duplica el árbol por compañía).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('core_menu_items')) {
            return;
        }

        Schema::create('core_menu_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('parent_id')->nullable();

            // Identidad / presentación.
            $table->string('clave', 100)->unique();      // slug estable (compras.ordenes)
            $table->string('etiqueta');                  // texto visible
            $table->text('icono')->nullable();           // path "d=" del SVG (solo raíces)

            // Destino.
            $table->string('ruta_nombre')->nullable();   // nombre de ruta Laravel
            $table->jsonb('ruta_params')->nullable();    // {"tipo":"PROVEEDOR"}
            $table->string('dispatch_evento', 100)->nullable(); // evento Alpine (open-help)

            // Resaltado de opción activa.
            $table->string('ruta_activa_patron')->nullable(); // patrones routeIs(), separados por espacio
            $table->string('activa_query_key', 50)->nullable(); // ej. "tipo"
            $table->string('activa_query_val', 50)->nullable(); // ej. "PROVEEDOR"

            // Visibilidad.
            $table->string('permiso', 100)->nullable();  // null = visible para todos
            $table->boolean('solo_admin')->default(false);
            $table->string('modulo', 50)->nullable();    // módulo dueño (futuro toggle por compañía)

            // Orden / estado.
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);

            // Auditoría.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'orden', 'activo']);

            // No romper el árbol: borrar un padre con hijos queda bloqueado.
            $table->foreign('parent_id')
                ->references('id')->on('core_menu_items')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_menu_items');
    }
};
