<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Plantilla de ejemplo para importar pagos a proveedores a Cuentas por Pagar.
 * Encabezados en la primera fila + filas de muestra. `numero` es la factura que
 * se paga; `cuenta` el código de la cuenta de banco/caja; filas con el mismo
 * proveedor + fecha + cuenta + referencia forman un solo pago.
 */
class CxpPagosPlantillaExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @param  array<int, array{string,string}>  $cuentas  [codigo, nombre] de cuentas de banco/caja reales para la muestra
     */
    public function __construct(private array $cuentas = []) {}

    public function headings(): array
    {
        return ['proveedor', 'ruc', 'numero', 'fecha', 'monto', 'cuenta', 'referencia'];
    }

    public function array(): array
    {
        $cuenta1 = $this->cuentas[0][0] ?? '10201';
        $cuenta2 = $this->cuentas[1][0] ?? $cuenta1;

        return [
            // Un pago que cancela dos facturas (mismo proveedor + fecha + cuenta + referencia).
            ['DISTRIBUIDORA MODELO, S.A.', '12345-1-123456', 'F-001', '25/06/2026', '100.00', $cuenta1, 'CHQ-0501'],
            ['DISTRIBUIDORA MODELO, S.A.', '12345-1-123456', 'F-002', '25/06/2026', '50.00', $cuenta1, 'CHQ-0501'],
            // Otro pago, otro proveedor, por transferencia.
            ['SERVICIOS VARIOS, S.A.', '8-888-8888', 'F-205', '25/06/2026', '250.00', $cuenta2, 'TRF-99812'],
        ];
    }
}
