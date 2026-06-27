<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adjunto central (tabla `core_adjuntos`). Un archivo subido a un disco
 * (S3/local) ligado a un registro de origen vía modulo/tabla_origen/registro_id
 * y aislado por compañía. Es la fuente única de adjuntos transversal a módulos.
 */
class Adjunto extends Model
{
    protected $table = 'core_adjuntos';

    protected $fillable = [
        'compania_id',
        'modulo',
        'tabla_origen',
        'registro_id',
        'nombre_archivo',
        'mime_type',
        'extension',
        'size_bytes',
        'storage_disk',
        'storage_path',
        'thumbnail_path',
        'url',
        'hash_archivo',
        'usuario_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'registro_id' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function compania(): BelongsTo
    {
        return $this->belongsTo(Compania::class, 'compania_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** Adjuntos de un documento concreto, aislados por compañía. */
    public function scopeParaDocumento(Builder $q, string $tablaOrigen, int $registroId, int $companiaId): Builder
    {
        return $q->where('compania_id', $companiaId)
            ->where('tabla_origen', $tablaOrigen)
            ->where('registro_id', $registroId);
    }

    public function esImagen(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function esPdf(): bool
    {
        return $this->mime_type === 'application/pdf'
            || str_ends_with(strtolower((string) $this->storage_path), '.pdf');
    }

    /** Tamaño legible para la UI (B/KB/MB). */
    public function tamanoLegible(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes <= 0) {
            return '';
        }
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
