<?php

namespace App\Services;

use App\Models\CxpDocumento;
use App\Models\CxpDocumentoDetalle;
use App\Models\CxpRecurrente;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Convierte plantillas de facturas recurrentes (cxp_recurrentes) en facturas de
 * proveedor reales (cxp_documentos tipo FACTURA, estado BORRADOR).
 *
 * El borrador entra al ciclo de vida normal de CxP: aparece en "Facturas de
 * Compras" y el contador lo revisa y pulsa "Contabilizar", que postea
 * Dr contrapartida + Dr ITBMS / Cr CxP (cuenta de control), alimenta el submayor
 * del proveedor y deja el documento PENDIENTE. La generación NO contabiliza:
 * nunca compromete una fecha futura ni un período cerrado.
 *
 * Cada documento se estampa con recurrente_id para trazabilidad e idempotencia
 * (no duplicar el mismo vencimiento ante doble clic, cron + botón o reproceso).
 */
class GeneradorCxpRecurrentes
{
    public function __construct(private readonly string $actorPorDefecto = 'sistema:cxp-recurrentes') {}

    /**
     * Genera los vencimientos pendientes de TODAS las plantillas activas hasta
     * $hasta (normalmente hoy). Acotable a una compañía.
     *
     * @return array{plantillas:int, facturas:int}
     */
    public function generarPendientes(Carbon $hasta, ?int $companiaId = null, ?string $usuario = null): array
    {
        $totalFacturas = 0;
        $totalPlantillas = 0;

        CxpRecurrente::query()
            ->where('estado', CxpRecurrente::ESTADO_ACTIVA)
            ->whereDate('proxima_fecha', '<=', $hasta->toDateString())
            ->when($companiaId, fn ($q) => $q->where('compania_id', $companiaId))
            ->orderBy('id')
            ->each(function (CxpRecurrente $plantilla) use ($hasta, $usuario, &$totalFacturas, &$totalPlantillas) {
                $creadas = $this->generarPlantilla($plantilla, $hasta, $usuario);

                if ($creadas > 0) {
                    $totalFacturas += $creadas;
                    $totalPlantillas++;
                }
            });

        return ['plantillas' => $totalPlantillas, 'facturas' => $totalFacturas];
    }

    /**
     * Genera, en una sola transacción, todas las facturas vencidas de UNA
     * plantilla hasta $hasta, avanzando proxima_fecha y finalizándola si pasó
     * de sus límites (fecha_fin / nº de ocurrencias). Devuelve cuántas creó.
     */
    public function generarPlantilla(CxpRecurrente $plantilla, Carbon $hasta, ?string $usuario = null): int
    {
        if (! $plantilla->esActiva()) {
            return 0;
        }

        $lineas = $plantilla->detalle()->get();

        // Plantilla sin líneas o con total no positivo: no se genera nada (se
        // ignora en el lote del cron; el usuario la corrige en su formulario).
        if ($lineas->isEmpty()) {
            return 0;
        }

        $calculo = $this->calcular($lineas);

        if ($calculo['total'] <= 0) {
            return 0;
        }

        $usuario ??= $this->actorPorDefecto;
        $creadas = 0;

        DB::transaction(function () use ($plantilla, $hasta, $usuario, $lineas, $calculo, &$creadas) {
            $proxima = $plantilla->proxima_fecha->copy();

            while ($proxima->lte($hasta) && $plantilla->dentroDeLimites($proxima)) {
                if (! $this->yaGenerado($plantilla, $proxima)) {
                    $this->crearBorrador($plantilla, $proxima, $lineas, $calculo, $usuario);
                    $creadas++;
                    $plantilla->ocurrencias_generadas++;
                }

                $plantilla->ultima_generacion = $proxima->copy();
                $proxima = $plantilla->siguienteFecha($proxima);
            }

            $plantilla->proxima_fecha = $proxima;

            if (! $plantilla->dentroDeLimites($proxima)) {
                $plantilla->estado = CxpRecurrente::ESTADO_FINALIZADA;
            }

            $plantilla->updated_by = $usuario;
            $plantilla->save();
        });

        return $creadas;
    }

    /**
     * ¿Ya existe una factura vigente generada para esta plantilla en esa fecha?
     * Garantiza idempotencia ante doble clic, cron + botón o reproceso.
     */
    private function yaGenerado(CxpRecurrente $plantilla, Carbon $fecha): bool
    {
        return CxpDocumento::query()
            ->where('compania_id', $plantilla->compania_id)
            ->where('recurrente_id', $plantilla->id)
            ->whereDate('fecha', $fecha->toDateString())
            ->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)
            ->exists();
    }

    private function crearBorrador(
        CxpRecurrente $plantilla,
        Carbon $fecha,
        $lineas,
        array $calculo,
        string $usuario,
    ): void {
        $vencimiento = $fecha->copy()->addDays(max(0, (int) $plantilla->dias_credito));

        $factura = CxpDocumento::create([
            'compania_id' => $plantilla->compania_id,
            'proveedor_id' => $plantilla->proveedor_id,
            'tipo_documento' => CxpDocumento::TIPO_FACTURA,
            // Número provisional único por plantilla+fecha; editable: el contador
            // puede sustituirlo por el número real del proveedor antes de contabilizar.
            'numero' => 'R'.$plantilla->id.'-'.$fecha->format('Ymd'),
            'referencia' => $plantilla->referencia,
            'fecha' => $fecha->toDateString(),
            'fecha_vencimiento' => $vencimiento->toDateString(),
            'subtotal' => $calculo['subtotal'],
            'descuento' => 0,
            'impuesto' => $calculo['impuesto'],
            'total' => $calculo['total'],
            'saldo' => $calculo['total'],
            'estado' => CxpDocumento::ESTADO_BORRADOR,
            'recurrente_id' => $plantilla->id,
            'usuario_id' => $plantilla->usuario_id ?? null,
            'created_by' => $usuario,
        ]);

        foreach ($calculo['lineas'] as $linea) {
            CxpDocumentoDetalle::create($linea + [
                'documento_id' => $factura->id,
                'created_by' => $usuario,
            ]);
        }
    }

    /**
     * Calcula líneas normalizadas y totales (subtotal/ITBMS/total), con la misma
     * regla que el formulario de facturas: base = cantidad × precio, ITBMS por tasa.
     *
     * @return array{lineas: array<int, array<string, mixed>>, subtotal: float, impuesto: float, total: float}
     */
    private function calcular($lineas): array
    {
        $out = [];
        $subtotal = 0.0;
        $impuesto = 0.0;

        foreach ($lineas->values() as $i => $linea) {
            $cantidad = round((float) $linea->cantidad, 4);
            $precio = round((float) $linea->precio_unitario, 4);
            $base = round($cantidad * $precio, 2);
            $itbms = round($base * ((int) $linea->tasa_itbms) / 100, 2);

            $subtotal += $base;
            $impuesto += $itbms;

            $out[] = [
                'linea' => $i + 1,
                'item_id' => $linea->item_id ? (int) $linea->item_id : null,
                'descripcion' => $linea->descripcion,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'impuesto_monto' => $itbms,
                'total_linea' => round($base + $itbms, 2),
                'cuenta_id' => (int) $linea->cuenta_id,
            ];
        }

        $subtotal = round($subtotal, 2);
        $impuesto = round($impuesto, 2);

        return [
            'lineas' => $out,
            'subtotal' => $subtotal,
            'impuesto' => $impuesto,
            'total' => round($subtotal + $impuesto, 2),
        ];
    }
}
