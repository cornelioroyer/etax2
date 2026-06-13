<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduCanalComunicacion extends Model
{
    protected $table = 'edu_canales_comunicacion';

    protected $fillable = [
        'institucion_id', 'codigo', 'nombre', 'tipo_canal', 'configuracion',
        'activo', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'configuracion' => 'array',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }
}
