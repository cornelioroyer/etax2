<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduPendienteEstudiante extends Model
{
    protected $table = 'edu_pendientes_estudiantes';

    protected $fillable = [
        'institucion_id', 'estudiante_id', 'matricula_id', 'periodo_id',
        'docente_id', 'asignatura_id', 'tipo_pendiente', 'titulo', 'descripcion',
        'prioridad', 'fecha_pendiente', 'fecha_limite', 'estado',
        'visible_acudiente', 'visible_estudiante', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha_pendiente'    => 'date',
        'fecha_limite'       => 'date',
        'visible_acudiente'  => 'boolean',
        'visible_estudiante' => 'boolean',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(EduEstudiante::class, 'estudiante_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(EduMatricula::class, 'matricula_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(EduPeriodoAcademico::class, 'periodo_id');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(EduDocente::class, 'docente_id');
    }

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(EduAsignatura::class, 'asignatura_id');
    }
}
