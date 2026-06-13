<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AfiRevaluacion extends Model
{
    protected $table = 'afi_revaluaciones';

    protected $fillable = [
        'activo_id', 'fecha', 'valor_anterior', 'valor_nuevo',
        'asiento_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha'          => 'date',
        'valor_anterior' => 'decimal:2',
        'valor_nuevo'    => 'decimal:2',
    ];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(AfiActivo::class, 'activo_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(CglAsiento::class, 'asiento_id');
    }

    public function getDiferenciaAttribute(): float
    {
        return (float) $this->valor_nuevo - (float) $this->valor_anterior;
    }
}
