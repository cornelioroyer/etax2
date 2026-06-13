<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prh_edificios')) {
            Schema::create('prh_edificios', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 30);
                $table->string('nombre', 200);
                $table->text('direccion')->nullable();
                $table->text('descripcion')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('prh_propietarios')) {
            Schema::create('prh_propietarios', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('identificacion', 50)->nullable();
                $table->string('nombre', 300);
                $table->string('email', 200)->nullable();
                $table->string('telefono', 50)->nullable();
                $table->text('direccion')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('prh_unidades')) {
            Schema::create('prh_unidades', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('edificio_id');
                $table->string('codigo', 30);
                $table->string('numero', 50);
                $table->string('tipo', 30)->default('APARTAMENTO');
                $table->string('piso', 20)->nullable();
                $table->decimal('area_m2', 10, 2)->nullable();
                $table->decimal('coeficiente', 8, 6)->default(0);
                $table->unsignedBigInteger('propietario_id')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('prh_tipos_cuota')) {
            Schema::create('prh_tipos_cuota', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->string('codigo', 30);
                $table->string('nombre', 150);
                $table->text('descripcion')->nullable();
                $table->decimal('monto_base', 14, 2)->default(0);
                $table->string('periodicidad', 20)->default('MENSUAL');
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('prh_cuotas')) {
            Schema::create('prh_cuotas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id');
                $table->unsignedBigInteger('unidad_id');
                $table->unsignedBigInteger('tipo_cuota_id');
                $table->char('periodo', 7);
                $table->date('fecha_emision');
                $table->date('fecha_vencimiento');
                $table->decimal('monto', 14, 2);
                $table->decimal('monto_pagado', 14, 2)->default(0);
                $table->text('concepto')->nullable();
                $table->string('estado', 20)->default('PENDIENTE');
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }

        if (! Schema::hasTable('prh_pagos')) {
            Schema::create('prh_pagos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cuota_id');
                $table->date('fecha_pago');
                $table->decimal('monto', 14, 2);
                $table->string('referencia', 150)->nullable();
                $table->string('forma_pago', 30)->default('EFECTIVO');
                $table->text('notas')->nullable();
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prh_pagos');
        Schema::dropIfExists('prh_cuotas');
        Schema::dropIfExists('prh_tipos_cuota');
        Schema::dropIfExists('prh_unidades');
        Schema::dropIfExists('prh_propietarios');
        Schema::dropIfExists('prh_edificios');
    }
};
