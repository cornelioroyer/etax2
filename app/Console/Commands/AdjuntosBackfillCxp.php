<?php

namespace App\Console\Commands;

use App\Models\CxpDocumento;
use App\Services\AdjuntoService;
use Illuminate\Console\Command;

/**
 * Backfill: registra en core_adjuntos los archivos legados (archivo_path) de los
 * documentos de CxP. Idempotente (no duplica) y no toca S3: solo crea las filas
 * que falten apuntando al mismo storage_path. Reversible borrando created_by='backfill'.
 */
class AdjuntosBackfillCxp extends Command
{
    protected $signature = 'adjuntos:backfill-cxp {--compania= : Limitar a una compañía} {--dry-run : Solo contar, no escribir}';

    protected $description = 'Espeja los archivo_path de cxp_documentos a core_adjuntos (idempotente).';

    public function handle(AdjuntoService $servicio): int
    {
        $dry = (bool) $this->option('dry-run');

        $query = CxpDocumento::query()
            ->whereNotNull('archivo_path')
            ->when($this->option('compania'), fn ($q, $c) => $q->where('compania_id', $c));

        $total = (clone $query)->count();
        $this->info("Documentos CxP con archivo_path: {$total}".($dry ? ' (dry-run)' : ''));

        $creados = 0;
        $existentes = 0;

        $query->orderBy('id')->chunkById(200, function ($docs) use ($servicio, $dry, &$creados, &$existentes) {
            foreach ($docs as $doc) {
                if ($dry) {
                    $creados++;

                    continue;
                }

                $adj = $servicio->registrarExistente(
                    $doc->archivo_path,
                    $doc->archivo_disk,
                    'cxp_documentos',
                    $doc->id,
                    $doc->compania_id,
                    'CXP',
                );

                if ($adj && $adj->wasRecentlyCreated) {
                    $creados++;
                } else {
                    $existentes++;
                }
            }
        });

        if ($dry) {
            $this->info("Se crearían hasta {$creados} filas en core_adjuntos.");
        } else {
            $this->info("Creadas: {$creados} · ya existentes: {$existentes}.");
        }

        return self::SUCCESS;
    }
}
