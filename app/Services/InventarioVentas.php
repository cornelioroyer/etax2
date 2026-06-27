<?php

namespace App\Services;

use App\Models\CuentaDefault;
use App\Models\InvAlmacen;
use App\Models\InvExistencia;
use App\Models\InvMovimiento;
use App\Models\InvMovimientoDetalle;
use App\Models\ItemProducto;

/**
 * Integración Inventario ↔ Ventas (invoice-driven). Espejo de InventarioCompras.
 *
 * Al emitir una venta, las líneas de ítems inventariables (PRODUCTO) descuentan
 * existencias al costo promedio y generan el costo de ventas. La contabilidad va
 * en el MISMO asiento de la factura (Dr Costo de ventas / Cr Inventario), así que
 * el InvMovimiento de salida NO postea asiento propio: queda enlazado al documento
 * y a su asiento para trazabilidad/kárdex.
 *
 * Flujo en dos fases (el costo promedio se lee ANTES de descontar):
 *   1) calcular(): lee el costo promedio y arma las líneas de costo del asiento, SIN mutar.
 *   2) registrar(): tras postear el asiento, crea el InvMovimiento SALIDA y descuenta stock.
 *
 * Cero regresión: solo mueve ítems PRODUCTO con item_id, almacén, cuentas de
 * inventario + costo resolvibles y costo promedio > 0. Lo demás se comporta como antes.
 */
class InventarioVentas
{
    public const ORIGEN_VENTAS = 'ventas_facturas';

    /** Almacén por defecto (1° activo) cuando el documento no especifica uno. */
    public function almacenPorDefecto(int $companiaId): ?int
    {
        return InvAlmacen::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('codigo')
            ->value('id');
    }

    /**
     * Fase 1: calcula el costo de las líneas inventariables y las líneas de asiento
     * (Dr Costo / Cr Inventario). No muta nada.
     *
     * @param  array<int,array{item_id?:int|null,cantidad:float|int|string}>  $lineas
     * @return array{lineasAsiento: array<int,array>, detalle: array<int,array{item_id:int,cantidad:float,costo_unitario:float}>, costoTotal: float}
     */
    public function calcular(int $companiaId, ?int $almacenId, array $lineas): array
    {
        $resultado = ['lineasAsiento' => [], 'detalle' => [], 'costoTotal' => 0.0];

        if (! $almacenId) {
            return $resultado;
        }

        $itemIds = array_values(array_unique(array_filter(
            array_map(fn ($l) => isset($l['item_id']) ? (int) $l['item_id'] : 0, $lineas),
        )));
        if (empty($itemIds)) {
            return $resultado;
        }

        $cuentaInvDefault   = CuentaDefault::idPara($companiaId, 'INVENTARIO');
        $cuentaCostoDefault = CuentaDefault::idPara($companiaId, 'COSTO_VENTAS');

        $items = ItemProducto::whereIn('id', $itemIds)
            ->where('compania_id', $companiaId)
            ->get(['id', 'nombre', 'tipo', 'cuenta_inventario_id', 'cuenta_costo_venta_id'])
            ->keyBy('id');

        // Acumular cantidad por ítem (varias líneas del mismo producto).
        $porItem = [];
        foreach ($lineas as $l) {
            $itemId = (int) ($l['item_id'] ?? 0);
            $cant   = (float) ($l['cantidad'] ?? 0);
            if ($itemId <= 0 || $cant <= 0) {
                continue;
            }
            $item = $items[$itemId] ?? null;
            if (! $item || $item->tipo !== ItemProducto::TIPO_PRODUCTO) {
                continue;
            }
            $porItem[$itemId] = round(($porItem[$itemId] ?? 0) + $cant, 4);
        }

        foreach ($porItem as $itemId => $cantidad) {
            $item          = $items[$itemId];
            $cuentaInvId   = $item->cuenta_inventario_id ?? $cuentaInvDefault;
            $cuentaCostoId = $item->cuenta_costo_venta_id ?? $cuentaCostoDefault;
            if (! $cuentaInvId || ! $cuentaCostoId) {
                continue; // sin cuentas resolvibles → no mueve (cero regresión)
            }

            $existencia  = InvExistencia::where('almacen_id', $almacenId)
                ->where('item_id', $itemId)
                ->first();
            $costoProm   = $existencia ? (float) $existencia->costo_promedio : 0.0;
            $costoSalida = round($costoProm * $cantidad, 2);
            if ($costoSalida <= 0) {
                continue; // sin costo/existencia → no postea costo de ventas
            }

            $resultado['lineasAsiento'][] = ['cuenta_id' => $cuentaCostoId, 'descripcion' => 'Costo: '.$item->nombre, 'debito' => $costoSalida, 'credito' => 0];
            $resultado['lineasAsiento'][] = ['cuenta_id' => $cuentaInvId,   'descripcion' => 'Inventario: '.$item->nombre, 'debito' => 0, 'credito' => $costoSalida];
            $resultado['detalle'][]       = ['item_id' => $itemId, 'cantidad' => $cantidad, 'costo_unitario' => round($costoProm, 4)];
            $resultado['costoTotal']      = round($resultado['costoTotal'] + $costoSalida, 2);
        }

        return $resultado;
    }

