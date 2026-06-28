<?php

namespace App\Services;

/**
 * Cálculo central de totales de documentos comerciales (cotizaciones, facturas
 * de venta y de compra). Centraliza la regla de descuentos + ITBMS para que
 * ventas y compras calculen idéntico y el efecto contable sea consistente.
 *
 * Método NETO: el descuento (de línea y general) reduce la base imponible, y
 * el ITBMS se calcula sobre la base ya neta. El descuento general se prorratea
 * entre las líneas en proporción a su base neta; el residuo de redondeo se
 * asigna a la última línea para que la suma cuadre al centavo.
 *
 * Convención de cabecera:
 *   subtotal  = suma de bases BRUTAS (cantidad × precio), antes de descuentos
 *   descuento = total de descuentos (línea + general)
 *   itbms     = suma de impuestos sobre la base imponible neta
 *   total     = subtotal − descuento + itbms
 */
class CalculoDocumento
{
    /**
     * @param  array  $lineas  cada línea debe traer: cantidad, precio_unitario,
     *                         tasa (porcentaje del impuesto) y, opcional,
     *                         descuento (monto de descuento de línea). Cualquier
     *                         otra clave (item_id, descripcion, impuesto_id,
     *                         cuenta_ingreso_id…) se conserva intacta.
     * @param  float  $descuentoGeneral  monto de descuento global a prorratear.
     * @return array{lineas: array, subtotal: float, descuento: float, itbms: float, total: float}
     */
    public static function calcular(array $lineas, float $descuentoGeneral = 0.0): array
    {
        $lineas = array_values($lineas);
        $n = count($lineas);

        // 1) Base bruta y descuento de línea → base neta por línea.
        $calc = [];
        $sumaBaseNeta = 0.0;
        foreach ($lineas as $i => $l) {
            $cant   = round((float) ($l['cantidad'] ?? 0), 4);
            $precio = round((float) ($l['precio_unitario'] ?? 0), 4);
            $bruto  = round($cant * $precio, 2);

            $descLinea = round((float) ($l['descuento'] ?? 0), 2);
            if ($descLinea < 0) {
                $descLinea = 0.0;
            }
            if ($descLinea > $bruto) {
                $descLinea = $bruto;
            }

            $baseNeta = round($bruto - $descLinea, 2);
            $sumaBaseNeta += $baseNeta;

            $calc[] = [
                'i'         => $i,
                'cant'      => $cant,
                'precio'    => $precio,
                'bruto'     => $bruto,
                'descLinea' => $descLinea,
                'baseNeta'  => $baseNeta,
                'tasa'      => (float) ($l['tasa'] ?? 0),
            ];
        }

        // 2) Prorrateo del descuento general por peso de la base neta.
        $descGen = round(max(0.0, $descuentoGeneral), 2);
        if ($descGen > $sumaBaseNeta) {
            $descGen = $sumaBaseNeta;
        }
        $asignado = 0.0;
        foreach ($calc as $k => &$c) {
            if ($descGen > 0 && $sumaBaseNeta > 0) {
                if ($k === $n - 1) {
                    $porc = round($descGen - $asignado, 2); // residuo a la última línea
                } else {
                    $porc = round($descGen * $c['baseNeta'] / $sumaBaseNeta, 2);
                    $asignado += $porc;
                }
            } else {
                $porc = 0.0;
            }
            $c['descGeneral']   = $porc;
            $c['baseImponible'] = round($c['baseNeta'] - $porc, 2);
        }
        unset($c);

        // 3) Impuesto por línea + totales de cabecera.
        $subtotal = 0.0;
        $descTotal = 0.0;
        $itbms = 0.0;
        $out = [];
        foreach ($calc as $c) {
            $imp = round($c['baseImponible'] * $c['tasa'] / 100, 2);
            $descLineaTotal = round($c['descLinea'] + $c['descGeneral'], 2);

            $subtotal  += $c['bruto'];
            $descTotal += $descLineaTotal;
            $itbms     += $imp;

            // Conserva todas las claves originales de la línea y agrega lo calculado.
            $out[] = array_merge($lineas[$c['i']], [
                'linea'          => $c['i'] + 1,
                'cantidad'       => $c['cant'],
                'precio_unitario'=> $c['precio'],
                'descuento'      => $descLineaTotal,   // descuento efectivo de la línea (línea + prorrateo)
                'base'           => $c['baseImponible'],
                'impuesto_monto' => $imp,
                'total_linea'    => round($c['baseImponible'] + $imp, 2),
            ]);
        }

        return [
            'lineas'    => $out,
            'subtotal'  => round($subtotal, 2),
            'descuento' => round($descTotal, 2),
            'itbms'     => round($itbms, 2),
            'total'     => round($subtotal - $descTotal + $itbms, 2),
        ];
    }
}
