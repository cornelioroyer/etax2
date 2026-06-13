<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerControlCalidadDetalle extends Model
{
    protected $table = 'taller_control_calidad_detalle';

    public $timestamps = false;

    protected $fillable = [
        'control_calidad_id', 'punto_revision',
        'resultado', 'observacion',
    ];

    public function controlCalidad(): BelongsTo
    {
        return $this->belongsTo(TallerControlCalidad::class, 'control_calidad_id');
    }
}
