<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduBoletinDetalle extends Model
{
    protected $table = 'edu_boletines_detalle';

    protected $fillable = [
        'boletin_id', 'asignatura_id', 'promedio', 'nota_final',
        'estado_academico', 'observacion', 'created_by', 'updated_by',
    ];

    public function boletin(): BelongsTo
    {
        return $this->belongsTo(EduBoletin::class, 'boletin_id');
    }

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(EduAsignatura::class, 'asignatura_id');
    }
}
