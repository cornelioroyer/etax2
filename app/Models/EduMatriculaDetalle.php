<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduMatriculaDetalle extends Model
{
    protected $table = 'edu_matriculas_detalle';

    protected $fillable = [
        'matricula_id', 'asignatura_id', 'estado', 'nota_final',
    ];

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(EduMatricula::class, 'matricula_id');
    }

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(EduAsignatura::class, 'asignatura_id');
    }
}
