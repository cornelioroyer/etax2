<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AfiBaja extends Model
{
    protected $table = 'afi_bajas';

    protected $fillable = [
        'activo_id', 'fecha', 'motivo', 'valor_baja',
        'asiento_id',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha'      => 'date',
        'valor_baja' => 'float',
    ];

    public function activo(): BelongsTo
    {
        return $this->belongsTo(AfiActivo::class, 'activo_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }
}
