<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduComunicadoDestinatario extends Model
{
    protected $table = 'edu_comunicados_destinatarios';

    protected $fillable = [
        'comunicado_id', 'contacto_id', 'email_enviado', 'whatsapp_enviado',
        'leido', 'fecha_lectura',
    ];

    protected $casts = [
        'email_enviado'     => 'boolean',
        'whatsapp_enviado'  => 'boolean',
        'leido'             => 'boolean',
        'fecha_lectura'     => 'datetime',
    ];

    public function comunicado(): BelongsTo
    {
        return $this->belongsTo(EduComunicado::class, 'comunicado_id');
    }
}
