<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class CuentasImport implements ToCollection, WithStartRow
{
    /** @var array<int, array{codigo:string, nombre:string, tipo:string, naturaleza:string, codigo_padre:string, permite_movimiento:?bool, conciliable:bool, renglon_isr:?string}> */
    public array $filas = [];

    public function startRow(): int
    {
        return 2; // fila 1 = encabezados
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $codigo = trim((string) ($row[0] ?? ''));
            $nombre = trim((string) ($row[1] ?? ''));

            if ($codigo === '' || $nombre === '') {
                continue;
            }

            $this->filas[] = [
                'codigo'             => $codigo,
                'nombre'             => $nombre,
                'tipo'               => $this->parseTipo($row[2] ?? ''),
                'naturaleza'         => $this->parseNaturaleza($row[3] ?? ''),
                'codigo_padre'       => trim((string) ($row[4] ?? '')),
                'permite_movimiento' => $this->parseBoolOpcional($row[5] ?? null),
                'conciliable'        => $this->parseBool($row[6] ?? null),
                'renglon_isr'        => trim((string) ($row[7] ?? '')) ?: null,
            ];
        }
    }

    /**
     * Normaliza el tipo de cuenta al código de cgl_tipos_cuenta.
     * Acepta sinónimos comunes; cadena vacía si no se reconoce.
     */
    private function parseTipo(mixed $val): string
    {
        $v = strtoupper(trim((string) $val));

        return match (true) {
            str_starts_with($v, 'ACTIV')                              => 'ACTIVO',
            str_starts_with($v, 'PASIV')                              => 'PASIVO',
            str_starts_with($v, 'PATRIMON') || str_starts_with($v, 'CAPITAL') => 'PATRIMONIO',
            str_starts_with($v, 'INGRES')                             => 'INGRESO',
            str_starts_with($v, 'COSTO')                              => 'COSTO',
            str_starts_with($v, 'GASTO')                              => 'GASTO',
            default                                                   => '',
        };
    }

    /** DEBITO / CREDITO; cadena vacía si no se especifica (se deriva del tipo). */
    private function parseNaturaleza(mixed $val): string
    {
        $v = strtoupper(trim((string) $val));

        return match (true) {
            str_starts_with($v, 'DEB') || $v === 'DR'  => 'DEBITO',
            str_starts_with($v, 'CRED') || $v === 'CR' => 'CREDITO',
            default                                    => '',
        };
    }

    private function parseBool(mixed $val): bool
    {
        return in_array(strtoupper(trim((string) $val)), ['1', 'SI', 'SÍ', 'TRUE', 'X', 'Y', 'YES'], true);
    }

    /** Devuelve null cuando la celda está vacía (para poder calcular el default). */
    private function parseBoolOpcional(mixed $val): ?bool
    {
        $v = strtoupper(trim((string) $val));

        if ($v === '') {
            return null;
        }

        // "Título" / "No" => no permite movimiento
        return in_array($v, ['1', 'SI', 'SÍ', 'TRUE', 'X', 'Y', 'YES', 'MOVIMIENTO'], true);
    }
}
