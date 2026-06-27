<?php

namespace App\Jobs;

use App\Models\Respaldo;
use App\Services\RespaldoCompania;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerarRespaldoCompania implements ShouldQueue
{
    use Queueable;

    /** Compañías grandes pueden tardar; damos margen amplio. */
    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $respaldoId) {}

    public function handle(RespaldoCompania $servicio): void
    {
        $respaldo = Respaldo::find($this->respaldoId);
        if (! $respaldo) {
            return;
        }

        $servicio->generar($respaldo);
    }

    public function failed(Throwable $e): void
    {
        $respaldo = Respaldo::find($this->respaldoId);
        $respaldo?->update([
            'estado' => Respaldo::ESTADO_FALLIDO,
            'mensaje_error' => substr($e->getMessage(), 0, 1000),
            'terminado_at' => now(),
        ]);
    }
}
