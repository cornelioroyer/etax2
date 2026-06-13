<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduDocenteAsignatura extends Model
{
    protected $table = 'edu_docente_asignaturas';

    protected $fillable = [
        'institucion_id', 'periodo_id', 'docente_id', 'asignatura_id',
        'grupo_id', 'activo', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
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

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(EduGrupo::class, 'grupo_id');
    }
}
