<?php

namespace App\Services;

use App\Models\Asiento;
use App\Models\AsientoDetalle;
use App\Models\AsientoRecurrente;
use App\Models\Diario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Convierte plantillas de asientos recurrentes en asientos cgl_* reales.
 *
 * Cada asiento se crea como BORRADOR (origen_modulo='CGL' → se comporta como
 * un asiento manual: editable / re-emitible / posteable por el contador) y se
 * estampa con origen_tabla='cgl_asientos_recurrentes' + origen_id para dejar
 * trazabilidad y garantizar idempotencia (no duplicar el mismo vencimiento).
 *
 * NO postea: la decisión de producto es que el contador revise y postee. El
 * posteo posterior pasa por el flujo normal (cuadre, bloqueo de cuentas de
 * control, período abierto, triggers de saldos), así que la generación nunca
 * compromete una fecha futura ni un período cerrado.
 */
class GeneradorAsientosRecurrentes
{
    public function __construct(private readonly string $actorPorDefecto = 'sistema:recurrentes') {}

    /**
     * Genera los vencimientos pendientes de TODAS las plantillas activas hasta
     * $hasta (normalmente hoy). Acotable a una compañía.
     *
     * @return array{plantillas:int, asientos:int}
     */
    public function generarPendientes(Carbon $hasta, ?int $companiaId = null, ?string $usuario = null): array
    {
        $totalAsientos = 0;
        $totalPlantillas = 0;

        AsientoRecurrente::query()
            ->where('estado', AsientoRecurrente::ESTADO_ACTIVA)
            ->whereDate('proxima_fecha', '<=', $hasta->toDateString())
            ->when($companiaId, fn ($q) => $q->where('compania_id', $companiaId))
            ->orderBy('id')
            ->each(function (AsientoRecurrente $plantilla) use ($hasta, $usuario, &$totalAsientos, &$totalPlantillas) {
                $creados = $this->generarPlantilla($plantilla, $hasta, $usuario);

                if ($creados > 0) {
                    $totalAsientos += $creados;
                    $totalPlantillas++;
                }
            });

        return ['plantillas' => $totalPlantillas, 'asientos' => $totalAsientos];
    }

    /**
     * Genera, en una sola transacción, todos los asientos vencidos de UNA
     * plantilla hasta $hasta, avanzando proxima_fecha y finalizándola si pasó
     * de sus límites (fecha_fin / nº de ocurrencias). Devuelve cuántos creó.
     */
    public function generarPlantilla(AsientoRecurrente $plantilla, Carbon $hasta, ?string $usuario = null): int
    {
        if (! $plantilla->esActiva()) {
            return 0;
        }

        $lineas = $plantilla->detalle()->get();

        // Plantilla incompleta o descuadrada: no se genera nada (se ignora en el
        // lote del cron; el usuario la corrige en su formulario).
        if ($lineas->count() < 2) {
            return 0;
        }

        $debito = round((float) $lineas->sum('debito'), 2);
        $credito = round((float) $lineas->sum('credito'), 2);

        if (abs($debito - $credito) > 0.004 || $debito <= 0) {
            return 0;
        }

        $usuario ??= $this->actorPorDefecto;
        $diarioId = $plantilla->diario_id ?? Diario::general($plantilla->compania_id, $usuario)->id;
        $creados = 0;

        DB::transaction(function () use ($plantilla, $hasta, $usuario, $lineas, $diarioId, $debito, $credito, &$creados) {
            $proxima = $plantilla->proxima_fecha->copy();

            while ($proxima->lte($hasta) && $plantilla->dentroDeLimites($proxima)) {
                if (! $this->yaGenerado($plantilla, $proxima)) {
                    $this->crearAsientoBorrador($plantilla, $proxima, $diarioId, $lineas, $debito, $credito, $usuario);
                    $creados++;
                    $plantilla->ocurrencias_generadas++;
                }

                $plantilla->ultima_generacion = $proxima->copy();
                $proxima = $plantilla->siguienteFecha($proxima);
            }

            $plantilla->proxima_fecha = $proxima;

            if (! $plantilla->dentroDeLimites($proxima)) {
                $plantilla->estado = AsientoRecurrente::ESTADO_FINALIZADA;
            }

            $plantilla->updated_by = $usuario;
            $plantilla->save();
        });

        return $creados;
    }

    /**
     * ¿Ya existe un asiento vigente generado para esta plantilla en esa fecha?
     * Garantiza idempotencia ante doble clic, cron + botón o reproceso.
     */
    private function yaGenerado(AsientoRecurrente $plantilla, Carbon $fecha): bool
    {
        return Asiento::query()
            ->where('compania_id', $plantilla->compania_id)
            ->where('origen_tabla', AsientoRecurrente::ORIGEN_TABLA)
            ->where('origen_id', $plantilla->id)
            ->whereDate('fecha', $fecha->toDateString())
            ->where('estado', '!=', Asiento::ESTADO_ANULADO)
            ->exists();
    }

    private function crearAsientoBorrador(
        AsientoRecurrente $plantilla,
        Carbon $fecha,
        int $diarioId,
        $lineas,
        float $debito,
        float $credito,
        string $usuario,
    ): void {
        $asiento = Asiento::create([
            'compania_id' => $plantilla->compania_id,
            'diario_id' => $diarioId,
            'numero' => Asiento::siguienteNumero($plantilla->compania_id),
            'fecha' => $fecha->toDateString(),
            'descripcion' => $plantilla->descripcion ?: $plantilla->nombre,
            'referencia' => $plantilla->referencia,
            'estado' => Asiento::ESTADO_BORRADOR,
            'origen_modulo' => 'CGL',
            'origen_tabla' => AsientoRecurrente::ORIGEN_TABLA,
            'origen_id' => $plantilla->id,
            'total_debito' => $debito,
            'total_credito' => $credito,
            'usuario_id' => $plantilla->usuario_id,
            'created_by' => $usuario,
        ]);

        foreach ($lineas->values() as $i => $linea) {
            AsientoDetalle::create([
                'asiento_id' => $asiento->id,
                'linea' => $i + 1,
                'cuenta_id' => $linea->cuenta_id,
                'contacto_id' => $linea->contacto_id,
                'descripcion' => $linea->descripcion,
                'debito' => $linea->debito,
                'credito' => $linea->credito,
                'tasa_cambio' => 1,
                'debito_local' => $linea->debito,
                'credito_local' => $linea->credito,
                'created_by' => $usuario,
            ]);
        }
    }
}
