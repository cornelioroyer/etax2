<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduComunicado extends Model
{
    protected $table = 'edu_comunicados';

    protected $fillable = [
        'institucion_id', 'titulo', 'mensaje', 'dirigido_a', 'grado_id',
        'grupo_id', 'fecha_envio', 'estado', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha_envio' => 'datetime',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function grado(): BelongsTo
    {
        return $this->belongsTo(EduGrado::class, 'grado_id');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(EduGrupo::class, 'grupo_id');
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(EduComunicadoDestinatario::class, 'comunicado_id');
    }
}
