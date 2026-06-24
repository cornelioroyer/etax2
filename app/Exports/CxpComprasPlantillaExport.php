<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Plantilla de ejemplo para importar compras "propias" (no DGI) a Cuentas por
 * Pagar. Encabezados en la primera fila + filas de muestra.
 */
class CxpComprasPlantillaExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @param  array<int, array{string,string}>  $cuentas  [codigo, nombre] de cuentas de gasto reales para la muestra
     */
    public function __construct(private array $cuentas = []) {}

    public function headings(): array
    {
        return ['proveedor', 'ruc', 'numero', 'fecha', 'tipo', 'concepto', 'cuenta', 'subtotal', 'itbms', 'tasa', 'vencimiento'];
    }

    public function array(): array
    {
        $cuenta1 = $this->cuentas[0][0] ?? '60101';
        $cuenta2 = $this->cuentas[1][0] ?? $cuenta1;

        return [
            ['DISTRIBUIDORA MODELO, S.A.', '12345-1-123456', 'F-001', '15/06/2026', 'FACTURA', 'Compra de mercancía', $cuenta1, '100.00', '7.00', '', '15/07/2026'],
            ['SERVICIOS VARIOS, S.A.', '8-888-8888', 'F-205', '18/06/2026', 'FACTURA', 'Servicios profesionales', $cuenta2, '250.00', '', '7', ''],
            ['DISTRIBUIDORA MODELO, S.A.', '12345-1-123456', 'NC-010', '20/06/2026', 'NC', 'Devolución parcial', $cuenta1, '30.00', '2.10', '', ''],
        ];
    }
}
