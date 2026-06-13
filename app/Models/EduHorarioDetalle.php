<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduHorarioDetalle extends Model
{
    protected $table = 'edu_horarios_detalle';

    protected $fillable = [
        'horario_id', 'dia_semana', 'hora_inicio', 'hora_fin',
        'asignatura_id', 'docente_id', 'aula',
    ];

    public function horario(): BelongsTo
    {
        return $this->belongsTo(EduHorario::class, 'horario_id');
    }

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(EduAsignatura::class, 'asignatura_id');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(EduDocente::class, 'docente_id');
    }
}
