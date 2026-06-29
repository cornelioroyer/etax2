<?php

namespace App\Services;

use App\Models\Asiento;
use App\Models\CuentaDefault;
use App\Models\InvExistencia;
use App\Models\ItemProducto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula el costo de las SALIDAS de inventario por PROMEDIO PONDERADO en
 * ORDEN DE FECHA (no de inserción).
 *
 * Problema que resuelve: el costo de una salida se captura al postear leyendo
 * `inv_existencias.costo_promedio` (InventarioVentas::calcular). El promedio
 * móvil depende del orden; un documento "back-dated" (una compra con fecha
 * anterior ingresada después de ventas ya posteadas) deja el costo de esas
 * ventas mal valuado y desincroniza kárdex ↔ existencia ↔ mayor.
 *
 * Este servicio re-recorre cada (ítem, almacén) por fecha — exactamente la
 * misma regla que el kárdex (InvKardexController) — y:
 *   1) Corrige `inv_movimientos_detalle.costo_unitario`/`total` de las salidas.
 *   2) Deja `inv_existencias` en el estado final correcto.
 *   3) Postea UN asiento de ajuste (Dr/Cr Costo de Ventas / Inventario) por la
 *      diferencia neta, SIN tocar los asientos originales (preserva auditoría).
 *
 * Idempotente: re-ejecutarlo no encuentra diferencias.
 *
 * El costo promedio de la existencia lo determinan SOLO las entradas (las
 * salidas descargan al promedio vigente y no lo cambian); por eso recalcular el
 * orden de las entradas frente a las salidas corrige tanto el costo de cada
 * salida como el promedio final.
 */
class RecalculadorCostosInventario
{
    public function __construct(private AsientoAutomatico $asientos) {}

    /**
     * Analiza el recálculo SIN mutar nada. Devuelve el plan.
     *
     * @return array{
     *   cambios: array<int, object>,
     *   existencias: array<string, array{item_id:int, almacen_id:int, cantidad:float, costo_promedio:float, costo_promedio_actual:?float}>,
     *   ajusteLineas: array<int, array{cuenta_id:int, descripcion:string, debito:float, credito:float}>,
     *   netoPorItem: array<int, float>,
     *   itemsSinCuenta: array<int, int>,
     *   sinCambios: bool
     * }
     */
    public function analizar(int $companiaId, ?int $itemId = null, ?int $almacenId = null): array
    {
        $movs = DB::table('inv_movimientos_detalle as d')
            ->join('inv_movimientos as m', 'm.id', '=', 'd.movimiento_id')
            ->where('m.compania_id', $companiaId)
            ->where('m.estado', '!=', 'ANULADO')
            ->when($itemId, fn ($q) => $q->where('d.item_id', $itemId))
            ->when($almacenId, fn ($q) => $q->where('m.almacen_id', $almacenId))
            ->orderBy('m.fecha')->orderBy('m.id')->orderBy('d.id')
            ->get([
                'd.id as det_id', 'm.id as mov_id', 'm.fecha', 'm.tipo_movimiento as tipo',
                'm.documento_origen as orig', 'm.documento_id as doc',
                'd.item_id', 'm.almacen_id', 'd.cantidad', 'd.costo_unitario',
            ]);

        $estado  = []; // key => ['qty'=>, 'prom'=>]
        $cambios = [];

        foreach ($movs as $r) {
            $key   = $r->item_id.'|'.$r->almacen_id;
            $st    = $estado[$key] ?? ['qty' => 0.0, 'prom' => 0.0];
            $cant  = (float) $r->cantidad;
            $costo = round((float) $r->costo_unitario, 4);

            switch ($r->tipo) {
                case 'SALIDA':
                    // Descarga al promedio vigente; el promedio NO cambia.
                    $nuevoCosto  = round($st['prom'], 4);
                    $st['qty']   = round($st['qty'] - $cant, 4); // permite negativo (política inv. negativo)
                    $totalViejo  = round($costo * $cant, 2);
                    $totalNuevo  = round($nuevoCosto * $cant, 2);
                    if (abs($nuevoCosto - $costo) >= 0.0001 || abs($totalNuevo - $totalViejo) >= 0.005) {
                        $cambios[] = (object) [
                            'det_id'      => (int) $r->det_id,
                            'mov_id'      => (int) $r->mov_id,
                            'item_id'     => (int) $r->item_id,
                            'almacen_id'  => (int) $r->almacen_id,
                            'fecha'       => $r->fecha,
                            'doc'         => trim(($r->orig ?? '').' #'.($r->doc ?? '')),
                            'cantidad'    => $cant,
                            'costo_viejo' => $costo,
                            'costo_nuevo' => $nuevoCosto,
                            'total_viejo' => $totalViejo,
                            'total_nuevo' => $totalNuevo,
                            'delta'       => round($totalNuevo - $totalViejo, 2),
                        ];
                    }
                    break;

                case 'AJUSTE':
                    // El ajuste fija cantidad y costo absolutos.
                    $st['qty']  = round($cant, 4);
                    $st['prom'] = $costo;
                    break;

                case 'ENTRADA':
                default:
                    $nueva      = round($st['qty'] + $cant, 4);
                    $st['prom'] = $nueva != 0.0
                        ? round(($st['qty'] * $st['prom'] + $cant * $costo) / $nueva, 4)
                        : $costo;
                    $st['qty']  = $nueva;
                    break;
            }

            $estado[$key] = $st;
        }

        // Estado final por (item, almacén) → objetivo para inv_existencias.
        $existencias = [];
        foreach ($estado as $key => $st) {
            [$iid, $aid] = array_map('intval', explode('|', $key));
            $actual = InvExistencia::where('almacen_id', $aid)->where('item_id', $iid)
                ->value('costo_promedio');
            $existencias[$key] = [
                'item_id'               => $iid,
                'almacen_id'            => $aid,
                'cantidad'              => round($st['qty'], 4),
                'costo_promedio'        => round($st['prom'], 4),
                'costo_promedio_actual' => $actual !== null ? (float) $actual : null,
            ];
        }

        // Ajuste contable: acumular la diferencia neta por par de cuentas
        // (costo de ventas, inventario), resueltas por ítem (con caída a default).
        $cuentaInvDefault   = CuentaDefault::idPara($companiaId, 'INVENTARIO');
        $cuentaCostoDefault = CuentaDefault::idPara($companiaId, 'COSTO_VENTAS');

        $itemIds = array_values(array_unique(array_map(fn ($c) => $c->item_id, $cambios)));
        $items   = ItemProducto::whereIn('id', $itemIds)
            ->where('compania_id', $companiaId)
            ->get(['id', 'nombre', 'cuenta_inventario_id', 'cuenta_costo_venta_id'])
            ->keyBy('id');

        $porPar        = []; // "costo|inv" => ['costo_id'=>, 'inv_id'=>, 'neto'=>, 'items'=>[]]
        $netoPorItem   = [];
        $itemsSinCuenta = [];

        foreach ($cambios as $c) {
            $item    = $items[$c->item_id] ?? null;
            $invId   = $item?->cuenta_inventario_id ?? $cuentaInvDefault;
            $costoId = $item?->cuenta_costo_venta_id ?? $cuentaCostoDefault;
            $netoPorItem[$c->item_id] = round(($netoPorItem[$c->item_id] ?? 0) + $c->delta, 2);

            if (! $invId || ! $costoId) {
                $itemsSinCuenta[$c->item_id] = $c->item_id;
                continue;
            }
            $pk = $costoId.'|'.$invId;
            $porPar[$pk] ??= ['costo_id' => $costoId, 'inv_id' => $invId, 'neto' => 0.0, 'items' => []];
            $porPar[$pk]['neto'] = round($porPar[$pk]['neto'] + $c->delta, 2);
            if ($item) {
                $porPar[$pk]['items'][$item->id] = $item->nombre;
            }
        }

        $ajusteLineas = [];
        foreach ($porPar as $p) {
            if (abs($p['neto']) < 0.005) {
                continue; // el neto se cancela: no hay nada que reclasificar
            }
            $nombres = implode(', ', array_slice(array_values($p['items']), 0, 5));
            if ($p['neto'] > 0) {
                // Faltó costo: subir Costo de Ventas, bajar Inventario.
                $ajusteLineas[] = ['cuenta_id' => $p['costo_id'], 'descripcion' => 'Ajuste costo de ventas: '.$nombres, 'debito' => $p['neto'], 'credito' => 0];
                $ajusteLineas[] = ['cuenta_id' => $p['inv_id'],   'descripcion' => 'Ajuste valor inventario: '.$nombres, 'debito' => 0, 'credito' => $p['neto']];
            } else {
                $abs = abs($p['neto']);
                $ajusteLineas[] = ['cuenta_id' => $p['inv_id'],   'descripcion' => 'Ajuste valor inventario: '.$nombres, 'debito' => $abs, 'credito' => 0];
                $ajusteLineas[] = ['cuenta_id' => $p['costo_id'], 'descripcion' => 'Ajuste costo de ventas: '.$nombres, 'debito' => 0, 'credito' => $abs];
            }
        }

        return [
            'cambios'        => $cambios,
            'existencias'    => $existencias,
            'ajusteLineas'   => $ajusteLineas,
            'netoPorItem'    => $netoPorItem,
            'itemsSinCuenta' => array_values($itemsSinCuenta),
            'sinCambios'     => empty($cambios),
        ];
    }

