<?php

namespace App\Services;

use App\Services\RecalculadorCostosInventario;
use Illuminate\Support\Facades\DB;

/**
 * Mantiene el costeo de inventario CORRECTO de forma automática frente a
 * documentos "back-dated".
 *
 * Problema: el costo promedio móvil depende del ORDEN de captura. Si se ingresa
 * una compra/recepción con fecha anterior a movimientos ya costeados (o se anula
 * un movimiento intermedio), las salidas posteriores quedan mal valuadas y el
 * kárdex se desincroniza de existencias y del mayor.
 *
 * Este reconciliador se invoca al final de cada mutación del ledger de
 * inventario (entradas, salidas, devoluciones y sus reversas). Detecta si la
 * operación dejó costos por recalcular y, de ser así, delega en
 * RecalculadorCostosInventario para:
 *   1) recostear las salidas en orden de fecha,
 *   2) dejar inv_existencias en el estado correcto,
 *   3) postear UN asiento de ajuste por la diferencia (sin tocar los originales).
 *
 * Es idempotente y barato en el camino normal: si todo está en orden, el
 * análisis no encuentra diferencias y no escribe nada.
 */
class ReconciliadorCostosInventario
{
    public function __construct(private RecalculadorCostosInventario $recalc) {}

    /**
     * Reconcilia los ítems afectados por una operación de inventario. Seguro de
     * llamar siempre: si no hay nada que corregir, no muta ni postea.
     *
     * @param  array<int,int>  $itemIds  Ítems tocados por la operación.
     * @param  string|null  $fechaOperacion  Fecha del documento que disparó la
     *         operación; si es null se fuerza el chequeo (p. ej. en reversas,
     *         donde el movimiento removido puede estar en cualquier punto de la
     *         línea de tiempo).
     */
    public function reconciliar(int $companiaId, array $itemIds, ?int $almacenId, ?string $fechaOperacion, $usuario): void
    {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
        if (empty($itemIds)) {
            return;
        }

        // Optimización del camino normal: si la operación tiene fecha y NO quedó
        // ningún movimiento posterior a esa fecha para estos ítems, el costeo se
        // hizo en orden y no hay nada que reordenar. (En reversas pasamos null
        // para forzar el chequeo, porque quitar un movimiento intermedio cambia
        // el costo de las salidas que venían después.)
        if ($fechaOperacion !== null && ! $this->existeMovimientoPosterior($companiaId, $itemIds, $almacenId, $fechaOperacion)) {
            return;
        }

        $plan = $this->recalc->analizar($companiaId, $itemIds, $almacenId);
        if ($plan['sinCambios']) {
            return;
        }

        // El ajuste se reconoce en el período donde el costo de ventas estuvo mal
        // valuado: la fecha de la ÚLTIMA salida corregida. Si no hubo salidas que
        // corregir (solo cambió el promedio para futuras ventas), aplicar() no
        // generará asiento (neto cero) y solo dejará existencias correctas.
        $fecha = $this->recalc->fechaAjuste($companiaId, $plan, $fechaOperacion, $usuario);

        $this->recalc->aplicar($companiaId, $plan, $fecha, $usuario);
    }

    /** ¿Hay algún movimiento vigente de estos ítems con fecha posterior a $fecha? */
    private function existeMovimientoPosterior(int $companiaId, array $itemIds, ?int $almacenId, string $fecha): bool
    {
        return DB::table('inv_movimientos as m')
            ->join('inv_movimientos_detalle as d', 'd.movimiento_id', '=', 'm.id')
            ->where('m.compania_id', $companiaId)
            ->where('m.estado', '!=', 'ANULADO')
            ->whereIn('d.item_id', $itemIds)
            ->when($almacenId, fn ($q) => $q->where('m.almacen_id', $almacenId))
            ->whereDate('m.fecha', '>', $fecha)
            ->exists();
    }
}
