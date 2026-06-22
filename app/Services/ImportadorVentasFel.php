<?php

namespace App\Services;

use App\Imports\VentasFacturasImport;
use App\Models\Contacto;
use App\Models\User;
use App\Models\CuentaDefault;
use App\Models\CxcDocumento;
use App\Models\CxcDocumentoDetalle;
use App\Models\ItemProducto;
use App\Models\TaxImpuesto;
use App\Models\TipoContacto;
use App\Models\VentaFactura;
use App\Models\VentaFacturaDetalle;
use App\Models\VentaNotaCredito;
use App\Models\VentasImportacion;
use App\Services\RucDigitoVerificador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Procesa una importación de ventas (Excel "Documentos Electrónicos Emitidos"
 * de la DGI): por cada fila consulta la factura electrónica por su CUFE en la
 * DGI, crea la factura EMITIDA con sus líneas reales y genera el asiento
 * contable. Reporta el avance fila a fila sobre el registro VentasImportacion.
 */
class ImportadorVentasFel
{
    private DgiFepConsulta $dgi;

    public function __construct(?DgiFepConsulta $dgi = null)
    {
        $this->dgi = $dgi ?? new DgiFepConsulta;
    }

    public function procesar(VentasImportacion $importacion): void
    {
        $import = new VentasFacturasImport;
        $ext  = strtolower(pathinfo($importacion->ruta, PATHINFO_EXTENSION));
        $tipo = $ext === 'xls' ? \Maatwebsite\Excel\Excel::XLS : \Maatwebsite\Excel\Excel::XLSX;
        Excel::import($import, Storage::path($importacion->ruta), null, $tipo);

        $importacion->update([
            'estado' => VentasImportacion::ESTADO_PROCESANDO,
            'total'  => count($import->filas),
        ]);

        $companiaId   = $importacion->compania_id;
        $usuarioEmail = $importacion->usuario;
        $usuario      = User::where('email', $usuarioEmail)->first();

        $cuentaCxcId    = CuentaDefault::idPara($companiaId, 'CXC');
        $cuentaItbmsId  = CuentaDefault::idPara($companiaId, 'ITBMS_POR_PAGAR');
        $cuentaVentasId = CuentaDefault::idPara($companiaId, 'VENTAS');
        $tipoCliente    = TipoContacto::where('codigo', 'CLIENTE')->first();
        $impuestosGlobales = TaxImpuesto::itbmsGlobales();

        $procesadas = 0;
        $creadas    = 0;
        $conDetalle = 0;
        $omitidas   = 0;
        $errores    = [];

        foreach ($import->filas as $i => $fila) {
            $procesadas++;
            $filaNum = $i + 3;

            $resultado = $this->procesarFila(
                $fila, $filaNum,
                $companiaId, $usuarioEmail, $usuario,
                $cuentaCxcId, $cuentaItbmsId, $cuentaVentasId,
                $tipoCliente, $impuestosGlobales,
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
                'creadas'    => $creadas,
                'con_detalle'=> $conDetalle,
                'omitidas'   => $omitidas,
                'errores'    => array_slice($errores, 0, 50),
            ]);
        }

