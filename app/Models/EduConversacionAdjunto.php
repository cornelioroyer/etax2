<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduConversacionAdjunto extends Model
{
    protected $table = 'edu_conversacion_adjuntos';

    protected $fillable = [
        'conversacion_mensaje_id', 'adjunto_id', 'created_by',
    ];

    public function conversacionMensaje(): BelongsTo
    {
        return $this->belongsTo(EduConversacionMensaje::class, 'conversacion_mensaje_id');
    }
}
