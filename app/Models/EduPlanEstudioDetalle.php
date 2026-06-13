<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduPlanEstudioDetalle extends Model
{
    protected $table = 'edu_planes_estudio_detalle';

    protected $fillable = [
        'plan_estudio_id', 'grado_id', 'asignatura_id', 'orden',
        'obligatoria', 'creditos', 'horas_semanales',
    ];

    public function planEstudio(): BelongsTo
    {
        return $this->belongsTo(EduPlanEstudio::class, 'plan_estudio_id');
    }

    public function grado(): BelongsTo
    {
        return $this->belongsTo(EduGrado::class, 'grado_id');
    }

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(EduAsignatura::class, 'asignatura_id');
    }
}
