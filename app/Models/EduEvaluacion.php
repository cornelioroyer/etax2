<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduEvaluacion extends Model
{
    protected $table = 'edu_evaluaciones';

    protected $fillable = [
        'institucion_id', 'periodo_id', 'asignatura_id', 'docente_id', 'grupo_id',
        'esquema_detalle_id', 'titulo', 'descripcion', 'tipo_evaluacion',
        'fecha_evaluacion', 'fecha_entrega', 'puntaje_maximo', 'porcentaje',
        'estado', 'visible_estudiante', 'visible_acudiente', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha_evaluacion'   => 'date',
        'fecha_entrega'      => 'date',
        'visible_estudiante' => 'boolean',
        'visible_acudiente'  => 'boolean',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(EduPeriodoAcademico::class, 'periodo_id');
    }

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(EduAsignatura::class, 'asignatura_id');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(EduDocente::class, 'docente_id');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(EduGrupo::class, 'grupo_id');
    }

    public function esquemaDetalle(): BelongsTo
    {
        return $this->belongsTo(EduEsquemaCalificacionDetalle::class, 'esquema_detalle_id');
    }

    public function calificaciones(): HasMany
    {
        return $this->hasMany(EduCalificacion::class, 'evaluacion_id');
    }
}
