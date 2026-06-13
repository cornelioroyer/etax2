<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduMensajeAdjunto extends Model
{
    protected $table = 'edu_mensajes_adjuntos';

    protected $fillable = [
        'mensaje_id', 'adjunto_id', 'created_by',
    ];

    public function mensaje(): BelongsTo
    {
        return $this->belongsTo(EduMensaje::class, 'mensaje_id');
    }
}
