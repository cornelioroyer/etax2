<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CglCierre extends Model
{
    protected $table = 'cgl_cierres';

    const ESTADO_PENDIENTE  = 'PENDIENTE';
    const ESTADO_EN_PROCESO = 'EN_PROCESO';
    const ESTADO_COMPLETADO = 'COMPLETADO';
    const ESTADO_ERROR      = 'ERROR';

    protected $fillable = [
        'compania_id', 'periodo_id', 'estado', 'cerrado_por',
        'fecha_cierre', 'observacion', 'created_by', 'updated_by',
    ];

    protected $casts = ['fecha_cierre' => 'datetime'];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoContable::class, 'periodo_id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(CglCierreDetalle::class, 'cierre_id');
    }

    public function estaCompletado(): bool
    {
        return $this->estado === self::ESTADO_COMPLETADO;
    }
}
