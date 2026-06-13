<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduPendienteComentario extends Model
{
    protected $table = 'edu_pendientes_comentarios';

    protected $fillable = [
        'pendiente_id', 'contacto_id', 'docente_id', 'comentario',
        'visible_acudiente', 'visible_estudiante', 'created_by',
    ];

    protected $casts = [
        'visible_acudiente'  => 'boolean',
        'visible_estudiante' => 'boolean',
    ];

    public function pendiente(): BelongsTo
    {
        return $this->belongsTo(EduPendienteEstudiante::class, 'pendiente_id');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(EduDocente::class, 'docente_id');
    }
}
