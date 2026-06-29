<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soporte para REVERSAR un movimiento manual de inventario mediante una
 * transacción de compensación (en vez de cambiar el estado a ANULADO):
 *  - inv_movimientos.reversa_de_id: enlaza el movimiento de reverso con su original
 *    (deja la pista y permite bloquear el doble reverso).
 *  - inv_movimientos_detalle.cantidad_anterior / costo_anterior: snapshot de la
 *    existencia ANTES de aplicar cada línea, necesario para reversar un AJUSTE
 *    (que fija valores absolutos y no guarda el estado previo).
 * Aditiva y reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inv_movimientos') && ! Schema::hasColumn('inv_movimientos', 'reversa_de_id')) {
            Schema::table('inv_movimientos', function (Blueprint $table) {
                $table->unsignedBigInteger('reversa_de_id')->nullable()->after('asiento_id');
                $table->index('reversa_de_id');
            });
        }

        if (Schema::hasTable('inv_movimientos_detalle')) {
            Schema::table('inv_movimientos_detalle', function (Blueprint $table) {
                if (! Schema::hasColumn('inv_movimientos_detalle', 'cantidad_anterior')) {
                    $table->decimal('cantidad_anterior', 18, 4)->nullable()->after('total');
                }
                if (! Schema::hasColumn('inv_movimientos_detalle', 'costo_anterior')) {
                    $table->decimal('costo_anterior', 18, 4)->nullable()->after('cantidad_anterior');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inv_movimientos') && Schema::hasColumn('inv_movimientos', 'reversa_de_id')) {
            Schema::table('inv_movimientos', function (Blueprint $table) {
                $table->dropColumn('reversa_de_id');
            });
        }

        foreach (['cantidad_anterior', 'costo_anterior'] as $col) {
            if (Schema::hasTable('inv_movimientos_detalle') && Schema::hasColumn('inv_movimientos_detalle', $col)) {
                Schema::table('inv_movimientos_detalle', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
