<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduEsquemaCalificacionDetalle extends Model
{
    protected $table = 'edu_esquemas_calificacion_detalle';

    protected $fillable = [
        'esquema_id', 'codigo', 'nombre', 'tipo_evaluacion', 'porcentaje',
        'orden', 'activo', 'created_by', 'updated_by',
    ];

    public function esquema(): BelongsTo
    {
        return $this->belongsTo(EduEsquemaCalificacion::class, 'esquema_id');
    }
}
