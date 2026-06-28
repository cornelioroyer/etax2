<?php

namespace App\Jobs;

use App\Models\Restauracion;
use App\Services\RestaurarCompania;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RestaurarCompaniaJob implements ShouldQueue
{
    use Queueable;

    /** Restauraciones grandes pueden tardar; damos margen amplio. */
    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $restauracionId) {}

    public function handle(RestaurarCompania $servicio): void
    {
        $rest = Restauracion::find($this->restauracionId);
        if (! $rest) {
            return;
        }

        $servicio->restaurar($rest);
    }

    public function failed(Throwable $e): void
    {
        $rest = Restauracion::find($this->restauracionId);
        if (! $rest) {
            return;
        }

        // Limpiar el zip temporal subido si quedó.
        if ($rest->archivo_tmp && str_contains((string) $rest->archivo_tmp, 'restauraciones-tmp')) {
            @unlink($rest->archivo_tmp);
        }

        $rest->update([
            'estado' => Restauracion::ESTADO_FALLIDO,
            'mensaje_error' => substr($e->getMessage(), 0, 1000),
            'terminado_at' => now(),
        ]);
    }
}
