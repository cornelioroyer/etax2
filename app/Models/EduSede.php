<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduSede extends Model
{
    protected $table = 'edu_sedes';

    protected $fillable = [
        'institucion_id', 'codigo', 'nombre', 'direccion', 'telefono',
        'email', 'activo', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function grupos(): HasMany
    {
        return $this->hasMany(EduGrupo::class, 'sede_id');
    }
}
