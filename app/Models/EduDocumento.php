<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduDocumento extends Model
{
    protected $table = 'edu_documentos';

    protected $fillable = [
        'institucion_id', 'estudiante_id', 'contacto_id', 'tipo_documento',
        'adjunto_id', 'fecha_documento', 'observacion', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha_documento' => 'date',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(EduEstudiante::class, 'estudiante_id');
    }
}
