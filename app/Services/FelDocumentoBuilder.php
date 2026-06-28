<?php

namespace App\Services;

use App\Models\Compania;
use App\Models\Contacto;
use App\Models\FelConfiguracion;

/**
 * Construye el arreglo DocumentoElectronico que espera el WS de
 * The Factory HKA (estructura del ejemplo "Factura de operación interna").
 *
 * Importante (wiki HKA): los campos no requeridos se OMITEN por completo,
 * no se envían vacíos.
 */
class FelDocumentoBuilder
{
    /**
     * Tasas ITBMS según catálogo DGI: código => factor.
     *
     * Estos factores DEBEN coincidir con los porcentajes canónicos de
     * App\Models\TaxImpuesto (PORCENTAJES_ITBMS / DGI_CODIGO_POR_PORCENTAJE).
     * La consistencia la verifica ItbmsConsistenciaTest. Ver DECISIONES.md → D-06.
     */
    public const TASAS_ITBMS = [
        '00' => 0.00,   // Exento
        '01' => 0.07,   // 7%
        '02' => 0.10,   // 10% (bebidas alcohólicas, hospedaje)
        '03' => 0.15,   // 15% (tabaco)
    ];

    /**
     * Tipos de documento soportados (catálogo DGI / campo tipoDocumento).
     * Las notas genéricas (06/07) NO requieren referenciar el CUFE de un
     * documento original, a diferencia de las notas 04/05.
     */
    public const TIPOS_DOCUMENTO = [
        '01' => 'Factura de operación interna',
        '04' => 'Nota de crédito referente a una o varias facturas electrónicas',
        '05' => 'Nota de débito referente a una o varias facturas electrónicas',
        '06' => 'Nota de crédito genérica',
        '07' => 'Nota de débito genérica',
        '09' => 'Reembolso',
    ];

    /** Formas de pago según catálogo DGI/HKA */
    public const FORMAS_PAGO = [
        '01' => 'Crédito',
        '02' => 'Efectivo',
        '03' => 'Tarjeta de crédito',
        '04' => 'Tarjeta de débito',
        '05' => 'Transferencia / ACH',
        '08' => 'Tarjeta prepago',
        '99' => 'Otro',
    ];

    /**
     * @param array $datos  ['items' => [[descripcion, cantidad, precio, tasa]], 'forma_pago' => '02',
     *                       'informacion_interes' => ?, receptor: ver abajo]
     */
    public function facturaInterna(
        Compania $compania,
        FelConfiguracion $config,
        ?Contacto $cliente,
        array $datos,
        int $numeroFiscal,
    ): array {
        [$items, $totalNeto, $totalItbms, $totalDescuento] = $this->items($datos['items']);
        $totalFactura = round($totalNeto + $totalItbms, 2);

        $tipoDocumento = $datos['tipo_documento'] ?? '01';
        if (! isset(self::TIPOS_DOCUMENTO[$tipoDocumento])) {
            $tipoDocumento = '01';
        }

        // codigoSucursalEmisor y tipoSucursal van en la raíz del DocumentoElectronico,
        // y cliente dentro de datosTransaccion (estructura del WSDL obj v1.0).
        $documento = [
            'codigoSucursalEmisor' => $config->codigo_sucursal ?: '0000',
            'tipoSucursal' => '1',
            'datosTransaccion' => array_filter([
                'tipoEmision' => '01',
                'tipoDocumento' => $tipoDocumento, // 01 factura, 06 NC genérica, 07 ND genérica
                'numeroDocumentoFiscal' => (string) $numeroFiscal,
                'puntoFacturacionFiscal' => $config->punto_facturacion ?: '001',
                'fechaEmision' => now('America/Panama')->format('Y-m-d\TH:i:sP'),
                'naturalezaOperacion' => '01', // venta
                'tipoOperacion' => 1,          // salida
                'destinoOperacion' => 1,       // Panamá
                'formatoCAFE' => 3,            // ticket
                'entregaCAFE' => 3,
                'envioContenedor' => 1,
                'procesoGeneracion' => 1,
                'informacionInteres' => $datos['informacion_interes'] ?? null,
                'cliente' => $this->cliente($cliente, $datos),
            ], fn ($v) => $v !== null && $v !== ''),
            'listaItems' => ['item' => $items],
            'totalesSubTotales' => [
                'totalPrecioNeto' => $this->n2($totalNeto),
                'totalDescuento' => $this->n2($totalDescuento),
                'totalITBMS' => $this->n2($totalItbms),
                'totalMontoGravado' => $this->n2($totalItbms),
                'totalFactura' => $this->n2($totalFactura),
                'totalValorRecibido' => $this->n2($totalFactura),
                'vuelto' => '0.00',
                'tiempoPago' => '1', // contado
                'nroItems' => (string) count($items),
                'totalTodosItems' => $this->n2($totalFactura),
                'listaFormaPago' => [
                    'formaPago' => [[
                        'formaPagoFact' => $datos['forma_pago'] ?? '02',
                        'valorCuotaPagada' => $this->n2($totalFactura),
                    ]],
                ],
            ],
        ];

        return $documento;
    }