    /**
     * Fase 2: crea el InvMovimiento SALIDA (sin asiento propio) y descuenta stock.
     * Llamar DESPUÉS de postear el asiento que ya incluye las líneas de costo.
     *
     * @param  array<int,array{item_id:int,cantidad:float,costo_unitario:float}>  $detalle  (el devuelto por calcular())
     */
    public function registrar(
        int $companiaId,
        int $almacenId,
        string $fecha,
        array $detalle,
        ?int $asientoId,
        string $documentoOrigen,
        int $documentoId,
        $usuario,
    ): ?InvMovimiento {
        $detalle = array_values(array_filter($detalle, fn ($d) => (float) $d['cantidad'] > 0));
        if (empty($detalle)) {
            return null;
        }

        $mov = InvMovimiento::create([
            'compania_id'      => $companiaId,
            'almacen_id'       => $almacenId,
            'fecha'            => $fecha,
            'tipo_movimiento'  => InvMovimiento::TIPO_SALIDA,
            'documento_origen' => $documentoOrigen,
            'documento_id'     => $documentoId,
            'descripcion'      => 'Salida por venta',
            'asiento_id'       => $asientoId,
            'estado'           => 'CONFIRMADO',
            'created_by'       => $usuario->email,
        ]);

        foreach ($detalle as $d) {
            $cantidad = round((float) $d['cantidad'], 4);
            $costo    = round((float) $d['costo_unitario'], 4);
            $itemId   = (int) $d['item_id'];

            InvMovimientoDetalle::create([
                'movimiento_id'  => $mov->id,
                'item_id'        => $itemId,
                'cantidad'       => $cantidad,
                'costo_unitario' => $costo,
                'total'          => round($cantidad * $costo, 2),
                'created_by'     => $usuario->email,
            ]);

            $existencia = InvExistencia::firstOrCreate(
                ['almacen_id' => $almacenId, 'item_id' => $itemId],
                ['compania_id' => $companiaId, 'cantidad' => 0, 'costo_promedio' => $costo, 'updated_by' => $usuario->email],
            );

            // Descuenta (tope en 0, como el módulo de inventario manual). El costo
            // promedio no cambia al salir.
            $existencia->update([
                'cantidad'   => max(0, round((float) $existencia->cantidad - $cantidad, 4)),
                'updated_by' => $usuario->email,
            ]);
        }

        return $mov;
    }

    /**
     * Reversa las salidas de inventario de un documento: repone existencias al
     * costo de salida (recalcula el promedio ponderado, como una entrada) y marca
     * los movimientos como ANULADO. Idempotente: ignora los ya anulados.
     */
    public function reversarPorDocumento(string $documentoOrigen, int $documentoId, $usuario): void
    {
        $movimientos = InvMovimiento::with('detalle')
            ->where('documento_origen', $documentoOrigen)
            ->where('documento_id', $documentoId)
            ->where('tipo_movimiento', InvMovimiento::TIPO_SALIDA)
            ->where('estado', '!=', 'ANULADO')
            ->get();

        foreach ($movimientos as $mov) {
            foreach ($mov->detalle as $det) {
                $cantidad = (float) $det->cantidad;
                $costo    = (float) $det->costo_unitario;

                $existencia = InvExistencia::where('almacen_id', $mov->almacen_id)
                    ->where('item_id', $det->item_id)
                    ->first();

                if (! $existencia) {
                    InvExistencia::create([
                        'compania_id'    => $mov->compania_id,
                        'almacen_id'     => $mov->almacen_id,
                        'item_id'        => $det->item_id,
                        'cantidad'       => round($cantidad, 4),
                        'costo_promedio' => round($costo, 4),
                        'updated_by'     => $usuario->email,
                    ]);
                    continue;
                }

                $qa = (float) $existencia->cantidad;
                $ca = (float) $existencia->costo_promedio;
                $nuevaCantidad = round($qa + $cantidad, 4);
                $nuevoCosto    = $nuevaCantidad > 0
                    ? round(($qa * $ca + $cantidad * $costo) / $nuevaCantidad, 4)
                    : $ca;

                $existencia->update([
                    'cantidad'       => $nuevaCantidad,
                    'costo_promedio' => $nuevoCosto,
                    'updated_by'     => $usuario->email,
                ]);
            }

            $mov->update(['estado' => 'ANULADO', 'updated_by' => $usuario->email]);
        }
    }
}
