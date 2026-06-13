<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Caja menuda (caja chica). Las tablas caj_* viven en el esquema
     * maestro PostgreSQL; en tests (SQLite) hay que crearlas. Cada bloque
     * va con guarda hasTable (no-op en prod). OJO: caj_movimientos NO
     * existía en dev (sólo en prod), así que aquí también se crea en dev.
     */
    public function up(): void
    {
        if (! Schema::hasTable('caj_cajas')) {
            Schema::create('caj_cajas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 30);
                $table->string('nombre', 100);
                $table->unsignedBigInteger('cuenta_contable_id')->nullable();
                $table->unsignedBigInteger('responsable_id')->nullable();
                $table->boolean('activa')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('caj_movimientos')) {
            Schema::create('caj_movimientos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('caja_id');
                $table->date('fecha');
                $table->string('tipo_movimiento', 30);
                $table->string('beneficiario', 200)->nullable();
                $table->text('descripcion')->nullable();
                $table->decimal('monto', 18, 2);
                $table->unsignedBigInteger('cuenta_contable_id')->nullable();
                $table->unsignedBigInteger('centro_costo_id')->nullable();
                $table->unsignedBigInteger('proyecto_id')->nullable();
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->unsignedBigInteger('adjunto_id')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('caj_vales')) {
            Schema::create('caj_vales', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('caja_id');
                $table->date('fecha');
                $table->string('beneficiario', 200);
                $table->decimal('monto', 18, 2);
                $table->text('motivo')->nullable();
                $table->unsignedBigInteger('adjunto_id')->nullable();
                $table->string('estado', 30)->default('PENDIENTE');
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('caj_reembolsos')) {
            Schema::create('caj_reembolsos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('caja_id');
                $table->date('fecha');
                $table->decimal('monto', 18, 2);
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->unsignedBigInteger('adjunto_id')->nullable();
                $table->string('estado', 30)->default('APLICADO');
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('caj_arqueos')) {
            Schema::create('caj_arqueos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('caja_id');
                $table->date('fecha');
                $table->decimal('saldo_sistema', 18, 2)->default(0);
                $table->decimal('saldo_fisico', 18, 2)->default(0);
                $table->decimal('diferencia', 18, 2)->default(0);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->string('estado', 30)->default('CERRADO');
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('caj_arqueos_detalle')) {
            Schema::create('caj_arqueos_detalle', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('arqueo_id');
                $table->decimal('denominacion', 18, 2);
                $table->integer('cantidad');
                $table->decimal('total', 18, 2);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('caj_arqueos_detalle');
        Schema::dropIfExists('caj_arqueos');
        Schema::dropIfExists('caj_reembolsos');
        Schema::dropIfExists('caj_vales');
        Schema::dropIfExists('caj_movimientos');
        Schema::dropIfExists('caj_cajas');
    }
};
