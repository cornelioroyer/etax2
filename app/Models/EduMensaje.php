<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduMensaje extends Model
{
    protected $table = 'edu_mensajes';

    protected $fillable = [
        'institucion_id', 'canal_id', 'asunto', 'mensaje', 'tipo_mensaje',
        'origen', 'docente_id', 'estudiante_id', 'acudiente_id',
        'referencia_tabla', 'referencia_id', 'fecha_programada', 'fecha_envio',
        'estado', 'error_mensaje', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function canal(): BelongsTo
    {
        return $this->belongsTo(EduCanalComunicacion::class, 'canal_id');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(EduDocente::class, 'docente_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(EduEstudiante::class, 'estudiante_id');
    }
}