    /** Datos para consultar/anular/descargar un documento ya emitido. */
    public function datosDocumento(FelConfiguracion $config, string $numeroFiscal, string $tipoDocumento = '01'): array
    {
        return [
            'datosDocumento' => [
                'codigoSucursalEmisor' => $config->codigo_sucursal ?: '0000',
                'numeroDocumentoFiscal' => $numeroFiscal,
                'puntoFacturacionFiscal' => $config->punto_facturacion ?: '001',
                'tipoDocumento' => isset(self::TIPOS_DOCUMENTO[$tipoDocumento]) ? $tipoDocumento : '01',
                'tipoEmision' => '01',
            ],
        ];
    }

    private function cliente(?Contacto $cliente, array $datos): array
    {
        if ($cliente === null || empty($cliente->identificacion)) {
            // Consumidor final (sin RUC)
            return array_filter([
                'tipoClienteFE' => '02',
                'razonSocial' => $cliente->nombre ?? 'CONSUMIDOR FINAL',
                'direccion' => $cliente->direccion ?? 'Ciudad de Panamá',
                'pais' => 'PA',
                'correoElectronico1' => $cliente->email ?? null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        return array_filter([
            'tipoClienteFE' => '01', // contribuyente
            'tipoContribuyente' => $cliente->tipo_persona === 'NATURAL' ? 1 : 2,
            'numeroRUC' => $cliente->identificacion,
            'digitoVerificadorRUC' => $cliente->dv,
            'razonSocial' => $cliente->razon_social ?: $cliente->nombre,
            'direccion' => $cliente->direccion ?: 'Ciudad de Panamá',
            'codigoUbicacion' => $datos['codigo_ubicacion'] ?? '8-8-8',
            'provincia' => $datos['provincia'] ?? ($cliente->provincia ?: 'PANAMA'),
            'distrito' => $datos['distrito'] ?? ($cliente->distrito ?: 'PANAMA'),
            'corregimiento' => $datos['corregimiento'] ?? 'BELLA VISTA',
            'pais' => 'PA',
            'correoElectronico1' => $cliente->email ?: null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** @return array{0: array, 1: float, 2: float, 3: float} [items, totalNeto, totalItbms, totalDescuento] */
    private function items(array $lineas): array
    {
        $items = [];
        $totalNeto = 0.0;
        $totalItbms = 0.0;
        $totalDescuento = 0.0;

        foreach ($lineas as $l) {
            $cantidad = (float) $l['cantidad'];
            $precio = (float) $l['precio'];
            $tasa = $l['tasa'] ?? '01';
            $factor = self::TASAS_ITBMS[$tasa] ?? 0.07;

            // Descuento (monto total de la línea: descuento de línea + prorrateo
            // del descuento general). La base gravada es el bruto menos descuento.
            $descuento = round((float) ($l['descuento'] ?? 0), 2);
            $bruto = round($cantidad * $precio, 2);
            if ($descuento < 0) {
                $descuento = 0.0;
            }
            if ($descuento > $bruto) {
                $descuento = $bruto;
            }
            $precioItem = round($bruto - $descuento, 2); // base neta gravable
            $itbms = round($precioItem * $factor, 2);

            $items[] = array_filter([
                'descripcion' => $l['descripcion'],
                'codigo' => $l['codigo'] ?? null,
                'unidadMedida' => 'und',
                // CPBS obligatorio para la DGI; default 81/8111 = servicios informáticos
                'codigoCPBSAbrev' => $l['cpbs_abrev'] ?? '81',
                'codigoCPBS' => $l['cpbs'] ?? '8111',
                'cantidad' => $this->n($cantidad, 2),
                'precioUnitario' => $this->n($precio, 2),
                // Descuento por unidad (monto), solo si aplica.
                'precioUnitarioDescuento' => $descuento > 0 && $cantidad > 0
                    ? $this->n(round($descuento / $cantidad, 2), 2) : null,
                'precioItem' => $this->n($precioItem, 2),
                'valorTotal' => $this->n($precioItem + $itbms, 2),
                'tasaITBMS' => $tasa,
                'valorITBMS' => $this->n($itbms, 2),
            ], fn ($v) => $v !== null && $v !== '');

            $totalNeto += $precioItem;
            $totalItbms += $itbms;
            $totalDescuento += $descuento;
        }

        return [$items, round($totalNeto, 2), round($totalItbms, 2), round($totalDescuento, 2)];
    }

    private function n(float $v, int $dec): string
    {
        return number_format($v, $dec, '.', '');
    }

    private function n2(float $v): string
    {
        return $this->n($v, 2);
    }
}
