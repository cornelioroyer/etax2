<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Parsea el reporte DGI "Documentos Electrónicos Emitidos" (.xlsx).
 *
 * Columnas (base 0):
 *   0 = CUFE
 *   1 = Tipo (Factura de Operación Interna / Nota de Crédito Genérica)
 *   3 = Fecha Emisión
 *   5 = RUC receptor
 *   6 = Nombre receptor
 *   7 = Subtotal
 *   8 = ITBMS
 *   9 = Monto (total)
 *  14 = Tiempo de Pago
 */
class VentasFacturasImport implements ToCollection, WithStartRow
{
    /** @var array<int, array{cufe:string,tipo:string,fecha:?string,ruc:string,nombre:string,subtotal:float,itbms:float,total:float,tiempo_pago:string}> */
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

            $tipo = trim((string) ($row[1] ?? ''));
            $esFactura = $tipo === 'Factura de Operación Interna';
            $esNota    = $tipo === 'Nota de Crédito Genérica';
            if (! $esFactura && ! $esNota) {
                continue;
            }

            $subtotal = $this->parseMonto($row[7] ?? 0);
            $itbms    = $this->parseMonto($row[8] ?? 0);

            $this->filas[] = [
                'cufe'        => $cufe,
                'tipo'        => $tipo,
                'es_nota'     => $esNota,
                'fecha'       => $this->parseFecha($row[3] ?? null),
                'ruc'         => trim((string) ($row[5] ?? '')),
                'nombre'      => trim((string) ($row[6] ?? '')),
                'subtotal'    => $subtotal,
                'itbms'       => $itbms,
                'total'       => round($subtotal + $itbms, 2),
                'tiempo_pago' => trim((string) ($row[14] ?? '')),
            ];
        }
    }

    private function parseFecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $texto = (string) $valor;

        // Formato DGI: DD/MM/YYYY HH:MM:SS o DD/MM/YYYY
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

    private function parseMonto(mixed $valor): float
    {
        $limpio = str_replace([',', ' ', 'B/.', 'B/'], '', trim((string) $valor));

        return round((float) $limpio, 2);
    }
}
