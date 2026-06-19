<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el tipo REEMBOLSO (DGI 09) al maestro core_tipos_documento en el
 * submayor CXC. Va en migración propia —y no solo en el seed de la migración
 * que crea la tabla— porque en la BD compartida dev/prod esa migración ya
 * pudo haberse ejecutado, y un cambio a su seed no se vuelve a correr.
 *
 * Idempotente (upsert): segura de re-ejecutar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_tipos_documento')) {
            return; // el maestro aún no existe; su seed ya incluye REEMBOLSO
        }

        $ahora = now();

        DB::table('core_tipos_documento')->upsert(
            [[
                'auxiliar'       => 'CXC',
                'tipo_documento' => 'REEMBOLSO',
                'descripcion'    => 'Reembolso',
                'signo'          => 1,
                'naturaleza'     => 'CARGO',
                'cobrable'       => true,
                'prefijo'        => 'RE-',
                'reversa'        => false,
                'created_at'     => $ahora,
                'updated_at'     => $ahora,
            ]],
            ['auxiliar', 'tipo_documento'],
            ['descripcion', 'signo', 'naturaleza', 'cobrable', 'prefijo', 'reversa', 'updated_at']
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('core_tipos_documento')) {
            DB::table('core_tipos_documento')
                ->where('auxiliar', 'CXC')
                ->where('tipo_documento', 'REEMBOLSO')
                ->delete();
        }
    }
};