        $importacion->update([
            'estado'       => VentasImportacion::ESTADO_COMPLETADO,
            'terminado_at' => now(),
        ]);
    }

    /** @return array{error:?string,omitida:bool,con_detalle:bool} */
    private function procesarFila(
        array $fila,
        int $filaNum,
        int $companiaId,
        ?string $usuarioEmail,
        ?User $usuario,
        ?int $cuentaCxcId,
        ?int $cuentaItbmsId,
        ?int $cuentaVentasId,
        ?TipoContacto $tipoCliente,
        $impuestosGlobales,
    ): array {
        $ninguno = ['error' => null, 'omitida' => false, 'con_detalle' => false];

        if (! $fila['fecha']) {
            return ['error' => "Fila {$filaNum}: fecha inválida.", 'omitida' => false, 'con_detalle' => false];
        }
        if ($fila['ruc'] === '') {
            return ['error' => "Fila {$filaNum}: RUC vacío.", 'omitida' => false, 'con_detalle' => false];
        }
        if ($fila['total'] <= 0) {
            return ['error' => "Fila {$filaNum}: monto inválido ({$fila['total']}).", 'omitida' => false, 'con_detalle' => false];
        }

        // Deduplicación por CUFE (único global para cada FEL)
        if ($fila['es_nota']) {
            $duplicada = VentaNotaCredito::where('compania_id', $companiaId)
                ->where('motivo', 'like', "%{$fila['cufe']}%")
                ->exists();
        } else {
            $duplicada = VentaFactura::where('compania_id', $companiaId)
                ->where('cufe', $fila['cufe'])
                ->exists();
        }

        if ($duplicada) {
            return ['error' => null, 'omitida' => true, 'con_detalle' => false];
        }

        // Consulta DGI solo para facturas (las NC no tienen líneas en la DGI)
        $dgi = $fila['es_nota'] ? null : $this->dgi->porCufe($fila['cufe']);

        // Busca/crea el cliente por RUC
        $receptor = $dgi['receptor'] ?? [];
        $cliente  = Contacto::where('compania_id', $companiaId)->where('codigo', $fila['ruc'])->first();

        // Si el cliente ya existe respeta su forma de pago; si es nuevo usa CREDITO
        $formaPago = $cliente?->forma_pago ?? 'CREDITO';

        if (! $cliente) {
            $cliente = Contacto::create([
                'compania_id'    => $companiaId,
                'codigo'         => $fila['ruc'],
                'nombre'         => substr($receptor['nombre'] ?? $fila['nombre'], 0, 200),
                'tipo_persona'   => 'JURIDICA',
                'identificacion' => $fila['ruc'],
                'dv'             => $receptor['dv'] ?? RucDigitoVerificador::calcular($fila['ruc']),
                'direccion'      => $receptor['direccion'] ?? null,
                'telefono'       => isset($receptor['telefono']) ? substr($receptor['telefono'], 0, 50) : null,
                'forma_pago'     => $formaPago,
                'activo'         => true,
                'created_by'     => $usuarioEmail,
            ]);
            if ($tipoCliente) {
                $cliente->tipos()->sync([$tipoCliente->id]);
            }
        }

        $conDetalle = false;

        try {
            DB::transaction(function () use (
                $fila, $dgi, $companiaId, $usuarioEmail, $usuario, $cliente,
                $cuentaCxcId, $cuentaItbmsId, $cuentaVentasId,
                $impuestosGlobales, &$conDetalle,
            ) {
                if ($fila['es_nota']) {
                    $this->crearNotaCredito(
                        $fila, $companiaId, $usuarioEmail, $usuario, $cliente,
                        $cuentaCxcId, $cuentaVentasId,
                    );
                } else {
                    $conDetalle = $this->crearFactura(
                        $fila, $dgi, $companiaId, $usuarioEmail, $usuario, $cliente,
                        $cuentaCxcId, $cuentaItbmsId, $cuentaVentasId,
                        $impuestosGlobales,
                    );
                }
            });
        } catch (\Throwable $e) {
            return ['error' => "Fila {$filaNum}: ".$e->getMessage(), 'omitida' => false, 'con_detalle' => false];
        }

        return ['error' => null, 'omitida' => false, 'con_detalle' => $conDetalle];
    }

    /**
     * Crea VentaFactura + VentaFacturaDetalle + CxcDocumento + CxcDocumentoDetalle + Asiento.
     * Devuelve true si se obtuvieron líneas reales de la DGI.
     */
    private function crearFactura(
        array $fila,
        ?array $dgi,
        int $companiaId,
        ?string $usuarioEmail,
        ?User $usuario,
        Contacto $cliente,
        ?int $cuentaCxcId,
        ?int $cuentaItbmsId,
        ?int $cuentaVentasId,
        $impuestosGlobales,
    ): bool {
        $impuestoPara = function (float $base, float $itbms) use ($impuestosGlobales) {
            $tasa = $base > 0 ? (int) round($itbms / $base * 100) : 0;

            return $impuestosGlobales->first(fn ($t) => (int) round((float) $t->porcentaje) === $tasa)
                ?? $impuestosGlobales->firstWhere('porcentaje', 0)
                ?? $impuestosGlobales->first();
        };

        $lineasCalc = [];
        $conDetalle = false;

        if ($dgi && ! empty($dgi['lineas'])) {
            $conDetalle = true;
            foreach ($dgi['lineas'] as $n => $l) {
                $itbmsLinea = round((float) $l['itbms'], 2);
                $baseLinea  = round((float) $l['total'] - $itbmsLinea, 2);
                $cantidad   = (float) $l['cantidad'] ?: 1.0;
                $precio     = (float) $l['precio_unitario'];
                if ($precio == 0.0 && $cantidad != 0.0) {
                    $precio = round($baseLinea / $cantidad, 4);
                }
                $imp  = $impuestoPara($baseLinea, $itbmsLinea);
                $item = $this->resolverItem(
                    (string) ($l['codigo'] ?? ''), (string) $l['descripcion'],
                    $precio, $imp?->id, $companiaId, $cuentaVentasId, $usuarioEmail,
                );
                $lineasCalc[] = [
                    'linea'             => $n + 1,
                    'item_id'           => $item?->id,
                    'descripcion'       => substr((string) $l['descripcion'] ?: 'Sin descripción', 0, 500),
                    'cantidad'          => $cantidad,
                    'precio_unitario'   => $precio,
                    'descuento'         => round((float) ($l['descuento'] ?? 0), 2),
                    'impuesto_id'       => $imp?->id,
                    'impuesto_monto'    => $itbmsLinea,
                    'base'              => $baseLinea,
                    'total_linea'       => round((float) $l['total'], 2),
                    'cuenta_ingreso_id' => $item?->cuenta_ingreso_id ?? $cuentaVentasId,
                ];
            }
        } else {
            $imp = $impuestoPara($fila['subtotal'], $fila['itbms']);
            $lineasCalc[] = [
                'linea'             => 1,
                'item_id'           => null,
                'descripcion'       => 'Servicios',
                'cantidad'          => 1.0,
                'precio_unitario'   => $fila['subtotal'],
                'descuento'         => 0,
                'impuesto_id'       => $imp?->id,
                'impuesto_monto'    => $fila['itbms'],
                'base'              => $fila['subtotal'],
                'total_linea'       => $fila['total'],
                'cuenta_ingreso_id' => $cuentaVentasId,
            ];
        }

        $subtotalDoc = round(array_sum(array_column($lineasCalc, 'base')), 2);
        $itbmsDoc    = round(array_sum(array_column($lineasCalc, 'impuesto_monto')), 2);
        $totalDoc    = round($subtotalDoc + $itbmsDoc, 2);

        // Número real del emisor (la propia compañía) si la DGI lo devolvió y no está usado
        $numeroDgi = $dgi['numero'] ?? null;
        $numero    = ($numeroDgi !== null && ! $this->numeroExiste($companiaId, $numeroDgi))
            ? $numeroDgi
            : VentaFactura::siguienteNumero($companiaId);

        $factura = VentaFactura::create([
            'compania_id' => $companiaId,
            'cliente_id'  => $cliente->id,
            'numero'      => $numero,
            'cufe'        => $fila['cufe'],
            'fecha'       => $fila['fecha'],
            'subtotal'    => $subtotalDoc,
            'descuento'   => 0,
            'itbms'       => $itbmsDoc,
            'total'       => $totalDoc,
            'saldo'       => $totalDoc,
            'estado'      => VentaFactura::ESTADO_EMITIDA,
            'created_by'  => $usuarioEmail,
        ]);

        foreach ($lineasCalc as $l) {
            VentaFacturaDetalle::create([
                'factura_id'        => $factura->id,
                'linea'             => $l['linea'],
                'item_id'           => $l['item_id'],
                'descripcion'       => $l['descripcion'],
                'cantidad'          => $l['cantidad'],
                'precio_unitario'   => $l['precio_unitario'],
                'descuento'         => $l['descuento'],
                'impuesto_id'       => $l['impuesto_id'],
                'impuesto_monto'    => $l['impuesto_monto'],
                'total_linea'       => $l['total_linea'],
                'cuenta_ingreso_id' => $l['cuenta_ingreso_id'],
                'created_by'        => $usuarioEmail,
            ]);
        }

        $cxc = CxcDocumento::create([
            'compania_id'    => $companiaId,
            'cliente_id'     => $cliente->id,
            'tipo_documento' => CxcDocumento::TIPO_FACTURA,
            'numero'         => $numero,
            'fecha'          => $fila['fecha'],
            'subtotal'       => $subtotalDoc,
            'descuento'      => 0,
            'impuesto'       => $itbmsDoc,
            'total'          => $totalDoc,
            'saldo'          => $totalDoc,
            'estado'         => CxcDocumento::ESTADO_PENDIENTE,
            'created_by'     => $usuarioEmail,
        ]);

        foreach ($lineasCalc as $l) {
            CxcDocumentoDetalle::create([
                'documento_id'    => $cxc->id,
                'linea'           => $l['linea'],
                'item_id'         => $l['item_id'],
                'descripcion'     => $l['descripcion'],
                'cantidad'        => $l['cantidad'],
                'precio_unitario' => $l['precio_unitario'],
                'descuento'       => $l['descuento'],
                'impuesto_monto'  => $l['impuesto_monto'],
                'total_linea'     => $l['total_linea'],
                'cuenta_id'       => $l['cuenta_ingreso_id'],
                'created_by'      => $usuarioEmail,
            ]);
        }

        $lineasAsiento = [
            ['cuenta_id' => $cuentaCxcId, 'contacto_id' => $cliente->id, 'descripcion' => "Factura {$numero}", 'debito' => $totalDoc, 'credito' => 0],
        ];
        foreach ($lineasCalc as $l) {
            $lineasAsiento[] = ['cuenta_id' => $l['cuenta_ingreso_id'], 'descripcion' => substr($l['descripcion'], 0, 255), 'debito' => 0, 'credito' => $l['base']];
        }
        if ($itbmsDoc > 0 && $cuentaItbmsId) {
            $lineasAsiento[] = ['cuenta_id' => $cuentaItbmsId, 'descripcion' => "ITBMS factura {$numero}", 'debito' => 0, 'credito' => $itbmsDoc];
        }

        $asiento = app(AsientoAutomatico::class)->postear(
            $companiaId, $fila['fecha'],
            "Factura de venta {$numero} — {$cliente->nombre}",
            $numero, $lineasAsiento, 'CXC', 'ventas_facturas', $factura->id,
            $usuario,
        );

        $factura->update(['cxc_documento_id' => $cxc->id, 'asiento_id' => $asiento->id]);
        $cxc->update(['asiento_id' => $asiento->id]);

        return $conDetalle;
    }

    /** Crea VentaNotaCredito + CxcDocumento + Asiento. */
    private function crearNotaCredito(
        array $fila,
        int $companiaId,
        ?string $usuarioEmail,
        ?User $usuario,
        Contacto $cliente,
        ?int $cuentaCxcId,
        ?int $cuentaVentasId,
    ): void {
        $total  = $fila['total'];
        $numero = VentaNotaCredito::siguienteNumero($companiaId);

        $cxcNota = CxcDocumento::create([
            'compania_id'    => $companiaId,
            'cliente_id'     => $cliente->id,
            'tipo_documento' => CxcDocumento::TIPO_NOTA_CREDITO,
            'numero'         => $numero,
            'fecha'          => $fila['fecha'],
            'subtotal'       => $total,
            'impuesto'       => 0,
            'total'          => $total,
            'saldo'          => $total,
            'estado'         => CxcDocumento::ESTADO_PENDIENTE,
            'created_by'     => $usuarioEmail,
        ]);

        $nota = VentaNotaCredito::create([
            'compania_id'      => $companiaId,
            'cliente_id'       => $cliente->id,
            'numero'           => $numero,
            'fecha'            => $fila['fecha'],
            'motivo'           => "FEL: {$fila['cufe']}",
            'total'            => $total,
            'cxc_documento_id' => $cxcNota->id,
            'estado'           => VentaNotaCredito::ESTADO_EMITIDA,
            'created_by'       => $usuarioEmail,
            'updated_by'       => $usuarioEmail,
        ]);

        $asiento = app(AsientoAutomatico::class)->postear(
            $companiaId, $fila['fecha'],
            "NC Ventas {$numero} — {$cliente->nombre}",
            $numero,
            [
                ['cuenta_id' => $cuentaVentasId, 'descripcion' => "Nota crédito {$numero}", 'debito' => $total, 'credito' => 0],
                ['cuenta_id' => $cuentaCxcId, 'contacto_id' => $cliente->id, 'descripcion' => "Nota crédito {$numero}", 'debito' => 0, 'credito' => $total],
            ],
            'VENTAS', 'ventas_facturas', $nota->id,
            $usuario,
        );

        $nota->update(['asiento_id' => $asiento->id]);
    }

    private function resolverItem(
        string $codigo,
        string $descripcion,
        float $precio,
        ?int $impuestoId,
        int $companiaId,
        ?int $cuentaVentasId,
        ?string $usuarioEmail,
    ): ?ItemProducto {
        $codigo = substr(trim($codigo), 0, 100);
        if ($codigo === '') {
            return null;
        }

        $item = ItemProducto::where('compania_id', $companiaId)->where('codigo', $codigo)->first();
        if ($item) {
            return $item;
        }

        return ItemProducto::create([
            'compania_id'       => $companiaId,
            'codigo'            => $codigo,
            'nombre'            => substr($descripcion !== '' ? $descripcion : $codigo, 0, 200),
            'tipo'              => ItemProducto::TIPO_SERVICIO,
            'precio_venta'      => $precio,
            'impuesto_id'       => $impuestoId,
            'cuenta_ingreso_id' => $cuentaVentasId,
            'activo'            => true,
            'created_by'        => $usuarioEmail,
        ]);
    }

    private function numeroExiste(int $companiaId, string $numero): bool
    {
        return VentaFactura::where('compania_id', $companiaId)->where('numero', $numero)->exists()
            || CxcDocumento::where('compania_id', $companiaId)->where('numero', $numero)->exists();
    }
}
