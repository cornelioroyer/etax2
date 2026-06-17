<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class CxpFacturasImport implements ToCollection, WithStartRow
{
    /** @var array<int, array{cufe:string, tipo:string, fecha:?string, ruc:string, nombre:string, subtotal:float, itbms:float, total:float}> */
    public array $filas = [];

    public function startRow(): int
    {
        return 3; // fila 1 = título, fila 2 = encabezados, fila 3+ = datos
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $cufe = trim((string) ($row[0] ?? ''));
            if ($cufe === '') {
                continue;
            }

            $this->filas[] = [
                'cufe'     => $cufe,
                'tipo'     => trim((string) ($row[1] ?? '')),
                'fecha'    => $this->parseFecha($row[2] ?? null),
                'ruc'      => trim((string) ($row[4] ?? '')),
                'nombre'   => trim((string) ($row[5] ?? '')),
                'subtotal' => round((float) ($row[6] ?? 0), 2),
                'itbms'    => round((float) ($row[7] ?? 0), 2),
                'total'    => round((float) ($row[8] ?? 0), 2),
            ];
        }
    }

    private function parseFecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $texto = (string) $valor;

        if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $texto, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        if (is_numeric($valor)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $valor)->format('Y-m-d');
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }
}
