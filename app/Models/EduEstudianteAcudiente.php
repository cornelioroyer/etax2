<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduEstudianteAcudiente extends Model
{
    protected $table = 'edu_estudiante_acudientes';

    protected $fillable = [
        'estudiante_id', 'contacto_id', 'tipo_relacion', 'principal',
        'responsable_pago', 'autorizado_retirar', 'activo', 'created_by', 'updated_by',
    ];

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(EduEstudiante::class, 'estudiante_id');
    }

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }
}
