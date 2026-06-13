<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduBoletin extends Model
{
    protected $table = 'edu_boletines';

    protected $fillable = [
        'institucion_id', 'estudiante_id', 'matricula_id', 'periodo_id',
        'promedio_general', 'observacion_general', 'estado', 'fecha_generacion',
        'fecha_publicacion', 'visible_estudiante', 'visible_acudiente',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha_generacion'   => 'date',
        'fecha_publicacion'  => 'date',
        'visible_estudiante' => 'boolean',
        'visible_acudiente'  => 'boolean',
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

    public function detalles(): HasMany
    {
        return $this->hasMany(EduBoletinDetalle::class, 'boletin_id');
    }
}
