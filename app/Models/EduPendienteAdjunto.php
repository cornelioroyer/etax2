<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduPendienteAdjunto extends Model
{
    protected $table = 'edu_pendientes_adjuntos';

    protected $fillable = [
        'pendiente_id', 'adjunto_id', 'created_by',
    ];

    public function pendiente(): BelongsTo
    {
        return $this->belongsTo(EduPendienteEstudiante::class, 'pendiente_id');
    }
}
