<?php

namespace App\Jobs;

use App\Models\VentasImportacion;
use App\Services\ImportadorVentasFel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcesarImportacionVentasFel implements ShouldQueue
{
    use Queueable;

    /** Las consultas a la DGI son secuenciales y lentas; damos margen amplio. */
    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $importacionId) {}

    public function handle(ImportadorVentasFel $importador): void
    {
        $importacion = VentasImportacion::find($this->importacionId);
        if (! $importacion) {
            return;
        }

        $importador->procesar($importacion);
    }

    public function failed(Throwable $e): void
    {
        $importacion = VentasImportacion::find($this->importacionId);
        $importacion?->update([
            'estado'        => VentasImportacion::ESTADO_FALLIDO,
            'mensaje_error' => substr($e->getMessage(), 0, 1000),
            'terminado_at'  => now(),
        ]);
    }
}
