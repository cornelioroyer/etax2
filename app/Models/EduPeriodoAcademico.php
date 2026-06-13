<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduPeriodoAcademico extends Model
{
    protected $table = 'edu_periodos_academicos';

    protected $fillable = [
        'institucion_id', 'codigo', 'nombre', 'anio', 'fecha_inicio', 'fecha_fin',
        'estado', 'activo', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }
}
