<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CierreContable extends Model
{
    protected $table = 'cgl_cierres';

    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_CERRADO = 'CERRADO';

    public const ESTADO_REABIERTO = 'REABIERTO';

    protected $fillable = [
        'compania_id',
        'periodo_id',
        'estado',
        'cerrado_por',
        'fecha_cierre',
        'observacion',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_cierre' => 'datetime',
        ];
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoContable::class, 'periodo_id');
    }
}
