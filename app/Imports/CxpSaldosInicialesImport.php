<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Lee un Excel/CSV de SALDOS INICIALES de proveedores, factura por factura, para
 * abrir cada documento pendiente en Cuentas por Pagar. A diferencia del import de
 * compras del período, aquí NO se reconoce gasto ni ITBMS de nuevo (ya ocurrió en
 * el sistema anterior): cada fila trae el MONTO ADEUDADO (saldo pendiente) y la
 * contrapartida del asiento es una cuenta de apertura/patrimonio que el usuario
 * elige en el formulario.
 *
 * Encabezados esperados en la primera fila, tolerantes a sinónimos, mayúsculas y
 * acentos:
 *
 *   proveedor | ruc | numero | fecha | vencimiento | monto | tipo | concepto
 *
 * No toca la BD: solo normaliza las filas. El controlador resuelve proveedor y
 * cuentas contra la compañía y crea cada documento ya contabilizado.
 */
class CxpSaldosInicialesImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<string, mixed>> */
    public array $filas = [];

    public function collection(Collection $filas): void
    {
        foreach ($filas as $i => $fila) {
            $proveedor = trim((string) $this->valor($fila, ['proveedor', 'nombre', 'razon_social', 'razón_social']));
            $numero    = trim((string) $this->valor($fila, ['numero', 'número', 'nro', 'no', 'documento', 'factura']));
            $monto     = $this->numero($this->valor($fila, ['monto', 'saldo', 'saldo_pendiente', 'pendiente', 'total', 'importe', 'valor', 'adeudado']));

            // Salta filas completamente vacías.
            if ($proveedor === '' && $numero === '' && $monto == 0.0) {
                continue;
            }

            $this->filas[] = [
                'fila'        => $i + 2, // +1 base 0, +1 encabezado: fila real en el Excel
                'proveedor'   => $proveedor,
                'ruc'         => trim((string) $this->valor($fila, ['ruc', 'ruc_proveedor', 'identificacion', 'identificación', 'cedula', 'cédula'])),
                'numero'      => $numero,
                'fecha'       => $this->parseFecha($this->valor($fila, ['fecha', 'fecha_emision', 'fecha_documento'])),
                'vencimiento' => $this->parseFecha($this->valor($fila, ['vencimiento', 'fecha_vencimiento', 'vence'])),
                'monto'       => $monto,
                'tipo'        => trim((string) $this->valor($fila, ['tipo', 'tipo_documento'])),
                'concepto'    => trim((string) $this->valor($fila, ['concepto', 'descripcion', 'descripción', 'detalle', 'glosa'])),
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
        $texto = str_replace(['B/.', 'B/', '$', ' '], '', $texto);

        if (str_contains($texto, ',') && (! str_contains($texto, '.') || strrpos($texto, ',') > strrpos($texto, '.'))) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } else {
            $texto = str_replace(',', '', $texto);
        }

        return is_numeric($texto) ? round((float) $texto, 2) : 0.0;
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
