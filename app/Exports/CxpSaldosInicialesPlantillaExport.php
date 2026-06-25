<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Plantilla de ejemplo para importar SALDOS INICIALES de proveedores, factura por
 * factura, a Cuentas por Pagar. Encabezados en la primera fila + filas de muestra.
 * El `monto` es el saldo pendiente (lo que aún se le debe al proveedor); no se
 * desglosa ITBMS porque el crédito fiscal ya se tomó en el sistema anterior.
 */
class CxpSaldosInicialesPlantillaExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return ['proveedor', 'ruc', 'numero', 'fecha', 'vencimiento', 'monto', 'tipo', 'concepto'];
    }

    public function array(): array
    {
        return [
            ['DISTRIBUIDORA MODELO, S.A.', '12345-1-123456', 'F-001', '15/05/2026', '15/06/2026', '1200.00', 'FACTURA', 'Saldo pendiente al corte'],
            ['SERVICIOS VARIOS, S.A.', '8-888-8888', 'F-205', '28/05/2026', '27/06/2026', '450.00', 'FACTURA', 'Saldo pendiente al corte'],
            ['DISTRIBUIDORA MODELO, S.A.', '12345-1-123456', 'NC-010', '30/05/2026', '', '80.00', 'NC', 'Nota de crédito a favor pendiente'],
        ];
    }
}
