<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduBecaDescuento extends Model
{
    protected $table = 'edu_becas_descuentos';

    protected $fillable = [
        'institucion_id', 'estudiante_id', 'periodo_id', 'tipo', 'nombre',
        'descripcion', 'tipo_calculo', 'porcentaje', 'monto', 'fecha_inicio',
        'fecha_fin', 'activo', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(EduEstudiante::class, 'estudiante_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(EduPeriodoAcademico::class, 'periodo_id');
    }
}
