<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Respaldo extends Model
{
    protected $table = 'respaldos';

    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_PROCESANDO = 'PROCESANDO';

    public const ESTADO_COMPLETADO = 'COMPLETADO';

    public const ESTADO_FALLIDO = 'FALLIDO';

    protected $fillable = [
        'compania_id',
        'usuario',
        'estado',
        'archivo',
        'ruta',
        'disco',
        'bytes',
        'total_tablas',
        'tablas_procesadas',
        'total_filas',
        'checksum',
        'mensaje_error',
        'terminado_at',
    ];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'total_filas' => 'integer',
            'terminado_at' => 'datetime',
        ];
    }

    public function compania(): BelongsTo
    {
        return $this->belongsTo(Compania::class, 'compania_id');
    }

    public function porcentaje(): int
    {
        if ($this->total_tablas > 0) {
            return (int) min(100, round($this->tablas_procesadas / $this->total_tablas * 100));
        }

        return $this->estado === self::ESTADO_COMPLETADO ? 100 : 0;
    }

    public function terminado(): bool
    {
        return in_array($this->estado, [self::ESTADO_COMPLETADO, self::ESTADO_FALLIDO], true);
    }

    public function tamanoLegible(): string
    {
        $bytes = $this->bytes;
        if ($bytes <= 0) {
            return '—';
        }
        $unidades = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($unidades) - 1);

        return round($bytes / (1024 ** $i), 2).' '.$unidades[$i];
    }
}
