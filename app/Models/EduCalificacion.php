<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduCalificacion extends Model
{
    protected $table = 'edu_calificaciones';

    protected $fillable = [
        'institucion_id', 'matricula_id', 'asignatura_id', 'periodo_id',
        'tipo_evaluacion', 'descripcion', 'fecha', 'porcentaje', 'nota',
        'observacion', 'evaluacion_id', 'estudiante_id', 'matricula_detalle_id',
        'puntaje_obtenido', 'nota_ponderada', 'visible_estudiante', 'visible_acudiente',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha'              => 'date',
        'visible_estudiante' => 'boolean',
        'visible_acudiente'  => 'boolean',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(EduMatricula::class, 'matricula_id');
    }

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(EduAsignatura::class, 'asignatura_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(EduPeriodoAcademico::class, 'periodo_id');
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(EduEvaluacion::class, 'evaluacion_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(EduEstudiante::class, 'estudiante_id');
    }
}
