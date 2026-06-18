<?php

namespace App\Services;

use App\Imports\CxpFacturasImport;
use App\Models\Contacto;
use App\Models\CuentaDefault;
use App\Models\CxpDocumento;
use App\Models\CxpDocumentoDetalle;
use App\Models\CxpImportacion;
use App\Models\TipoContacto;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Procesa una importación de compras (Excel "Documentos Electrónicos
 * Recibidos" de la DGI): por cada fila consulta la factura electrónica por su
 * CUFE en la DGI y crea la compra en BORRADOR con sus líneas reales. Reporta
 * el avance fila a fila sobre el registro CxpImportacion (lo lee la barra de
 * progreso de la UI).
 */
class ImportadorCxpFel
{
    private DgiFepConsulta $dgi;

    public function __construct(?DgiFepConsulta $dgi = null)
    {
        $this->dgi = $dgi ?? new DgiFepConsulta;
    }

    public function procesar(CxpImportacion $importacion): void
    {
        $import = new CxpFacturasImport;
        Excel::import($import, Storage::path($importacion->ruta));

        $importacion->update([
            'estado' => CxpImportacion::ESTADO_PROCESANDO,
            'total' => count($import->filas),
        ]);

        $companiaId = $importacion->compania_id;
        $usuarioEmail = $importacion->usuario;
        $cuentaGastoDefault = CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT');
        $tipoProveedor = TipoContacto::where('codigo', 'PROVEEDOR')->first();

        $procesadas = 0;
        $creadas = 0;
        $conDetalle = 0;
        $omitidas = 0;
        $errores = [];

        foreach ($import->filas as $i => $fila) {
            $procesadas++;
            $fila_num = $i + 3;

            $resultado = $this->procesarFila(
                $fila,
                $fila_num,
                $companiaId,
                $usuarioEmail,
                $cuentaGastoDefault,
                $tipoProveedor,
            );

            if ($resultado['error'] !== null) {
                $errores[] = $resultado['error'];
            } elseif ($resultado['omitida']) {
                $omitidas++;
            } else {
                $creadas++;
                if ($resultado['con_detalle']) {
                    $conDetalle++;
                }
            }

            $importacion->update([
                'procesadas' => $procesadas,
                'creadas' => $creadas,
                'con_detalle' => $conDetalle,
                'omitidas' => $omitidas,
                'errores' => array_slice($errores, 0, 50),
            ]);
        }

        $importacion->update([
            'estado' => CxpImportacion::ESTADO_COMPLETADO,
            'terminado_at' => now(),
        ]);
    }

    /**
     * @return array{error:?string,omitida:bool,con_detalle:bool}
     */
    private function procesarFila(
        array $fila,
        int $filaNum,
        int $companiaId,
        ?string $usuarioEmail,
        ?int $cuentaGastoDefault,
        ?TipoContacto $tipoProveedor,
    ): array {
        if (! $fila['fecha']) {
            return ['error' => "Fila {$filaNum}: fecha inválida.", 'omitida' => false, 'con_detalle' => false];
        }
        if ($fila['ruc'] === '') {
            return ['error' => "Fila {$filaNum}: RUC vacío.", 'omitida' => false, 'con_detalle' => false];
        }
        if ($fila['total'] <= 0) {
            return ['error' => "Fila {$filaNum}: monto inválido ({$fila['total']}).", 'omitida' => false, 'con_detalle' => false];
        }

        // Trae la factura real de la DGI por su CUFE (emisor, número y líneas).
        $dgi = $this->dgi->porCufe($fila['cufe']);

        $proveedor = Contacto::where('compania_id', $companiaId)
            ->where('identificacion', $fila['ruc'])
            ->first();

        if (! $proveedor) {
            $codigo = substr($fila['ruc'], 0, 50);
            if (Contacto::where('compania_id', $companiaId)->where('codigo', $codigo)->exists()) {
                $codigo = null;
            }

            $proveedor = Contacto::create([
                'compania_id' => $companiaId,
                'codigo' => $codigo,
                'nombre' => substr($fila['nombre'] ?: $fila['ruc'], 0, 200),
                'tipo_persona' => 'JURIDICA',
                'identificacion' => $fila['ruc'],
                'activo' => true,
                'cuenta_gasto_id' => $cuentaGastoDefault,
                'created_by' => $usuarioEmail,
            ]);

            if ($tipoProveedor) {
                $proveedor->tipos()->attach($tipoProveedor->id);
            }
        }

        // El CUFE es único global → mejor clave de deduplicación.
        $duplicada = CxpDocumento::where('compania_id', $companiaId)
            ->where('cufe', $fila['cufe'])
            ->exists();

        if ($duplicada) {
            return ['error' => null, 'omitida' => true, 'con_detalle' => false];
        }

        $cuentaId = $proveedor->cuenta_gasto_id ?? $cuentaGastoDefault;

        // Número de documento real del emisor si la DGI lo devolvió; si no, CUFE truncado.
        $numero = $dgi['numero'] ?? substr($fila['cufe'], 0, 50);
        $subtotal = $dgi['subtotal'] ?? $fila['subtotal'];
        $itbms = $dgi['itbms'] ?? $fila['itbms'];
        $total = $dgi['total'] ?? $fila['total'];

        $factura = CxpDocumento::create([
            'compania_id' => $companiaId,
            'proveedor_id' => $proveedor->id,
            'tipo_documento' => CxpDocumento::TIPO_FACTURA,
            'numero' => $numero,
            'cufe' => $fila['cufe'],
            'fecha' => $fila['fecha'],
            'subtotal' => $subtotal,
            'descuento' => 0,
            'impuesto' => $itbms,
            'total' => $total,
            'saldo' => $total,
            'estado' => CxpDocumento::ESTADO_BORRADOR,
            'created_by' => $usuarioEmail,
        ]);

        $conDetalle = false;

        if ($dgi && ! empty($dgi['lineas'])) {
            // Líneas reales de la factura electrónica.
            foreach ($dgi['lineas'] as $n => $linea) {
                CxpDocumentoDetalle::create([
                    'documento_id' => $factura->id,
                    'linea' => $n + 1,
                    'descripcion' => substr($linea['descripcion'], 0, 500),
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'descuento' => $linea['descuento'],
                    'impuesto_monto' => $linea['itbms'],
                    'total_linea' => $linea['total'],
                    'cuenta_id' => $cuentaId,
                    'created_by' => $usuarioEmail,
                ]);
            }
            $conDetalle = true;
        } else {
            // Sin respuesta de la DGI: una sola línea con los totales del Excel.
            CxpDocumentoDetalle::create([
                'documento_id' => $factura->id,
                'linea' => 1,
                'descripcion' => substr($fila['nombre'] ?: $fila['tipo'], 0, 500),
                'cantidad' => 1,
                'precio_unitario' => $subtotal,
                'impuesto_monto' => $itbms,
                'total_linea' => $total,
                'cuenta_id' => $cuentaId,
                'created_by' => $usuarioEmail,
            ]);
        }

        return ['error' => null, 'omitida' => false, 'con_detalle' => $conDetalle];
    }
}
