<?php

namespace App\Services;

use App\Models\CuentaContable;
use App\Models\TipoCuenta;
use Illuminate\Support\Facades\DB;

class PlantillaCuentas
{
    /**
     * Plantilla aplicada por defecto al crear una compañía:
     * plan de cuentas Formulario 2 DGI (ISR Panamá).
     */
    public const POR_DEFECTO = 'PA_ISR';

    /**
     * Copia una plantilla de cuentas (core_plantillas_cuentas) a la compañía
     * y configura las cuentas por defecto (core_cuentas_default).
     *
     * No hace nada si la plantilla no existe o si la compañía ya tiene plan de
     * cuentas. Devuelve el número de cuentas creadas.
     */
    public function aplicar(int $companiaId, string $codigo, string $usuario): int
    {
        if (CuentaContable::where('compania_id', $companiaId)->exists()) {
            return 0;
        }

        $plantillaId = DB::table('core_plantillas_cuentas')
            ->where('codigo', $codigo)
            ->where('activa', true)
            ->value('id');

        if (! $plantillaId) {
            return 0;
        }

        $detalle = DB::table('core_plantillas_cuentas_detalle')
            ->where('plantilla_id', $plantillaId)
            ->orderBy('codigo')
            ->get();

        $tipos = TipoCuenta::pluck('id', 'codigo');

        DB::transaction(function () use ($detalle, $tipos, $companiaId, $usuario) {
            $idsPorCodigo = [];

            foreach ($detalle as $fila) {
                $cuenta = CuentaContable::create([
                    'compania_id' => $companiaId,
                    'codigo' => $fila->codigo,
                    'nombre' => $fila->nombre,
                    'cuenta_padre_id' => $fila->codigo_padre ? ($idsPorCodigo[$fila->codigo_padre] ?? null) : null,
                    'nivel' => $fila->nivel,
                    'tipo_cuenta_id' => $tipos[$fila->tipo_cuenta_codigo] ?? null,
                    'naturaleza' => $fila->naturaleza,
                    'permite_movimiento' => $fila->permite_movimiento,
                    'conciliable' => $fila->conciliable,
                    'activa' => true,
                    'renglon_isr' => $fila->renglon_isr ?? null,
                    'created_by' => $usuario,
                ]);

                $idsPorCodigo[$fila->codigo] = $cuenta->id;

                if ($fila->clave_default) {
                    DB::table('core_cuentas_default')->insert([
                        'compania_id' => $companiaId,
                        'clave' => $fila->clave_default,
                        'cuenta_id' => $cuenta->id,
                        'descripcion' => $fila->nombre,
                        'created_by' => $usuario,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        return $detalle->count();
    }
}
