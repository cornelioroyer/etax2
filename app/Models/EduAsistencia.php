<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduAsistencia extends Model
{
    protected $table = 'edu_asistencias';

    protected $fillable = [
        'institucion_id', 'matricula_id', 'asignatura_id', 'fecha',
        'estado', 'observacion', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha' => 'date',
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
}
