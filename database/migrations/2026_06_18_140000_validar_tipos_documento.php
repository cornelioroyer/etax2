<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 1 de la integridad referencial (antes de cualquier FK): verifica que
 * todo tipo_documento usado en las tablas reales exista en el maestro
 * core_tipos_documento, y rellena (backfill) los que falten con valores por
 * defecto razonables.
 *
 * Así, cuando se aplique la constraint (migración siguiente), no puede fallar
 * por filas con tipos no catalogados. Cualquier tipo inesperado queda
 * registrado en el log para revisión manual.
 *
 * Idempotente y de solo-lectura sobre las tablas de documentos: segura en la
 * BD compartida dev/prod.
 */
return new class extends Migration
{
    /** tabla de documentos => auxiliar del submayor */
    private array $fuentes = [
        'cxc_documentos'  => 'CXC',
        'ventas_facturas' => 'CXC',
        'cxp_documentos'  => 'CXP',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('core_tipos_documento')) {
            return; // el maestro aún no existe; nada que validar
        }

        // (auxiliar, tipo_documento) ya catalogados
        $catalogados = DB::table('core_tipos_documento')
            ->get(['auxiliar', 'tipo_documento'])
            ->map(fn ($r) => $r->auxiliar.'|'.$r->tipo_documento)
            ->flip();

        $faltantes = [];
        $ahora = now();

        foreach ($this->fuentes as $tabla => $aux) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'tipo_documento')) {
                continue;
            }

            $tipos = DB::table($tabla)
                ->select('tipo_documento')
                ->whereNotNull('tipo_documento')
                ->distinct()
                ->pluck('tipo_documento');

            foreach ($tipos as $tipo) {
                $clave = $aux.'|'.$tipo;
                if (isset($catalogados[$clave]) || isset($faltantes[$clave])) {
                    continue;
                }

                // Heurística conservadora para un tipo no estándar: tratarlo como
                // cargo (+1) cobrable. Se registra para revisión.
                $faltantes[$clave] = [
                    'auxiliar'       => $aux,
                    'tipo_documento' => $tipo,
                    'descripcion'    => $tipo,
                    'signo'          => 1,
                    'naturaleza'     => 'CARGO',
                    'cobrable'       => true,
                    'prefijo'        => null,
                    'reversa'        => false,
                    'created_at'     => $ahora,
                    'updated_at'     => $ahora,
                ];

                Log::warning("core_tipos_documento: tipo no catalogado backfilleado por defecto (CARGO/+1)", [
                    'auxiliar' => $aux, 'tipo_documento' => $tipo, 'origen' => $tabla,
                ]);
            }
        }

        if ($faltantes !== []) {
            DB::table('core_tipos_documento')->insert(array_values($faltantes));
        }
    }

    public function down(): void
    {
        // No revertimos el backfill: borrar filas del maestro podría dejar
        // documentos sin su tipo. Es seguro dejarlas.
    }
};
