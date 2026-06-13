<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduPlanEstudio extends Model
{
    protected $table = 'edu_planes_estudio';

    protected $fillable = [
        'institucion_id', 'programa_id', 'codigo', 'nombre', 'fecha_inicio',
        'fecha_fin', 'activo', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(EduPrograma::class, 'programa_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(EduPlanEstudioDetalle::class, 'plan_estudio_id');
    }
}
