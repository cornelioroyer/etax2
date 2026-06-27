<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Plantilla de ejemplo para importar cobros recibidos de clientes a Cuentas por
 * Cobrar (Ventas → Recibos de cobro). Encabezados en la primera fila + filas de
 * muestra. `numero` es la factura de venta que se cobra; `cuenta` el código de la
 * cuenta de banco/caja donde se deposita; filas con el mismo cliente + fecha +
 * cuenta + referencia forman un solo recibo.
 */
class CobrosPlantillaExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @param  array<int, array{string,string}>  $cuentas  [codigo, nombre] de cuentas de banco/caja reales para la muestra
     */
    public function __construct(private array $cuentas = []) {}

    public function headings(): array
    {
        return ['cliente', 'ruc', 'numero', 'fecha', 'monto', 'cuenta', 'referencia'];
    }

    public function array(): array
    {
        $cuenta1 = $this->cuentas[0][0] ?? '10201';
        $cuenta2 = $this->cuentas[1][0] ?? $cuenta1;

        return [
            // Un cobro que cancela dos facturas (mismo cliente + fecha + cuenta + referencia).
            ['COMERCIAL EL SOL, S.A.', '12345-1-123456', 'F-001', '25/06/2026', '100.00', $cuenta1, 'DEP-0501'],
            ['COMERCIAL EL SOL, S.A.', '12345-1-123456', 'F-002', '25/06/2026', '50.00', $cuenta1, 'DEP-0501'],
            // Otro cobro, otro cliente, por transferencia.
            ['INVERSIONES DEL ISTMO, S.A.', '8-888-8888', 'F-205', '25/06/2026', '250.00', $cuenta2, 'TRF-99812'],
        ];
    }
}
