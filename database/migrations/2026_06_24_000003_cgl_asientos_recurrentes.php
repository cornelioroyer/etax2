<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plantillas de asientos recurrentes (alquiler, depreciación lineal,
     * amortizaciones, servicios fijos). NO son asientos: son moldes que el
     * generador convierte en asientos cgl_* BORRADOR en cada vencimiento,
     * reutilizando el mismo motor (cuadre, cuentas de control, período,
     * triggers de saldos). Tablas nuevas y aditivas.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cgl_asientos_recurrentes')) {
            Schema::create('cgl_asientos_recurrentes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('diario_id')->nullable();
                $table->string('nombre', 200);
                $table->text('descripcion')->nullable();
                $table->string('referencia', 100)->nullable();
                // SEMANAL, QUINCENAL, MENSUAL, BIMESTRAL, TRIMESTRAL, SEMESTRAL, ANUAL
                $table->string('frecuencia', 20);
                $table->date('fecha_inicio');
                $table->date('fecha_fin')->nullable();
                $table->integer('ocurrencias_max')->nullable();
                $table->integer('ocurrencias_generadas')->default(0);
                $table->date('proxima_fecha');
                $table->date('ultima_generacion')->nullable();
                // ACTIVA, PAUSADA, FINALIZADA
                $table->string('estado', 20)->default('ACTIVA');
                $table->decimal('total_debito', 18, 2)->default(0);
                $table->decimal('total_credito', 18, 2)->default(0);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->index(['compania_id', 'estado']);
                $table->index(['estado', 'proxima_fecha']);
            });
        }

        if (! Schema::hasTable('cgl_asientos_recurrentes_detalle')) {
            Schema::create('cgl_asientos_recurrentes_detalle', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('recurrente_id');
                $table->integer('linea');
                $table->unsignedBigInteger('cuenta_id');
                $table->unsignedBigInteger('contacto_id')->nullable();
                $table->text('descripcion')->nullable();
                $table->decimal('debito', 18, 2)->default(0);
                $table->decimal('credito', 18, 2)->default(0);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->unique(['recurrente_id', 'linea']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cgl_asientos_recurrentes_detalle');
        Schema::dropIfExists('cgl_asientos_recurrentes');
    }
};
