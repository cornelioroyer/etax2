<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduDocente extends Model
{
    protected $table = 'edu_docentes';

    protected $fillable = [
        'institucion_id', 'contacto_id', 'codigo_docente', 'especialidad',
        'fecha_ingreso', 'fecha_salida', 'estado', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }

    public function asignaturas(): HasMany
    {
        return $this->hasMany(EduDocenteAsignatura::class, 'docente_id');
    }
}
