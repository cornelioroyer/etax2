<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

/**
 * Lee filas crudas de la plantilla de Productos y Servicios.
 *
 * No toca la base de datos: solo normaliza el texto de cada fila. La
 * resolución de categorías, unidades, cuentas e ITBMS (que depende de la
 * compañía activa) y el guardado se hacen en ItemProductoController::importar().
 *
 * Orden de columnas (fila 1 = encabezados, se omite):
 *  0 codigo | 1 nombre | 2 tipo | 3 descripcion | 4 categoria | 5 unidad
 *  6 precio_venta | 7 costo | 8 cuenta_ingreso | 9 cuenta_gasto | 10 itbms
 */
class ItemsImport implements ToCollection, WithStartRow
{
    /** @var array<int, array<string, mixed>> */
    public array $filas = [];

    public function startRow(): int
    {
        return 2; // fila 1 = encabezados
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $nombre = trim((string) ($row[1] ?? ''));
            $codigo = trim((string) ($row[0] ?? ''));

            // Fila vacía: ni código ni nombre.
            if ($nombre === '' && $codigo === '') {
                continue;
            }

            $this->filas[] = [
                'codigo'         => $codigo,
                'nombre'         => $nombre,
                'tipo'           => $this->parseTipo($row[2] ?? ''),
                'descripcion'    => trim((string) ($row[3] ?? '')),
                'categoria'      => trim((string) ($row[4] ?? '')),
                'unidad'         => trim((string) ($row[5] ?? '')),
                'precio_venta'   => $this->parseMonto($row[6] ?? null),
                'costo'          => $this->parseMonto($row[7] ?? null),
                'cuenta_ingreso' => trim((string) ($row[8] ?? '')),
                'cuenta_gasto'   => trim((string) ($row[9] ?? '')),
                'itbms'          => $this->parseItbms($row[10] ?? null),
            ];
        }
    }

    private function parseTipo(mixed $val): string
    {
        $v = strtoupper(trim((string) $val));

        return str_starts_with($v, 'S') ? 'SERVICIO' : 'PRODUCTO';
    }

    /** Convierte texto numérico (admite coma decimal y separadores de miles) a float >= 0. */
    private function parseMonto(mixed $val): float
    {
        $texto = trim((string) $val);
        if ($texto === '') {
            return 0.0;
        }

        // Quita símbolos de moneda y espacios; normaliza separadores.
        $texto = preg_replace('/[^\d,.\-]/', '', $texto) ?? '';

        // Si hay coma y punto, asume el último como decimal y el otro como miles.
        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            $texto = strrpos($texto, ',') > strrpos($texto, '.')
                ? str_replace('.', '', $texto)            // 1.234,56 -> 1234,56
                : str_replace(',', '', $texto);           // 1,234.56 -> 1234.56
        }
        $texto = str_replace(',', '.', $texto);

        return max(0.0, round((float) $texto, 4));
    }

    /**
     * Devuelve el porcentaje de ITBMS (0/7/10/15) o null si la celda está vacía
     * (para que el controlador aplique el default). "EXENTO" => 0.
     */
    private function parseItbms(mixed $val): ?int
    {
        $texto = strtoupper(trim((string) $val));
        if ($texto === '') {
            return null;
        }
        if (str_contains($texto, 'EXENT')) {
            return 0;
        }

        // Toma el primer entero presente (admite "7%", "ITBMS 7", "7.00").
        if (preg_match('/\d+/', $texto, $m)) {
            return (int) $m[0];
        }

        return null;
    }
}
