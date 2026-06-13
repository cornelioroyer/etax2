<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Activos fijos. Las tablas afi_* viven en el esquema maestro PostgreSQL;
     * en tests (SQLite) hay que crearlas. Cada bloque va con guarda hasTable
     * (no-op en dev/prod donde las tablas ya existen).
     */
    public function up(): void
    {
        if (! Schema::hasTable('afi_categorias')) {
            Schema::create('afi_categorias', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 30);
                $table->string('nombre', 150);
                $table->integer('vida_util_meses_default')->nullable();
                $table->unsignedBigInteger('cuenta_activo_id')->nullable();
                $table->unsignedBigInteger('cuenta_depreciacion_acum_id')->nullable();
                $table->unsignedBigInteger('cuenta_gasto_depreciacion_id')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('afi_ubicaciones')) {
            Schema::create('afi_ubicaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 30);
                $table->string('nombre', 150);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('afi_activos')) {
            Schema::create('afi_activos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 50);
                $table->text('descripcion');
                $table->unsignedBigInteger('categoria_id')->nullable();
                $table->unsignedBigInteger('ubicacion_id')->nullable();
                $table->date('fecha_compra')->nullable();
                $table->date('fecha_inicio_depreciacion')->nullable();
                $table->decimal('valor_compra', 18, 2)->default(0);
                $table->decimal('valor_residual', 18, 2)->default(0);
                $table->integer('vida_util_meses')->default(0);
                $table->string('metodo_depreciacion', 50)->default('LINEA_RECTA');
                $table->unsignedBigInteger('cuenta_activo_id')->nullable();
                $table->unsignedBigInteger('cuenta_depreciacion_acum_id')->nullable();
                $table->unsignedBigInteger('cuenta_gasto_depreciacion_id')->nullable();
                $table->string('estado', 30)->default('ACTIVO');
                $table->unsignedBigInteger('asiento_compra_id')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('afi_depreciaciones')) {
            Schema::create('afi_depreciaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('activo_id');
                $table->unsignedBigInteger('periodo_id')->nullable();
                $table->date('fecha');
                $table->decimal('monto', 18, 2);
                $table->decimal('acumulado', 18, 2);
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->string('estado', 30)->default('POSTEADA');
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('afi_bajas')) {
            Schema::create('afi_bajas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('activo_id');
                $table->date('fecha');
                $table->text('motivo')->nullable();
                $table->decimal('valor_baja', 18, 2)->default(0);
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('afi_movimientos')) {
            Schema::create('afi_movimientos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('activo_id');
                $table->date('fecha');
                $table->string('tipo_movimiento', 50);
                $table->text('descripcion')->nullable();
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('afi_revaluaciones')) {
            Schema::create('afi_revaluaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('activo_id');
                $table->date('fecha');
                $table->decimal('valor_anterior', 18, 2);
                $table->decimal('valor_nuevo', 18, 2);
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('afi_revaluaciones');
        Schema::dropIfExists('afi_movimientos');
        Schema::dropIfExists('afi_bajas');
        Schema::dropIfExists('afi_depreciaciones');
        Schema::dropIfExists('afi_activos');
        Schema::dropIfExists('afi_ubicaciones');
        Schema::dropIfExists('afi_categorias');
    }
};
