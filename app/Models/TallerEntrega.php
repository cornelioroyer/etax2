<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerEntrega extends Model
{
    protected $table = 'taller_entregas';

    public $timestamps = false;

    protected $fillable = [
        'orden_id', 'entregado_a_id', 'usuario_entrega_id',
        'fecha_entrega', 'documento_recibido',
        'observacion', 'firma_adjunto_id', 'estado',
        'created_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'datetime',
            'created_at'    => 'datetime',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(TallerOrden::class, 'orden_id');
    }
}
