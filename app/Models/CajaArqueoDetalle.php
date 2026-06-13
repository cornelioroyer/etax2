<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CajaArqueoDetalle extends Model
{
    protected $table = 'caj_arqueos_detalle';

    protected $fillable = [
        'arqueo_id',
        'denominacion',
        'cantidad',
        'total',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'denominacion' => 'decimal:2',
            'cantidad' => 'integer',
            'total' => 'decimal:2',
        ];
    }

    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(CajaArqueo::class, 'arqueo_id');
    }
}
