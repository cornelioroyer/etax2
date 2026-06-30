<?php

namespace App\Services;

use App\Models\Asiento;
use App\Models\CuentaDefault;
use App\Models\InvExistencia;
use App\Models\ItemProducto;
use App\Models\PeriodoContable;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
 *
 * SALVAGUARDA: el replay asume que TODA la cantidad de `inv_existencias` está
 * respaldada por `inv_movimientos` (arranca cada par item/almacén en 0/0). Si
 * un ítem tiene saldo que nunca se registró como movimiento (saldo inicial
 * migrado/sembrado directo en inv_existencias) y su historial no tiene un
 * AJUSTE que ancle el reinicio, el replay jamás cuadrará con la realidad.
 * `analizar()` detecta ese descuadre y EXCLUYE el ítem/almacén del plan (no
 * toca su existencia ni el costo de sus salidas) en vez de pisarlo con un
 * resultado incompleto; lo reporta en `noReconciliables` y registra un
 * Log::warning para revisión manual.
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
     *   noReconciliables: array<int, array{item_id:int, almacen_id:int, cantidad_actual:float, cantidad_calculada:float}>,
     *   sinCambios: bool
     * }
     *
     * @param  int|array<int,int>|null  $itemId  Un ítem, una lista de ítems, o null = todos.
     */
    public function analizar(int $companiaId, int|array|null $itemId = null, ?int $almacenId = null): array
    {
        // Normaliza el filtro de ítems: acepta un id, una lista o null (todos).
        $itemIds = $itemId === null ? null : array_values(array_filter(array_map('intval', (array) $itemId)));

        $movs = DB::table('inv_movimientos_detalle as d')
            ->join('inv_movimientos as m', 'm.id', '=', 'd.movimiento_id')
            ->where('m.compania_id', $companiaId)
            ->where('m.estado', '!=', 'ANULADO')
            ->when($itemIds, fn ($q) => $q->whereIn('d.item_id', $itemIds))
            ->when($almacenId, fn ($q) => $q->where('m.almacen_id', $almacenId))
            ->orderBy('m.fecha')->orderBy('m.id')->orderBy('d.id')
            ->get([
                'd.id as det_id', 'm.id as mov_id', 'm.fecha', 'm.tipo_movimiento as tipo',
                'm.documento_origen as orig', 'm.documento_id as doc',
                'd.item_id', 'm.almacen_id', 'd.cantidad', 'd.costo_unitario',
            ]);

        $estado      = []; // key => ['qty'=>, 'prom'=>]
        $cambios     = [];
        $tieneAjuste = []; // key => true si el trail tiene un AJUSTE (ancla de reset confiable)

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
                    // El ajuste fija cantidad y costo absolutos: ancla confiable que
                    // resincroniza el replay con la realidad física sin importar lo que
                    // haya antes (ver salvaguarda de "no reconciliables" más abajo).
                    $tieneAjuste[$key] = true;
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

        // Existencias actuales de los pares tocados por el replay, en UNA sola
        // consulta (se reutiliza para detectar saldos no respaldados y para el
        // costo promedio vigente que se informa en el plan).
        $itemIdsEstado    = array_values(array_unique(array_map(fn ($k) => (int) explode('|', $k)[0], array_keys($estado))));
        $almacenIdsEstado = array_values(array_unique(array_map(fn ($k) => (int) explode('|', $k)[1], array_keys($estado))));
        $existenciasActuales = empty($itemIdsEstado) ? collect() : InvExistencia::whereIn('item_id', $itemIdsEstado)
            ->whereIn('almacen_id', $almacenIdsEstado)
            ->get(['item_id', 'almacen_id', 'cantidad', 'costo_promedio'])
            ->keyBy(fn ($e) => $e->item_id.'|'.$e->almacen_id);

        // Salvaguarda: un ítem/almacén sin AJUSTE que ancle su historial DEBE
        // reproducir exactamente la cantidad real (sumar entradas/salidas es
        // conmutativo, así que el orden de fecha nunca cambia el total — solo el
        // costo). Si no cuadra, es que `inv_movimientos` no captura toda la
        // historia (típico de saldo inicial migrado/sembrado directo en
        // inv_existencias). Se excluye ese ítem/almacén del plan en vez de pisar
        // su saldo a ciegas.
        $noReconciliables = [];
        foreach (array_keys($estado) as $key) {
            if (! empty($tieneAjuste[$key])) {
                continue;
            }
            $actual = $existenciasActuales->get($key);
            if (! $actual) {
                continue; // no hay existencia real que proteger
            }
            if (abs((float) $actual->cantidad - $estado[$key]['qty']) > 0.001) {
                [$iid, $aid] = array_map('intval', explode('|', $key));
                $noReconciliables[$key] = [
                    'item_id'            => $iid,
                    'almacen_id'         => $aid,
                    'cantidad_actual'    => (float) $actual->cantidad,
                    'cantidad_calculada' => round($estado[$key]['qty'], 4),
                ];
                unset($estado[$key]);
            }
        }
        if (! empty($noReconciliables)) {
            Log::warning('RecalculadorCostosInventario: existencias con historial de movimientos incompleto, no se auto-reconciliaron', [
                'compania_id' => $companiaId,
                'items'       => array_values($noReconciliables),
            ]);
            $cambios = array_values(array_filter(
                $cambios,
                fn ($c) => ! isset($noReconciliables[$c->item_id.'|'.$c->almacen_id]),
            ));
        }

        // Estado final por (item, almacén) → objetivo para inv_existencias.
        $existencias = [];
        foreach ($estado as $key => $st) {
            [$iid, $aid] = array_map('intval', explode('|', $key));
            $actual = $existenciasActuales->get($key);
            $existencias[$key] = [
                'item_id'               => $iid,
                'almacen_id'            => $aid,
                'cantidad'              => round($st['qty'], 4),
                'costo_promedio'        => round($st['prom'], 4),
                'costo_promedio_actual' => $actual !== null ? (float) $actual->costo_promedio : null,
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
            'cambios'          => $cambios,
            'existencias'      => $existencias,
            'ajusteLineas'     => $ajusteLineas,
            'netoPorItem'      => $netoPorItem,
            'itemsSinCuenta'   => array_values($itemsSinCuenta),
            'noReconciliables' => array_values($noReconciliables),
            'sinCambios'       => empty($cambios),
        ];
    }

    /**
     * Fecha en que conviene postear el asiento de ajuste de un plan: la de la
     * ÚLTIMA salida corregida (período donde el costo de ventas estuvo mal
     * valuado) SI ese período está abierto; de lo contrario hoy. Evita intentar
     * postear en un período cerrado (lo rechazaría AsientoAutomatico). Si el plan
     * no corrige salidas, usa $fallback (o hoy).
     */
    public function fechaAjuste(int $companiaId, array $plan, ?string $fallback, $usuario): string
    {
        $fechas = array_map(fn ($c) => substr((string) $c->fecha, 0, 10), $plan['cambios']);
        $ideal  = empty($fechas) ? ($fallback ?? now()->toDateString()) : max($fechas);

        $periodo = PeriodoContable::paraFecha($companiaId, Carbon::parse($ideal), $usuario->email ?? null);

        return $periodo->estaAbierto() ? $ideal : now()->toDateString();
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
