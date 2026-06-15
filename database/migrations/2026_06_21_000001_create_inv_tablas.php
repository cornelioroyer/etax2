<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En dev/prod las tablas de inventario y de productos ya existen (esquema
     * maestro, creadas fuera de migración); en tests (SQLite) hay que crearlas.
     * Las guardas hasTable() hacen esta migración un no-op en entornos reales.
     *
     * Solo se incluyen las tablas necesarias para los reportes/pruebas que las
     * consultan (cuadre de auxiliares); el esquema rico de inventario sigue
     * viviendo en la BD maestra.
     */
    public function up(): void
    {
        if (! Schema::hasTable('item_productos_servicios')) {
            Schema::create('item_productos_servicios', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 50);
                $table->string('nombre', 200);
                $table->text('descripcion')->nullable();
                $table->string('tipo', 30)->default('SERVICIO');
                $table->unsignedBigInteger('categoria_id')->nullable();
                $table->unsignedBigInteger('unidad_medida_id')->nullable();
                $table->decimal('precio_venta', 18, 4)->default(0);
                $table->decimal('costo', 18, 4)->default(0);
                $table->unsignedBigInteger('cuenta_ingreso_id')->nullable();
                $table->unsignedBigInteger('cuenta_gasto_id')->nullable();
                $table->unsignedBigInteger('cuenta_inventario_id')->nullable();
                $table->unsignedBigInteger('cuenta_costo_venta_id')->nullable();
                $table->unsignedBigInteger('impuesto_id')->nullable();
                $table->json('extra')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('inv_almacenes')) {
            Schema::create('inv_almacenes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 30);
                $table->string('nombre', 150);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('inv_existencias')) {
            Schema::create('inv_existencias', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('almacen_id');
                $table->decimal('cantidad', 18, 4)->default(0);
                $table->decimal('costo_promedio', 18, 4)->default(0);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Solo aplica a tests (SQLite); en dev/prod las tablas no se tocan.
    }
};
