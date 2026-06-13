<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BcoConciliacionDetalle extends Model
{
    protected $table = 'bco_conciliaciones_detalle';

    protected $fillable = [
        'conciliacion_id', 'movimiento_id', 'asiento_detalle_id', 'conciliado',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['conciliado' => 'boolean'];
    }

    public function conciliacion(): BelongsTo
    {
        return $this->belongsTo(BcoConciliacion::class, 'conciliacion_id');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(BcoMovimiento::class, 'movimiento_id');
    }
}