    /**
     * Aplica el plan: corrige costos de salidas, deja existencias en su estado
     * final y postea el asiento de ajuste. DEBE llamarse dentro de una
     * transacción (lo envuelve el comando). Devuelve el asiento de ajuste creado
     * (o null si el neto fue cero / no hubo cambios).
     */
    public function aplicar(int $companiaId, array $plan, string $fecha, User $usuario): ?Asiento
    {
        if ($plan['sinCambios']) {
            return null;
        }

        // 1) Corregir el costo grabado de cada salida.
        foreach ($plan['cambios'] as $c) {
            DB::table('inv_movimientos_detalle')
                ->where('id', $c->det_id)
                ->update([
                    'costo_unitario' => $c->costo_nuevo,
                    'total'          => $c->total_nuevo,
                    'updated_by'     => $usuario->email,
                    'updated_at'     => now(),
                ]);
        }

        // 2) Dejar las existencias en el estado final recalculado.
        foreach ($plan['existencias'] as $e) {
            InvExistencia::where('almacen_id', $e['almacen_id'])
                ->where('item_id', $e['item_id'])
                ->update([
                    'cantidad'       => $e['cantidad'],
                    'costo_promedio' => $e['costo_promedio'],
                    'updated_by'     => $usuario->email,
                ]);
        }

        // 3) Asiento de ajuste por la diferencia neta (no toca los originales).
        if (empty($plan['ajusteLineas'])) {
            return null;
        }

        return $this->asientos->postear(
            $companiaId,
            $fecha,
            'Ajuste por recálculo de costo de inventario (promedio ponderado por fecha)',
            'RECALC-INV',
            $plan['ajusteLineas'],
            'INVENTARIO',
            'inv_recalculo_costos',
            null,
            $usuario,
        );
    }
}
