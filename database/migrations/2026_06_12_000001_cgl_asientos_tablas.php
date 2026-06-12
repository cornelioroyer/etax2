<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En dev/prod las tablas cgl_* ya existen (esquema maestro con
     * triggers de control contable); en tests (SQLite) hay que crearlas.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cgl_tipos_cuenta')) {
            Schema::create('cgl_tipos_cuenta', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 30)->unique();
                $table->string('nombre', 100);
                $table->string('naturaleza', 10);
                $table->string('seccion', 30)->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('cgl_cuentas')) {
            Schema::create('cgl_cuentas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 50);
                $table->string('nombre', 200);
                $table->unsignedBigInteger('cuenta_padre_id')->nullable();
                $table->integer('nivel')->default(1);
                $table->unsignedBigInteger('tipo_cuenta_id')->nullable();
                $table->string('naturaleza', 10)->default('DEBITO');
                $table->boolean('permite_movimiento')->default(true);
                $table->boolean('requiere_contacto')->default(false);
                $table->boolean('requiere_centro_costo')->default(false);
                $table->boolean('requiere_proyecto')->default(false);
                $table->boolean('conciliable')->default(false);
                $table->boolean('activa')->default(true);
                $table->string('renglon_isr', 20)->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
                $table->unique(['compania_id', 'codigo']);
            });
        }

        if (! Schema::hasTable('cgl_diarios')) {
            Schema::create('cgl_diarios', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 30);
                $table->string('nombre', 100);
                $table->string('tipo_diario', 30);
                $table->unsignedBigInteger('cuenta_default_id')->nullable();
                $table->boolean('requiere_aprobacion')->default(false);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
                $table->unique(['compania_id', 'codigo']);
            });
        }

        if (! Schema::hasTable('cgl_periodos')) {
            Schema::create('cgl_periodos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->integer('anio');
                $table->integer('mes');
                $table->date('fecha_inicio');
                $table->date('fecha_fin');
                $table->string('estado', 30)->default('ABIERTO');
                $table->unsignedBigInteger('cerrado_por')->nullable();
                $table->timestampTz('fecha_cierre')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
                $table->unique(['compania_id', 'anio', 'mes']);
            });
        }

        if (! Schema::hasTable('cgl_asientos')) {
            Schema::create('cgl_asientos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('periodo_id')->nullable();
                $table->unsignedBigInteger('diario_id')->nullable();
                $table->string('numero', 50);
                $table->date('fecha');
                $table->text('descripcion')->nullable();
                $table->string('referencia', 100)->nullable();
                $table->string('estado', 30)->default('BORRADOR');
                $table->string('origen_modulo', 50)->nullable();
                $table->string('origen_tabla', 100)->nullable();
                $table->unsignedBigInteger('origen_id')->nullable();
                $table->decimal('total_debito', 18, 2)->default(0);
                $table->decimal('total_credito', 18, 2)->default(0);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->unsignedBigInteger('posteado_por')->nullable();
                $table->timestampTz('fecha_posteo')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
                $table->unique(['compania_id', 'numero']);
            });
        }

        if (! Schema::hasTable('cgl_asientos_detalle')) {
            Schema::create('cgl_asientos_detalle', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asiento_id');
                $table->integer('linea');
                $table->unsignedBigInteger('cuenta_id');
                $table->unsignedBigInteger('contacto_id')->nullable();
                $table->text('descripcion')->nullable();
                $table->decimal('debito', 18, 2)->default(0);
                $table->decimal('credito', 18, 2)->default(0);
                $table->unsignedBigInteger('moneda_id')->nullable();
                $table->decimal('tasa_cambio', 18, 8)->default(1);
                $table->decimal('debito_local', 18, 2)->default(0);
                $table->decimal('credito_local', 18, 2)->default(0);
                $table->boolean('conciliado')->default(false);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
                $table->unique(['asiento_id', 'linea']);
            });
        }
        if (! Schema::hasTable('cgl_saldos')) {
            Schema::create('cgl_saldos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('periodo_id');
                $table->unsignedBigInteger('cuenta_id');
                $table->unsignedBigInteger('contacto_id')->nullable();
                $table->unsignedBigInteger('centro_costo_id')->nullable();
                $table->decimal('debito', 18, 2)->default(0);
                $table->decimal('credito', 18, 2)->default(0);
                $table->decimal('saldo', 18, 2)->default(0);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Solo aplica a tests (SQLite); en dev/prod las tablas son del
        // esquema maestro y no se tocan.
    }
};
