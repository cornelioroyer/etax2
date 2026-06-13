<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduHorario extends Model
{
    protected $table = 'edu_horarios';

    protected $fillable = [
        'institucion_id', 'periodo_id', 'grupo_id', 'nombre',
        'activo', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(EduPeriodoAcademico::class, 'periodo_id');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(EduGrupo::class, 'grupo_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(EduHorarioDetalle::class, 'horario_id');
    }
}
