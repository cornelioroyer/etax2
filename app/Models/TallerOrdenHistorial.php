<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerOrdenHistorial extends Model
{
    protected $table = 'taller_orden_historial';

    public $timestamps = false;

    protected $fillable = [
        'orden_id',
        'estado_anterior',
        'estado_nuevo',
        'descripcion',
        'usuario_id',
        'contacto_id',
        'created_at',
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
}
