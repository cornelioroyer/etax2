<?php

namespace App\Services;

use App\Models\CuentaDefault;
use Illuminate\Support\Facades\DB;

/**
 * Cuentas de control de los libros auxiliares (CxC, CxP, Inventario, Bancos,
 * Caja, Activos Fijos). Estas cuentas solo se mueven desde sus módulos —que
 * mantienen el submayor y postean vía AsientoAutomatico—; un asiento manual (o
 * una plantilla recurrente) no puede afectarlas, o el auxiliar dejaría de
 * cuadrar contra el mayor (el submayor de cobros/pagos, la conciliación
 * bancaria, el arqueo de caja o el registro de activos quedarían desfasados).
 *
 * Fuente única de esta regla, compartida por el módulo de Asientos y el de
 * Asientos Recurrentes.
 */
class CuentasControlContable
{
    /**
     * @return array<int, string>  [cuenta_id => etiqueta del módulo dueño]
     */
    public static function para(int $companiaId): array
    {
        $control = [];

        // CxC / CxP: cuentas de control configuradas en core_cuentas_default.
        foreach (['CXC' => 'Cuentas por Cobrar', 'CXP' => 'Cuentas por Pagar'] as $clave => $etiqueta) {
            if ($id = CuentaDefault::idPara($companiaId, $clave)) {
                $control[$id] = $etiqueta;
            }
        }

        // Inventario: la cuenta de inventario de cada item; su saldo lo lleva el
        // kardex/valuación del módulo de Inventario.
        self::marcar($control, 'Inventario', DB::table('item_productos_servicios')
            ->where('compania_id', $companiaId)
            ->whereNotNull('cuenta_inventario_id')
            ->distinct()
            ->pluck('cuenta_inventario_id'));

        // Bancos: la cuenta contable de cada cuenta bancaria se concilia contra
        // el estado de cuenta desde el módulo de Bancos (cheques, depósitos,
        // transferencias, conciliación).
        self::marcar($control, 'Bancos', DB::table('bco_cuentas')
            ->where('compania_id', $companiaId)
            ->whereNotNull('cuenta_contable_id')
            ->distinct()
            ->pluck('cuenta_contable_id'));

        // Caja: la cuenta contable de cada caja se mueve por el módulo de Caja,
        // que mantiene el arqueo (movimientos, vales, reembolsos).
        self::marcar($control, 'Caja', DB::table('caj_cajas')
            ->where('compania_id', $companiaId)
            ->whereNotNull('cuenta_contable_id')
            ->distinct()
            ->pluck('cuenta_contable_id'));

        // Activos Fijos: el costo del activo y la depreciación acumulada los
        // mantiene el registro de activos (alta, depreciación, baja). NO se
        // incluye la cuenta de GASTO de depreciación: es de resultados y no
        // lleva submayor, así que un asiento manual sí puede afectarla.
        foreach (['afi_activos', 'afi_categorias'] as $tabla) {
            foreach (['cuenta_activo_id', 'cuenta_depreciacion_acum_id'] as $columna) {
                self::marcar($control, 'Activos Fijos', DB::table($tabla)
                    ->where('compania_id', $companiaId)
                    ->whereNotNull($columna)
                    ->distinct()
                    ->pluck($columna));
            }
        }

        return $control;
    }

    /**
     * Marca cada id de la colección como cuenta de control del módulo, sin pisar
     * una etiqueta ya asignada: si una misma cuenta la comparten dos módulos, la
     * primera fuente registrada conserva la etiqueta (el orden de este servicio
     * define la prioridad), de modo que el resultado sea determinista.
     *
     * @param  array<int, string>  $control
     * @param  iterable  $ids
     */
    private static function marcar(array &$control, string $etiqueta, $ids): void
    {
        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id > 0 && ! isset($control[$id])) {
                $control[$id] = $etiqueta;
            }
        }
    }
}
