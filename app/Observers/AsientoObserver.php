<?php

namespace App\Observers;

use App\Models\Asiento;
use App\Services\BancoSync;

/**
 * Mantiene el módulo de Bancos sincronizado con la contabilidad: cuando un
 * asiento se postea o anula, refleja/retira los movimientos bancarios de las
 * cuentas contables enlazadas a un banco.
 *
 * Crear el BcoMovimiento NO genera ningún asiento, así que no hay recursión.
 */
class AsientoObserver
{
    public function __construct(private BancoSync $bancoSync) {}

    public function created(Asiento $asiento): void
    {
        // Por si algún flujo crea el asiento ya posteado de una vez.
        if ($asiento->esPosteado()) {
            $this->bancoSync->sincronizar($asiento);
        }
    }

    public function updated(Asiento $asiento): void
    {
        if (! $asiento->wasChanged('estado')) {
            return;
        }

        if ($asiento->estado === Asiento::ESTADO_POSTEADO) {
            $this->bancoSync->sincronizar($asiento);
        } elseif ($asiento->estado === Asiento::ESTADO_ANULADO) {
            $this->bancoSync->revertir($asiento);
        }
    }
}
