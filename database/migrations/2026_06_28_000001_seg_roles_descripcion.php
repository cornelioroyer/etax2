<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etiqueta amigable opcional para los roles (catálogo administrable).
 * Aditiva y reversible: el nombre técnico (seg_roles.name) sigue siendo la clave;
 * `descripcion` es solo para mostrar (p.ej. "Cajero — registra cobros y caja").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('seg_roles', 'descripcion')) {
            Schema::table('seg_roles', function (Blueprint $table) {
                $table->string('descripcion')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('seg_roles', 'descripcion')) {
            Schema::table('seg_roles', function (Blueprint $table) {
                $table->dropColumn('descripcion');
            });
        }
    }
};
