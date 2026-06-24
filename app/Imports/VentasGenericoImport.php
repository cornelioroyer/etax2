<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Lee un Excel/CSV de ventas "propias" (NO del portal DGI) para registrarlas en
 * Facturas de venta. Encabezados esperados en la primera fila, tolerantes a
 * sinónimos, mayúsculas y acentos:
 *
 *   cliente | ruc | numero | fecha | concepto | cuenta |
 *   subtotal | itbms | tasa | vencimiento
 *
 * No toca la BD: solo normaliza las filas. El controlador resuelve cliente y
 * cuentas contra la compañía, agrupa por documento y crea las facturas.
 */
class VentasGenericoImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<string, mixed>> */
    public array $filas = [];

    public function collection(Collection $filas): void
    {
        foreach ($filas as $i => $fila) {
            $cliente  = trim((string) $this->valor($fila, ['cliente', 'nombre', 'razon_social', 'razón_social']));
            $numero   = trim((string) $this->valor($fila, ['numero', 'número', 'nro', 'no', 'documento', 'factura']));
            $subtotal = $this->numero($this->valor($fila, ['subtotal', 'base', 'monto', 'importe', 'valor']));
            $itbms    = $this->numero($this->valor($fila, ['itbms', 'impuesto', 'iva', 'itbm']));
            $tasa     = $this->numero($this->valor($fila, ['tasa', 'tasa_itbms', 'porcentaje', '%']));

            // Salta filas completamente vacías.
            if ($cliente === '' && $numero === '' && $subtotal == 0.0 && $itbms == 0.0) {
                continue;
            }

            $this->filas[] = [
                'fila'        => $i + 2, // +1 base 0, +1 encabezado: fila real en el Excel
                'cliente'     => $cliente,
                'ruc'         => trim((string) $this->valor($fila, ['ruc', 'ruc_cliente', 'identificacion', 'identificación', 'cedula', 'cédula'])),
                'numero'      => $numero,
                'fecha'       => $this->parseFecha($this->valor($fila, ['fecha', 'fecha_emision', 'fecha_documento'])),
                'concepto'    => trim((string) $this->valor($fila, ['concepto', 'descripcion', 'descripción', 'detalle', 'glosa'])),
                'cuenta'      => trim((string) $this->valor($fila, ['cuenta', 'cuenta_ingreso', 'codigo_cuenta', 'código_cuenta', 'cuenta_contable'])),
                'subtotal'    => $subtotal,
                'itbms'       => $itbms,
                'tasa'        => $tasa,
                'vencimiento' => $this->parseFecha($this->valor($fila, ['vencimiento', 'fecha_vencimiento', 'vence'])),
            ];
        }
    }

    /** Primer valor no nulo entre varias claves posibles del encabezado. */
    private function valor($fila, array $claves)
    {
        foreach ($claves as $clave) {
            if (isset($fila[$clave]) && $fila[$clave] !== null && $fila[$clave] !== '') {
                return $fila[$clave];
            }
        }

        return null;
    }

    /** Convierte un valor de celda a float tolerando moneda, miles y coma decimal. */
    private function numero($valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        $texto = trim((string) $valor);
        $texto = str_replace(['B/.', 'B/', '$', ' ', '%'], '', $texto);

        if (str_contains($texto, ',') && (! str_contains($texto, '.') || strrpos($texto, ',') > strrpos($texto, '.'))) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } else {
            $texto = str_replace(',', '', $texto);
        }

        return is_numeric($texto) ? (float) $texto : 0.0;
    }

    /** Acepta dd/mm/yyyy, yyyy-mm-dd, o serial de Excel. Devuelve Y-m-d o null. */
    private function parseFecha($valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $texto = trim((string) $valor);

        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $texto, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $texto, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        if (is_numeric($valor)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $valor)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
