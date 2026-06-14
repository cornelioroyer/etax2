<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Presupuestos. Un escenario agrupa presupuestos (p. ej. "Base 2026",
     * "Conservador"); cada presupuesto es de un año y su detalle lleva una
     * línea por cuenta contable con 12 montos mensuales. Cada bloque va con
     * guarda hasTable para ser idempotente y no-op si la tabla ya existe.
     */
    public function up(): void
    {
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
                $table->unsignedBigInteger('escenario_id');
                $table->string('nombre', 150);
                $table->smallInteger('anio');
                $table->string('estado', 30)->default('BORRADOR');
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('budget_presupuestos_detalle')) {
            Schema::create('budget_presupuestos_detalle', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('presupuesto_id');
                $table->unsignedBigInteger('cuenta_id');
                for ($mes = 1; $mes <= 12; $mes++) {
                    $table->decimal('monto_' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT), 18, 2)->default(0);
                }
                $table->decimal('monto_total', 18, 2)->default(0);
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
    }
};
