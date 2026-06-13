<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * En dev/prod la tabla tax_impuestos ya existe (esquema maestro de la
     * suite planilla); en tests (SQLite) hay que crearla. Va con guarda
     * hasTable y debe correr antes del seed 2026_06_14_000001.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tax_impuestos')) {
            Schema::create('tax_impuestos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compania_id')->nullable();
                $table->string('codigo', 30);
                $table->string('nombre', 100);
                $table->string('tipo', 30);
                $table->decimal('porcentaje', 8, 4)->default(0);
                $table->unsignedBigInteger('cuenta_debito_id')->nullable();
                $table->unsignedBigInteger('cuenta_credito_id')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->string('created_by', 200)->nullable();
                $table->string('updated_by', 200)->nullable();

                $table->unique(['compania_id', 'codigo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_impuestos');
    }
};
