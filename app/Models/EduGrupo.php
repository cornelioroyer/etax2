<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduGrupo extends Model
{
    protected $table = 'edu_grupos';

    protected $fillable = [
        'institucion_id', 'sede_id', 'grado_id', 'codigo', 'nombre',
        'jornada', 'capacidad', 'activo', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(EduSede::class, 'sede_id');
    }

    public function grado(): BelongsTo
    {
        return $this->belongsTo(EduGrado::class, 'grado_id');
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(EduMatricula::class, 'grupo_id');
    }
}
