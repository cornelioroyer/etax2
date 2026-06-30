<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bandera "solo lectura" por compañía.
 *
 * Sustituye el bloqueo hardcodeado de la compañía 1 (COMPANIA_SISTEMA) por una
 * marca configurable: una compañía con solo_lectura=true impide toda acción de
 * escritura a los usuarios que no son super_admin (el Gate solo permite las
 * acciones de lectura). Por defecto NINGUNA compañía es de solo lectura, así
 * que los permisos aplican uniformemente en todas (incluida ETAX 2).
 *
 * Aditiva e idempotente (guarda hasColumn). Reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('core_companias', 'solo_lectura')) {
            Schema::table('core_companias', function (Blueprint $table) {
                $table->boolean('solo_lectura')->default(false)->after('activa');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('core_companias', 'solo_lectura')) {
            Schema::table('core_companias', function (Blueprint $table) {
                $table->dropColumn('solo_lectura');
            });
        }
    }
};
