<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduConversacion extends Model
{
    protected $table = 'edu_conversaciones';

    protected $fillable = [
        'institucion_id', 'asunto', 'estudiante_id', 'matricula_id',
        'grupo_id', 'asignatura_id', 'estado', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(EduEstudiante::class, 'estudiante_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(EduMatricula::class, 'matricula_id');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(EduGrupo::class, 'grupo_id');
    }

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(EduAsignatura::class, 'asignatura_id');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(EduConversacionMensaje::class, 'conversacion_id');
    }

    public function participantes(): HasMany
    {
        return $this->hasMany(EduConversacionParticipante::class, 'conversacion_id');
    }
}
