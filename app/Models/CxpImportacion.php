<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CxpImportacion extends Model
{
    protected $table = 'cxp_importaciones';

    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_PROCESANDO = 'PROCESANDO';

    public const ESTADO_COMPLETADO = 'COMPLETADO';

    public const ESTADO_FALLIDO = 'FALLIDO';

    protected $fillable = [
        'compania_id',
        'usuario',
        'archivo',
        'ruta',
        'estado',
        'total',
        'procesadas',
        'creadas',
        'con_detalle',
        'omitidas',
        'errores',
        'mensaje_error',
        'terminado_at',
    ];

    protected function casts(): array
    {
        return [
            'errores' => 'array',
            'terminado_at' => 'datetime',
        ];
    }

    public function porcentaje(): int
    {
        if ($this->total > 0) {
            return (int) min(100, round($this->procesadas / $this->total * 100));
        }

        return $this->estado === self::ESTADO_COMPLETADO ? 100 : 0;
    }

    public function terminada(): bool
    {
        return in_array($this->estado, [self::ESTADO_COMPLETADO, self::ESTADO_FALLIDO], true);
    }
}
