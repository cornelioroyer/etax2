<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla inv_kardex nunca fue poblada por ningún código ni trigger: el reporte
 * de Kardex ahora se deriva en vivo de inv_movimientos (fuente única de verdad,
 * ver InvKardexController). Se elimina la tabla huérfana. El down() recrea su
 * estructura original (tal como existía en la BD, fuera de migraciones) por si
 * se quisiera revertir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('inv_kardex');
    }

    public function down(): void
    {
        Schema::create('inv_kardex', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('compania_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('almacen_id');
            $table->date('fecha');
            $table->string('tipo_movimiento');
            $table->string('documento_origen')->nullable();
            $table->unsignedBigInteger('documento_id')->nullable();
            $table->decimal('entrada_cantidad', 18, 4)->default(0);
            $table->decimal('entrada_costo', 18, 4)->default(0);
            $table->decimal('salida_cantidad', 18, 4)->default(0);
            $table->decimal('salida_costo', 18, 4)->default(0);
            $table->decimal('saldo_cantidad', 18, 4)->default(0);
            $table->decimal('saldo_costo', 18, 4)->default(0);
            $table->decimal('costo_promedio', 18, 4)->default(0);
            $table->unsignedBigInteger('asiento_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->string('created_by')->nullable();
            $table->timestampTz('updated_at')->useCurrent();
            $table->string('updated_by')->nullable();

            $table->index('almacen_id', 'idx_inv_kardex_almacen_id');
            $table->index('asiento_id', 'idx_inv_kardex_asiento_id');
            $table->index('compania_id', 'idx_inv_kardex_compania_id');
            $table->index(['item_id', 'fecha'], 'idx_inv_kardex_item_fecha');
        });
    }
};
