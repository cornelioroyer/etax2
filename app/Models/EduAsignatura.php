<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduAsignatura extends Model
{
    protected $table = 'edu_asignaturas';

    protected $fillable = [
        'institucion_id', 'codigo', 'nombre', 'descripcion', 'creditos',
        'horas_semanales', 'activo', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }
}
