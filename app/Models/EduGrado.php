<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduGrado extends Model
{
    protected $table = 'edu_grados';

    protected $fillable = [
        'institucion_id', 'nivel_id', 'programa_id', 'codigo', 'nombre',
        'orden', 'activo', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(EduNivelAcademico::class, 'nivel_id');
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(EduPrograma::class, 'programa_id');
    }

    public function grupos(): HasMany
    {
        return $this->hasMany(EduGrupo::class, 'grado_id');
    }
}
