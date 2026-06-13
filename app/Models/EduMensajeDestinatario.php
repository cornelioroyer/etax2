<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduMensajeDestinatario extends Model
{
    protected $table = 'edu_mensajes_destinatarios';

    protected $fillable = [
        'mensaje_id', 'contacto_id', 'tipo_destinatario', 'email', 'telefono',
        'enviado', 'entregado', 'leido', 'fecha_envio', 'fecha_entrega',
        'fecha_lectura', 'error_mensaje',
    ];

    protected $casts = [
        'enviado'    => 'boolean',
        'entregado'  => 'boolean',
        'leido'      => 'boolean',
    ];

    public function mensaje(): BelongsTo
    {
        return $this->belongsTo(EduMensaje::class, 'mensaje_id');
    }
}
