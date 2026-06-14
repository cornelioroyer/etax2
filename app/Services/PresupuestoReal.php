<?php

namespace App\Services;

use App\Models\Asiento;
use App\Models\BudgetPresupuesto;
use App\Models\PeriodoContable;
use Illuminate\Support\Facades\DB;

/**
 * Calcula el monto real (ejecutado) de un presupuesto a partir de los
 * asientos POSTEADOS de la compañía y lo compara contra lo presupuestado.
 *
 * Real por línea = movimiento neto de la cuenta en el/los período(s) de la
 * línea, expresado según la naturaleza de la cuenta:
 *   - DEBITO  (deudora):  Σ(debito_local − credito_local)
 *   - CREDITO (acreedora): Σ(credito_local − debito_local)
 *
 * Si la línea fija período y/o dimensiones (centro de costo, departamento,
 * proyecto), el real se restringe a ellos; si están en blanco, se agregan
 * todos los movimientos del año del presupuesto.
 */
class PresupuestoReal
{
    /**
     * Recalcula monto_real / variacion / porcentaje_variacion de todas las
     * líneas del presupuesto. Devuelve la cantidad de líneas actualizadas.
     */
    public function calcular(BudgetPresupuesto $presupuesto): int
    {
        $companiaId = $presupuesto->compania_id;

        $presupuesto->loadMissing('detalle');
        if ($presupuesto->detalle->isEmpty()) {
            return 0;
        }

        // Naturaleza (DEBITO/CREDITO) de cada cuenta involucrada.
        $cuentaIds = $presupuesto->detalle->pluck('cuenta_id')->unique()->all();
        $naturalezas = DB::table('cgl_cuentas')
            ->whereIn('id', $cuentaIds)
            ->pluck('naturaleza', 'id');

        // Períodos del año del presupuesto (para las líneas sin período fijo).
        $periodoIdsDelAnio = PeriodoContable::where('compania_id', $companiaId)
            ->where('anio', $presupuesto->anio)
            ->pluck('id')
            ->all();

        $actualizadas = 0;

        DB::transaction(function () use ($presupuesto, $companiaId, $naturalezas, $periodoIdsDelAnio, &$actualizadas) {
            foreach ($presupuesto->detalle as $d) {
                $q = DB::table('cgl_asientos_detalle as ad')
                    ->join('cgl_asientos as a', 'a.id', '=', 'ad.asiento_id')
                    ->where('a.compania_id', $companiaId)
                    ->where('a.estado', Asiento::ESTADO_POSTEADO)
                    ->where('ad.cuenta_id', $d->cuenta_id);

                if ($d->periodo_id) {
                    $q->where('a.periodo_id', $d->periodo_id);
                } elseif (! empty($periodoIdsDelAnio)) {
                    $q->whereIn('a.periodo_id', $periodoIdsDelAnio);
                } else {
                    $q->whereRaw('1 = 0'); // año sin períodos: real = 0
                }

                foreach (['centro_costo_id', 'departamento_id', 'proyecto_id'] as $dim) {
                    if ($d->{$dim}) {
                        $q->where("ad.{$dim}", $d->{$dim});
                    }
                }

                $row = $q->selectRaw('COALESCE(SUM(ad.debito_local),0) AS deb, COALESCE(SUM(ad.credito_local),0) AS cred')->first();

                $naturaleza = $naturalezas[$d->cuenta_id] ?? 'DEBITO';
                $real = $naturaleza === 'CREDITO'
                    ? ((float) $row->cred - (float) $row->deb)
                    : ((float) $row->deb - (float) $row->cred);
                $real = round($real, 2);

                $presupuestado = round((float) $d->monto_presupuestado, 2);
                $variacion = round($real - $presupuestado, 2);
                $porcentaje = $presupuestado != 0.0
                    ? round($variacion / abs($presupuestado) * 100, 4)
                    : null;

                $d->update([
                    'monto_real'           => $real,
                    'variacion'            => $variacion,
                    'porcentaje_variacion' => $porcentaje,
                ]);

                $actualizadas++;
            }
        });

        return $actualizadas;
    }
}
