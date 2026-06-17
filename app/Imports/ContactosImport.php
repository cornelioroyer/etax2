<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class ContactosImport implements ToCollection, WithStartRow
{
    /** @var array<int, array{nombre:string, razon_social:string, tipo_persona:string, identificacion:string, dv:string, email:string, telefono:string, direccion:string, tipos_raw:string}> */
    public array $filas = [];

    public function startRow(): int
    {
        return 2; // fila 1 = encabezados
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $nombre = trim((string) ($row[0] ?? ''));
            if ($nombre === '') {
                continue;
            }

            $this->filas[] = [
                'nombre'         => $nombre,
                'razon_social'   => trim((string) ($row[1] ?? '')),
                'tipo_persona'   => $this->parseTipoPersona($row[2] ?? ''),
                'identificacion' => trim((string) ($row[3] ?? '')),
                'dv'             => trim((string) ($row[4] ?? '')),
                'email'          => trim((string) ($row[5] ?? '')),
                'telefono'       => trim((string) ($row[6] ?? '')),
                'direccion'      => trim((string) ($row[7] ?? '')),
                'tipos_raw'      => strtoupper(trim((string) ($row[8] ?? ''))),
                'saldo'          => round((float) ($row[9] ?? 0), 2),
                'fecha_saldo'    => $this->parseFecha($row[10] ?? null),
            ];
        }
    }

    private function parseTipoPersona(mixed $val): string
    {
        $v = strtoupper(trim((string) $val));

        return in_array($v, ['NATURAL', 'JURIDICA', 'EXTRANJERO']) ? $v : 'NATURAL';
    }

    private function parseFecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $texto = trim((string) $valor);

        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $texto, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $texto)) {
            return $texto;
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
