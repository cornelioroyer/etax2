<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('banco_cuentas')) {
            Schema::create('banco_cuentas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('banco_nombre', 100);
                $table->string('numero_cuenta', 50);
                $table->string('tipo', 20)->default('CORRIENTE'); // CORRIENTE, AHORROS, INVERSION
                $table->string('moneda', 10)->default('PAB');      // PAB, USD
                $table->unsignedBigInteger('cuenta_contable_id')->nullable();
                $table->decimal('saldo_inicial', 18, 2)->default(0);
                $table->boolean('activa')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
                $table->unique(['compania_id', 'numero_cuenta']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('banco_cuentas');
    }
};
