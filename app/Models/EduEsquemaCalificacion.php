<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduEsquemaCalificacion extends Model
{
    protected $table = 'edu_esquemas_calificacion';

    protected $fillable = [
        'institucion_id', 'codigo', 'nombre', 'descripcion',
        'activo', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(EduEsquemaCalificacionDetalle::class, 'esquema_id');
    }
}
