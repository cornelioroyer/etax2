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
     * Ajusta el VALOR de una existencia sin cambiar la cantidad (recalcula el
     * costo promedio). Se usa cuando el costo facturado difiere del costo con que
     * el bien entró por recepción (GRNI): el mayor recibe la varianza en la cuenta
     * de inventario y aquí se refleja el mismo delta en el kárdex para preservar
     * la igualdad kárdex (cantidad × costo_promedio) = saldo de inventario.
     */
    public function ajustarValorExistencia(int $companiaId, int $almacenId, int $itemId, float $deltaValor, $usuario): void
    {
        if (abs($deltaValor) < 0.00001) {
            return;
        }

        $existencia = InvExistencia::where('almacen_id', $almacenId)
            ->where('item_id', $itemId)
            ->first();

        if (! $existencia) {
            return;
        }

        $cantidad = (float) $existencia->cantidad;
        $valorActual = round($cantidad * (float) $existencia->costo_promedio, 4);
        $nuevoValor = round($valorActual + $deltaValor, 4);
        $nuevoCosto = abs($cantidad) > 0.00001 ? round($nuevoValor / $cantidad, 4) : (float) $existencia->costo_promedio;

        $existencia->update([
            'costo_promedio' => $nuevoCosto,
            'updated_by'     => $usuario->email,
        ]);
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

                // Permite existencia NEGATIVA (política "inventario negativo
                // consistente"): al anular una compra cuyo stock ya se consumió, el
                // asiento anulado acredita Inventario por costo × cantidad, así que la
                // existencia debe bajar por esa misma cantidad para que el kárdex
                // cuadre con el mayor. Pisar en 0 reintroducía el descuadre.
                $nuevaCantidad = round($qa - $cantidad, 4);

                // El valor baja EXACTAMENTE por el costo de entrada (= crédito a
                // Inventario del asiento). El promedio se recalcula para preservar esa
                // igualdad; solo se conserva el anterior en el caso degenerado de
                // cantidad resultante nula (valor 0).
                $nuevoValor = round($qa * $ca - $cantidad * $costo, 4);
                $nuevoCosto = abs($nuevaCantidad) > 0.00001
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
