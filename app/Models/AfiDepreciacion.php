<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AfiDepreciacion extends Model
{
    protected $table = 'afi_depreciaciones';

    protected $fillable = [
        'activo_id', 'periodo_id', 'fecha', 'monto', 'acumulado',
        'asiento_id', 'estado',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha'     => 'date',
        'monto'     => 'float',
        'acumulado' => 'float',
    ];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(AfiActivo::class, 'activo_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoContable::class, 'periodo_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }
}
