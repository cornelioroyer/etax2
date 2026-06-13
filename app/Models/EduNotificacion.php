<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduNotificacion extends Model
{
    protected $table = 'edu_notificaciones';

    protected $fillable = [
        'institucion_id', 'contacto_id', 'titulo', 'mensaje', 'tipo_notificacion',
        'referencia_tabla', 'referencia_id', 'leida', 'fecha_lectura', 'created_by',
    ];

    protected $casts = [
        'leida'         => 'boolean',
        'fecha_lectura' => 'datetime',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }
}
