<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduPrograma extends Model
{
    protected $table = 'edu_programas';

    protected $fillable = [
        'institucion_id', 'nivel_id', 'codigo', 'nombre', 'tipo_programa',
        'duracion_periodos', 'activo', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(EduNivelAcademico::class, 'nivel_id');
    }

    public function grados(): HasMany
    {
        return $this->hasMany(EduGrado::class, 'programa_id');
    }
}
