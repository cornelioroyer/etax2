<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduConversacionParticipante extends Model
{
    protected $table = 'edu_conversacion_participantes';

    protected $fillable = [
        'conversacion_id', 'contacto_id', 'rol_participante', 'activo', 'created_by',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(EduConversacion::class, 'conversacion_id');
    }
}
