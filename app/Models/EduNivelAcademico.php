<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduNivelAcademico extends Model
{
    protected $table = 'edu_niveles_academicos';

    protected $fillable = [
        'institucion_id', 'codigo', 'nombre', 'descripcion', 'orden',
        'activo', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function programas(): HasMany
    {
        return $this->hasMany(EduPrograma::class, 'nivel_id');
    }

    public function grados(): HasMany
    {
        return $this->hasMany(EduGrado::class, 'nivel_id');
    }
}
