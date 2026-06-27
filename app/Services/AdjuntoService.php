<?php

namespace App\Services;

use App\Models\Adjunto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Servicio central de adjuntos: sube, registra y elimina archivos en el disco de
 * adjuntos (config('filesystems.adjuntos'), normalmente S3) y los liga a un
 * registro de origen en `core_adjuntos`. Es la pieza reutilizable que deben usar
 * todos los módulos en lugar de las columnas inline archivo_path/archivo_disk.
 */
class AdjuntoService
{
    /** Extensiones permitidas (validar también en el FormRequest del módulo). */
    public const EXTENSIONES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    /** Tamaño máximo en kilobytes (10 MB). */
    public const MAX_KB = 10240;

    private function disco(): string
    {
        return config('filesystems.adjuntos', 's3');
    }

    /**
     * Sube un archivo y crea su fila en core_adjuntos.
     *
     * @param  mixed  $usuario  Usuario autenticado (para usuario_id/created_by).
     */
    public function guardar(UploadedFile $file, string $tablaOrigen, int $registroId, int $companiaId, string $modulo, $usuario): Adjunto
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $ext = strtolower($file->getClientOriginalExtension() ?: $this->extDeMime($file->getMimeType() ?: ''));

        return $this->guardarBytes(
            bytes: $bytes,
            nombreOriginal: $file->getClientOriginalName() ?: ('archivo.'.$ext),
            extension: $ext,
            mime: $file->getMimeType() ?: 'application/octet-stream',
            tablaOrigen: $tablaOrigen,
            registroId: $registroId,
            companiaId: $companiaId,
            modulo: $modulo,
            usuario: $usuario,
        );
    }

    /** Variante que recibe los bytes ya en memoria (foto reducida, PDF de la DGI…). */
    public function guardarBytes(string $bytes, string $nombreOriginal, string $extension, string $mime, string $tablaOrigen, int $registroId, int $companiaId, string $modulo, $usuario): Adjunto
    {
        $disco = $this->disco();
        $ext = strtolower($extension ?: 'bin');
        $path = strtolower($modulo).'/'.$companiaId.'/'.Str::uuid().'.'.$ext;

        Storage::disk($disco)->put($path, $bytes);

        return Adjunto::create([
            'compania_id' => $companiaId,
            'modulo' => $modulo,
            'tabla_origen' => $tablaOrigen,
            'registro_id' => $registroId,
            'nombre_archivo' => mb_substr($nombreOriginal, 0, 255),
            'mime_type' => mb_substr($mime, 0, 100),
            'extension' => mb_substr($ext, 0, 20),
            'size_bytes' => strlen($bytes),
            'storage_disk' => $disco,
            'storage_path' => $path,
            'hash_archivo' => hash('sha256', $bytes),
            'usuario_id' => $usuario->id ?? null,
            'created_by' => $usuario->email ?? null,
        ]);
    }

    /**
     * Registra en core_adjuntos un archivo que YA existe en el disco (lo escribió
     * el flujo viejo de archivo_path, o un backfill). No copia ni mueve bytes.
     * Idempotente: si ya hay una fila para ese (tabla, registro, path) la devuelve.
     */
    public function registrarExistente(string $storagePath, ?string $storageDisk, string $tablaOrigen, int $registroId, int $companiaId, string $modulo, $usuario = null): ?Adjunto
    {
        $disco = $storageDisk ?: $this->disco();

        $ya = Adjunto::where('compania_id', $companiaId)
            ->where('tabla_origen', $tablaOrigen)
            ->where('registro_id', $registroId)
            ->where('storage_path', $storagePath)
            ->first();
        if ($ya) {
            return $ya;
        }

        $ext = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION) ?: 'bin');

        // Tamaño/mime best-effort: si el archivo no está accesible igual se
        // registra la referencia (no rompemos por un disco intermitente).
        $size = null;
        try {
            if (Storage::disk($disco)->exists($storagePath)) {
                $size = Storage::disk($disco)->size($storagePath);
            }
        } catch (\Throwable $e) {
            Log::warning('Adjunto registrarExistente: no se pudo medir', ['path' => $storagePath, 'error' => $e->getMessage()]);
        }

        return Adjunto::create([
            'compania_id' => $companiaId,
            'modulo' => $modulo,
            'tabla_origen' => $tablaOrigen,
            'registro_id' => $registroId,
            'nombre_archivo' => basename($storagePath),
            'mime_type' => $this->mimeDeExt($ext),
            'extension' => mb_substr($ext, 0, 20),
            'size_bytes' => $size,
            'storage_disk' => $disco,
            'storage_path' => $storagePath,
            'usuario_id' => $usuario->id ?? null,
            'created_by' => $usuario->email ?? 'backfill',
        ]);
    }

    /** Elimina la fila y, best-effort, el archivo del disco. */
    public function eliminar(Adjunto $adjunto): void
    {
        $disco = $adjunto->storage_disk ?: $this->disco();
        try {
            Storage::disk($disco)->delete($adjunto->storage_path);
        } catch (\Throwable $e) {
            Log::warning('Adjunto: no se pudo borrar del disco', ['id' => $adjunto->id, 'error' => $e->getMessage()]);
        }
        $adjunto->delete();
    }

    public function extDeMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'application/pdf' => 'pdf',
            default => 'jpg',
        };
    }

    private function mimeDeExt(string $ext): ?string
    {
        return match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            'pdf' => 'application/pdf',
            default => null,
        };
    }
}
