<?php

namespace App\Services;

use App\Models\InvExistencia;
use App\Models\InvMovimiento;
use App\Models\InvMovimientoDetalle;

/**
 * Integración Inventario ↔ Compras (invoice-driven).
 *
 * Al facturar una orden de compra, las líneas de ítems inventariables
 * (PRODUCTO) suben las existencias al costo de la factura. La contabilidad
 * la lleva el asiento de la propia factura (Dr Inventario / Cr CxP), así que
 * el InvMovimiento de entrada NO postea asiento propio: solo queda enlazado
 * al documento y a su asiento para trazabilidad/kárdex.
 *
 * El reverso (al anular/corregir la factura) baja las existencias al costo de
 * entrada y marca los movimientos como ANULADO. Es idempotente.
 */
class InventarioCompras
{
    /**
     * @param  array<int,array{item_id:int,cantidad:float,costo_unitario:float}>  $lineas
     */
    public function registrarEntrada(
        int $companiaId,
        int $almacenId,
        string $fecha,
        array $lineas,
        ?int $asientoId,
        string $documentoOrigen,
        int $documentoId,
        $usuario,
    ): ?InvMovimiento {
        $lineas = array_values(array_filter($lineas, fn ($l) => (float) $l['cantidad'] > 0));

        if (empty($lineas)) {
            return null;
        }

        $mov = InvMovimiento::create([
            'compania_id'      => $companiaId,
            'almacen_id'       => $almacenId,
            'fecha'            => $fecha,
            'tipo_movimiento'  => InvMovimiento::TIPO_ENTRADA,
            'documento_origen' => $documentoOrigen,
            'documento_id'     => $documentoId,
            'descripcion'      => 'Entrada por compra',
            'asiento_id'       => $asientoId,
            'estado'           => 'CONFIRMADO',
            'created_by'       => $usuario->email,
        ]);

        foreach ($lineas as $linea) {
            $cantidad = round((float) $linea['cantidad'], 4);
            $costo    = round((float) $linea['costo_unitario'], 4);
            $itemId   = (int) $linea['item_id'];

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

            $cantAnterior  = (float) $existencia->cantidad;
            $costoAnterior = (float) $existencia->costo_promedio;
            $nuevaCantidad = round($cantAnterior + $cantidad, 4);
            $nuevoCosto    = $nuevaCantidad > 0
                ? round(($cantAnterior * $costoAnterior + $cantidad * $costo) / $nuevaCantidad, 4)
                : $costo;

            $existencia->update([
                'cantidad'       => $nuevaCantidad,
                'costo_promedio' => $nuevoCosto,
                'updated_by'     => $usuario->email,
            ]);
        }

        return $mov;
    }

    /**
     * Reversa las entradas de inventario asociadas a un documento. Idempotente:
     * ignora los movimientos ya anulados.
     */
    public function reversarPorDocumento(string $documentoOrigen, int $documentoId, $usuario): void
    {
        $movimientos = InvMovimiento::with('detalle')
            ->where('documento_origen', $documentoOrigen)
            ->where('documento_id', $documentoId)
            ->where('tipo_movimiento', InvMovimiento::TIPO_ENTRADA)
            ->where('estado', '!=', 'ANULADO')
            ->get();

        foreach ($movimientos as $mov) {
            foreach ($mov->detalle as $det) {
                $existencia = InvExistencia::where('almacen_id', $mov->almacen_id)
                    ->where('item_id', $det->item_id)
                    ->first();

                if (! $existencia) {
                    continue;
                }

                $cantidad = (float) $det->cantidad;
                $costo    = (float) $det->costo_unitario;
                $qa       = (float) $existencia->cantidad;
                $ca       = (float) $existencia->costo_promedio;

                $nuevaCantidad = round($qa - $cantidad, 4);
                if ($nuevaCantidad < 0) {
                    $nuevaCantidad = 0;
                }

                // Baja el valor al costo de entrada; conserva el promedio si la
                // existencia ya no tiene unidades o el cálculo daría negativo.
                $nuevoValor = $qa * $ca - $cantidad * $costo;
                $nuevoCosto = ($nuevaCantidad > 0 && $nuevoValor >= 0)
                    ? round($nuevoValor / $nuevaCantidad, 4)
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
