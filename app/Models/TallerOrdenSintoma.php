<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerOrdenSintoma extends Model
{
    protected $table = 'taller_orden_sintomas';

    public $timestamps = false;

    protected $fillable = [
        'orden_id',
        'sintoma_id',
        'descripcion',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(TallerOrden::class, 'orden_id');
    }

    public function sintoma(): BelongsTo
    {
        return $this->belongsTo(TallerSintoma::class, 'sintoma_id');
    }
}
