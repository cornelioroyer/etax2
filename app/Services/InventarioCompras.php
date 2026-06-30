<?php

namespace App\Services;

use App\Models\InvAlmacen;
use App\Models\InvExistencia;
use App\Models\InvMovimiento;
use App\Models\InvMovimientoDetalle;
use App\Models\ItemProducto;
use Illuminate\Validation\ValidationException;

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

        // Si esta entrada quedó con fecha anterior a salidas ya costeadas
        // (compra back-dated), recostea y reajusta automáticamente.
        app(ReconciliadorCostosInventario::class)->reconciliar(
            $companiaId,
            array_map(fn ($l) => (int) $l['item_id'], $lineas),
            $almacenId,
            $fecha,
            $usuario,
        );

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
     *
     * Guarda de integridad: si la mercancía de la compra YA se consumió/vendió
     * (la existencia quedaría negativa al reversar), se bloquea la anulación. De
     * lo contrario quedaría un costo de ventas ya posteado sin la compra que lo
     * respalda (inventario negativo huérfano). El usuario debe registrar una
     * devolución / nota de crédito de compra en su lugar.
     */
    public function reversarPorDocumento(string $documentoOrigen, int $documentoId, $usuario): void
    {
        $movimientos = InvMovimiento::with('detalle')
            ->where('documento_origen', $documentoOrigen)
            ->where('documento_id', $documentoId)
            ->where('tipo_movimiento', InvMovimiento::TIPO_ENTRADA)
            ->where('estado', '!=', 'ANULADO')
            ->get();

        if ($movimientos->isEmpty()) {
            return;
        }

        $this->validarReversaNoDejaNegativo($movimientos);

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

        // Quitar una entrada del medio de la línea de tiempo cambia el costo de las
        // salidas posteriores: recostea y reajusta los ítems afectados.
        $itemIds = $movimientos->flatMap(fn ($m) => $m->detalle->pluck('item_id'))->all();
        app(ReconciliadorCostosInventario::class)->reconciliar(
            $movimientos->first()->compania_id, $itemIds, null, null, $usuario,
        );
    }

    /**
     * Verifica que reversar las entradas no lleve ninguna existencia por debajo de
     * cero: la mercancía ya consumida no se puede "des-comprar". Acumula la cantidad
     * a reversar por par (almacén, ítem) y la compara con la existencia actual; si
     * alguna quedaría negativa, lanza ValidationException con el detalle legible.
     *
     * @param  \Illuminate\Support\Collection<int,InvMovimiento>  $movimientos
     */
    private function validarReversaNoDejaNegativo($movimientos): void
    {
        // Cantidad a reversar acumulada por "almacenId|itemId".
        $requerido = [];
        foreach ($movimientos as $mov) {
            foreach ($mov->detalle as $det) {
                $clave = $mov->almacen_id.'|'.$det->item_id;
                $requerido[$clave] = round(($requerido[$clave] ?? 0) + (float) $det->cantidad, 4);
            }
        }

        $conflictos = [];
        foreach ($requerido as $clave => $cantidad) {
            [$almacenId, $itemId] = array_map('intval', explode('|', $clave));
            $disponible = (float) (InvExistencia::where('almacen_id', $almacenId)
                ->where('item_id', $itemId)
                ->value('cantidad') ?? 0);

            if ($disponible - $cantidad < -0.0001) {
                $conflictos[] = ['almacen_id' => $almacenId, 'item_id' => $itemId, 'disponible' => $disponible, 'requerido' => $cantidad];
            }
        }

        if (empty($conflictos)) {
            return;
        }

        // Enriquecer con códigos/nombres legibles para el mensaje.
        $items     = ItemProducto::whereIn('id', array_column($conflictos, 'item_id'))->get(['id', 'codigo', 'nombre'])->keyBy('id');
        $almacenes = InvAlmacen::whereIn('id', array_column($conflictos, 'almacen_id'))->get(['id', 'codigo'])->keyBy('id');

        $detalle = array_map(function ($c) use ($items, $almacenes) {
            $item    = $items[$c['item_id']] ?? null;
            $almacen = $almacenes[$c['almacen_id']] ?? null;
            $nombre  = $item ? trim($item->codigo.' '.$item->nombre) : 'ítem '.$c['item_id'];
            $alm     = $almacen ? $almacen->codigo : 'almacén '.$c['almacen_id'];

            return sprintf('«%s» en %s (hay %s, se requieren %s)', $nombre, $alm, $this->fmtCantidad($c['disponible']), $this->fmtCantidad($c['requerido']));
        }, $conflictos);

        throw ValidationException::withMessages([
            'documento' => 'No se puede anular: la mercancía de esta compra ya fue consumida o vendida, '
                .'por lo que el inventario quedaría negativo. Registre una devolución / nota de crédito de '
                .'compra en lugar de anular. Detalle: '.implode('; ', $detalle).'.',
        ]);
    }

    /** Formatea una cantidad de inventario sin ceros decimales sobrantes. */
    private function fmtCantidad(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }
}
