<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Plantilla de ejemplo para importar saldos iniciales / asiento desde Excel.
 * Encabezados en la primera fila + un par de filas de muestra.
 */
class AsientoPlantillaExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /**
     * @param  array<int, array{string,string}>  $ejemplos  [codigo, nombre] de cuentas reales para la muestra
     */
    public function __construct(private array $ejemplos = []) {}

    public function headings(): array
    {
        return ['codigo', 'descripcion', 'debito', 'credito'];
    }

    public function array(): array
    {
        if ($this->ejemplos !== []) {
            return array_map(
                fn ($c) => [$c[0], 'Saldo inicial '.$c[1], '', ''],
                $this->ejemplos
            );
        }

        return [
            ['10101', 'Saldo inicial Caja', '1500.00', ''],
            ['10102', 'Saldo inicial Banco', '8500.00', ''],
            ['30101', 'Saldo inicial Capital', '', '10000.00'],
        ];
    }
}
