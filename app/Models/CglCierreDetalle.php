<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CglCierreDetalle extends Model
{
    protected $table = 'cgl_cierres_detalle';

    protected $fillable = [
        'cierre_id', 'paso', 'estado', 'observacion', 'created_by', 'updated_by',
    ];

    public function cierre(): BelongsTo
    {
        return $this->belongsTo(CglCierre::class, 'cierre_id');
    }
}
