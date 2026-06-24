<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Plantilla de ejemplo para importar ventas "propias" (no DGI) a Facturas de
 * venta. Encabezados en la primera fila + filas de muestra. La cuenta es de
 * ingreso (naturaleza acreedora); si se omite, cae en la cuenta default VENTAS.
 */
class VentasPlantillaExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @param  array<int, array{string,string}>  $cuentas  [codigo, nombre] de cuentas de ingreso reales para la muestra
     */
    public function __construct(private array $cuentas = []) {}

    public function headings(): array
    {
        return ['cliente', 'ruc', 'numero', 'fecha', 'concepto', 'cuenta', 'subtotal', 'itbms', 'tasa', 'vencimiento'];
    }

    public function array(): array
    {
        $cuenta1 = $this->cuentas[0][0] ?? '40101';
        $cuenta2 = $this->cuentas[1][0] ?? $cuenta1;

        return [
            ['ALMACENES EJEMPLO, S.A.', '12345-1-123456', '1001', '15/06/2026', 'Venta de mercancía', $cuenta1, '100.00', '7.00', '', '15/07/2026'],
            ['CLIENTE VARIOS, S.A.', '8-888-8888', '1002', '18/06/2026', 'Servicios prestados', $cuenta2, '250.00', '', '7', ''],
            ['ALMACENES EJEMPLO, S.A.', '12345-1-123456', '1003', '20/06/2026', 'Venta exenta', $cuenta1, '80.00', '', '0', ''],
        ];
    }
}
