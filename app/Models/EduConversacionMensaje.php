<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduConversacionMensaje extends Model
{
    protected $table = 'edu_conversacion_mensajes';

    protected $fillable = [
        'conversacion_id', 'contacto_id', 'docente_id', 'mensaje',
        'visible_estudiante', 'visible_acudiente', 'visible_docente',
        'visible_escuela', 'created_by',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(EduConversacion::class, 'conversacion_id');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(EduDocente::class, 'docente_id');
    }
}
