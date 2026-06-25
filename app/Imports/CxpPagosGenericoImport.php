<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Lee un Excel/CSV de pagos a proveedores para registrarlos en Cuentas por Pagar.
 * Encabezados esperados en la primera fila, tolerantes a sinónimos, mayúsculas y
 * acentos:
 *
 *   proveedor | ruc | numero | fecha | monto | cuenta | referencia
 *
 * Donde `numero` es el número de la FACTURA (o ND/reembolso) que se está pagando
 * y `cuenta` es el código de la cuenta de banco/caja con la que se paga. Filas con
 * el mismo proveedor + fecha + cuenta + referencia forman un solo pago (PG) con
 * varias facturas aplicadas.
 *
 * No toca la BD: solo normaliza las filas. El controlador resuelve proveedor,
 * factura y cuenta contra la compañía, agrupa por pago y los registra.
 */
class CxpPagosGenericoImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<string, mixed>> */
    public array $filas = [];

    public function collection(Collection $filas): void
    {
        foreach ($filas as $i => $fila) {
            $proveedor = trim((string) $this->valor($fila, ['proveedor', 'nombre', 'razon_social', 'razón_social']));
            $ruc       = trim((string) $this->valor($fila, ['ruc', 'ruc_proveedor', 'identificacion', 'identificación', 'cedula', 'cédula']));
            $numero    = trim((string) $this->valor($fila, ['numero', 'número', 'nro', 'no', 'documento', 'factura', 'factura_numero']));
            $monto     = $this->numero($this->valor($fila, ['monto', 'pago', 'importe', 'valor', 'abono', 'monto_pagado']));

            // Salta filas completamente vacías.
            if ($proveedor === '' && $ruc === '' && $numero === '' && $monto == 0.0) {
                continue;
            }

            $this->filas[] = [
                'fila'       => $i + 2, // +1 base 0, +1 encabezado: fila real en el Excel
                'proveedor'  => $proveedor,
                'ruc'        => $ruc,
                'numero'     => $numero,
                'fecha'      => $this->parseFecha($this->valor($fila, ['fecha', 'fecha_pago', 'fecha_documento'])),
                'monto'      => $monto,
                'cuenta'     => trim((string) $this->valor($fila, ['cuenta', 'cuenta_pago', 'cuenta_banco', 'codigo_cuenta', 'código_cuenta', 'cuenta_contable'])),
                'referencia' => trim((string) $this->valor($fila, ['referencia', 'cheque', 'numero_cheque', 'transferencia', 'comprobante', 'ref'])),
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
