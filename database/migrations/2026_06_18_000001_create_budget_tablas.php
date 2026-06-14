<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Presupuestos (Budget). Las tablas budget_* viven en el esquema maestro
     * PostgreSQL (dev y prod); en tests (SQLite) hay que crearlas. Cada bloque
     * va con guarda hasTable (no-op donde ya existen). El detalle es por cuenta
     * y periodo, con dimensiones analíticas y comparación presupuestado/real.
     */
    public function up(): void
    {
        if (! Schema::hasTable('budget_versiones')) {
            Schema::create('budget_versiones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('nombre', 100);
                $table->boolean('activa')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
                $table->unique(['compania_id', 'nombre']);
            });
        }

        if (! Schema::hasTable('budget_escenarios')) {
            Schema::create('budget_escenarios', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('nombre', 150);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('budget_presupuestos')) {
            Schema::create('budget_presupuestos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('nombre', 150);
                $table->integer('anio');
                $table->unsignedBigInteger('version_id')->nullable();
                $table->unsignedBigInteger('escenario_id')->nullable();
                $table->string('estado', 30)->default('BORRADOR');
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
                $table->unique(['compania_id', 'nombre', 'anio']);
            });
        }

        if (! Schema::hasTable('budget_presupuestos_detalle')) {
            Schema::create('budget_presupuestos_detalle', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('presupuesto_id');
                $table->unsignedBigInteger('periodo_id')->nullable();
                $table->unsignedBigInteger('cuenta_id');
                $table->unsignedBigInteger('centro_costo_id')->nullable();
                $table->unsignedBigInteger('departamento_id')->nullable();
                $table->unsignedBigInteger('proyecto_id')->nullable();
                $table->decimal('monto_presupuestado', 18, 2)->default(0);
                $table->decimal('monto_real', 18, 2)->default(0);
                $table->decimal('variacion', 18, 2)->default(0);
                $table->decimal('porcentaje_variacion', 8, 4)->default(0);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_presupuestos_detalle');
        Schema::dropIfExists('budget_presupuestos');
        Schema::dropIfExists('budget_escenarios');
        Schema::dropIfExists('budget_versiones');
    }
};
