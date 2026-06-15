<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Lee un Excel/CSV de saldos iniciales para armar las líneas de un asiento.
 * Columnas esperadas (encabezado en la primera fila, sin distinguir acentos
 * ni mayúsculas): codigo, descripcion (opcional), debito, credito.
 *
 * No toca la BD: solo normaliza las filas. El controlador resuelve cada
 * código contra el catálogo y crea el asiento.
 */
class AsientoSaldosImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array{fila:int, codigo:string, descripcion:?string, debito:float, credito:float}> */
    public array $lineas = [];

    public function collection(Collection $filas): void
    {
        foreach ($filas as $i => $fila) {
            $codigo = trim((string) $this->valor($fila, ['codigo', 'cuenta', 'codigo_cuenta', 'cuenta_codigo']));
            $descripcion = trim((string) $this->valor($fila, ['descripcion', 'concepto', 'detalle', 'glosa']));
            $debito = $this->numero($this->valor($fila, ['debito', 'debe', 'deudor']));
            $credito = $this->numero($this->valor($fila, ['credito', 'haber', 'acreedor']));

            // Salta filas completamente vacías (sin código ni montos).
            if ($codigo === '' && $debito == 0.0 && $credito == 0.0) {
                continue;
            }

            $this->lineas[] = [
                'fila' => $i + 2, // +1 base 0, +1 encabezado: número de fila real en el Excel
                'codigo' => $codigo,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'debito' => $debito,
                'credito' => $credito,
            ];
        }
    }

    /**
     * Primer valor no nulo entre varias claves posibles del encabezado.
     */
    private function valor($fila, array $claves)
    {
        foreach ($claves as $clave) {
            if (isset($fila[$clave]) && $fila[$clave] !== null && $fila[$clave] !== '') {
                return $fila[$clave];
            }
        }

        return null;
    }

    /**
     * Convierte un valor de celda a float tolerando separadores de miles,
     * coma decimal y signos. Vacío => 0.
     */
    private function numero($valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        $texto = trim((string) $valor);

        // Quita símbolo de moneda y espacios.
        $texto = str_replace(['B/.', 'B/', '$', ' '], '', $texto);

        // Si usa coma como decimal (1.234,56) normaliza a punto.
        if (str_contains($texto, ',') && (! str_contains($texto, '.') || strrpos($texto, ',') > strrpos($texto, '.'))) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } else {
            $texto = str_replace(',', '', $texto);
        }

        return is_numeric($texto) ? round((float) $texto, 2) : 0.0;
    }
}
