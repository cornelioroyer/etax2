<?php

namespace App\Services;

use App\Models\Asiento;
use App\Models\BcoCuenta;
use App\Models\BcoMovimiento;
use Illuminate\Validation\ValidationException;

/**
 * Integración inversa Contabilidad → Bancos.
 *
 * Cuando un asiento posteado toca una cuenta contable que está enlazada a una
 * cuenta del módulo de Bancos, refleja esa línea como un BcoMovimiento para
 * que el saldo del módulo de Bancos no se descuadre contra el mayor.
 *
 * Convención de signos (¡invertida entre los dos mundos!):
 *   - Débito al banco en el mayor (el activo SUBE)  → credito en bco_movimientos (ingreso)
 *   - Crédito al banco en el mayor (el activo BAJA) → debito  en bco_movimientos (egreso)
 *
 * El saldo de Bancos se calcula SUM(credito) - SUM(debito) (ver BcoCuenta).
 */
class BancoSync
{
    public const ORIGEN = 'cgl_asientos';

    /**
     * Refleja en Bancos las líneas de un asiento posteado que tocan una cuenta
     * bancaria. Idempotente: no duplica si el movimiento ya existe.
     */
    public function sincronizar(Asiento $asiento): void
    {
        // Solo asientos posteados. Los de origen BANCOS ya tienen su movimiento
        // (el movimiento es justamente quien generó el asiento).
        if (! $asiento->esPosteado() || $asiento->origen_modulo === 'BANCOS') {
            return;
        }

        $asiento->loadMissing('detalle');

        // Cuentas bancarias de la compañía, agrupadas por su cuenta contable.
        $cuentasPorGl = BcoCuenta::where('compania_id', $asiento->compania_id)
            ->whereNotNull('cuenta_contable_id')
            ->get()
            ->groupBy('cuenta_contable_id');

        foreach ($asiento->detalle as $linea) {
            $bancos = $cuentasPorGl->get($linea->cuenta_id);

            // La cuenta no es de banco, o el enlace es ambiguo (varias cuentas
            // bancarias comparten la misma cuenta contable) → no se puede
            // determinar a qué banco va; se omite.
            if (! $bancos || $bancos->count() !== 1) {
                continue;
            }

            $banco = $bancos->first();

            // Idempotencia: ya está reflejado para este asiento y banco.
            $yaExiste = BcoMovimiento::where('asiento_id', $asiento->id)
                ->where('cuenta_bancaria_id', $banco->id)
                ->exists();

            if ($yaExiste) {
                continue;
            }

            $debitoGl = round((float) $linea->debito, 2);
            $creditoGl = round((float) $linea->credito, 2);

            if ($debitoGl <= 0 && $creditoGl <= 0) {
                continue;
            }

            BcoMovimiento::create([
                'compania_id' => $asiento->compania_id,
                'cuenta_bancaria_id' => $banco->id,
                'fecha' => $asiento->fecha,
                'tipo_movimiento' => BcoMovimiento::TIPO_ASIENTO,
                'descripcion' => $linea->descripcion ?: ($asiento->descripcion ?: "Asiento {$asiento->numero}"),
                'referencia' => $asiento->numero,
                'debito' => $creditoGl,   // crédito GL → egreso de banco
                'credito' => $debitoGl,   // débito GL → ingreso de banco
                'contacto_id' => $linea->contacto_id,
                'conciliado' => false,
                'asiento_id' => $asiento->id,
                'documento_origen' => self::ORIGEN,
                'documento_id' => $asiento->id,
                'created_by' => $asiento->updated_by ?: $asiento->created_by,
                'updated_by' => $asiento->updated_by ?: $asiento->created_by,
            ]);
        }
    }

    /**
     * Quita de Bancos los movimientos generados por un asiento (al anularlo).
     * Si alguno ya fue conciliado, bloquea la operación.
     *
     * Llamar dentro de una transacción para que el bloqueo revierta la anulación.
     */
    public function revertir(Asiento $asiento): void
    {
        $movimientos = BcoMovimiento::where('asiento_id', $asiento->id)
            ->where('documento_origen', self::ORIGEN)
            ->get();

        if ($movimientos->isEmpty()) {
            return;
        }

        if ($movimientos->contains('conciliado', true)) {
            throw ValidationException::withMessages([
                'asiento' => "No se puede anular: el movimiento bancario del asiento {$asiento->numero} ya fue conciliado.",
            ]);
        }

        BcoMovimiento::whereIn('id', $movimientos->pluck('id'))->delete();
    }
}
