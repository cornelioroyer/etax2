<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduEstudiante extends Model
{
    protected $table = 'edu_estudiantes';

    protected $fillable = [
        'institucion_id', 'contacto_id', 'codigo_estudiante', 'fecha_ingreso',
        'fecha_retiro', 'estado', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(EduMatricula::class, 'estudiante_id');
    }

    public function acudientes(): HasMany
    {
        return $this->hasMany(EduEstudianteAcudiente::class, 'estudiante_id');
    }
}
