<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En dev/prod las tablas bco_* ya existen (creadas fuera de migración en
     * su momento); en tests (SQLite) hay que crearlas. Las guardas hasTable()
     * hacen que esta migración sea un no-op seguro sobre los entornos reales.
     */
    public function up(): void
    {
        if (! Schema::hasTable('bco_bancos')) {
            Schema::create('bco_bancos', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 30)->nullable();
                $table->string('nombre', 150);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('bco_cuentas')) {
            Schema::create('bco_cuentas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('banco_id');
                $table->unsignedBigInteger('cuenta_contable_id')->nullable();
                $table->string('numero_cuenta', 100);
                $table->string('nombre', 150);
                $table->string('tipo_cuenta', 50)->nullable();
                $table->unsignedBigInteger('moneda_id')->nullable();
                $table->decimal('saldo_inicial', 18, 2)->default(0);
                $table->boolean('activa')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('bco_movimientos')) {
            Schema::create('bco_movimientos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('cuenta_bancaria_id');
                $table->date('fecha');
                $table->string('tipo_movimiento', 30);
                $table->text('descripcion')->nullable();
                $table->string('referencia', 100)->nullable();
                $table->decimal('debito', 18, 2)->default(0);
                $table->decimal('credito', 18, 2)->default(0);
                $table->decimal('saldo', 18, 2)->nullable();
                $table->unsignedBigInteger('contacto_id')->nullable();
                $table->boolean('conciliado')->default(false);
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->string('documento_origen', 100)->nullable();
                $table->unsignedBigInteger('documento_id')->nullable();
                $table->unsignedBigInteger('adjunto_id')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('bco_depositos')) {
            Schema::create('bco_depositos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('cuenta_bancaria_id');
                $table->date('fecha');
                $table->string('referencia', 100)->nullable();
                $table->decimal('monto', 18, 2);
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->unsignedBigInteger('adjunto_id')->nullable();
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
