<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduInstitucion extends Model
{
    protected $table = 'edu_instituciones';

    protected $fillable = [
        'compania_id', 'codigo', 'nombre', 'tipo_institucion', 'direccion',
        'telefono', 'email', 'sitio_web', 'activo', 'created_by', 'updated_by',
    ];

    public function compania(): BelongsTo
    {
        return $this->belongsTo(Compania::class);
    }

    public function sedes(): HasMany
    {
        return $this->hasMany(EduSede::class, 'institucion_id');
    }

    public function periodos(): HasMany
    {
        return $this->hasMany(EduPeriodoAcademico::class, 'institucion_id');
    }

    public function estudiantes(): HasMany
    {
        return $this->hasMany(EduEstudiante::class, 'institucion_id');
    }

    public function docentes(): HasMany
    {
        return $this->hasMany(EduDocente::class, 'institucion_id');
    }
}
