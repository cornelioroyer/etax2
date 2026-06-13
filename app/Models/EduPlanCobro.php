<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduPlanCobro extends Model
{
    protected $table = 'edu_planes_cobro';

    protected $fillable = [
        'institucion_id', 'concepto_id', 'codigo', 'nombre', 'descripcion',
        'aplica_a', 'nivel_id', 'programa_id', 'grado_id', 'grupo_id', 'estudiante_id',
        'frecuencia', 'cantidad_cuotas', 'dia_vencimiento', 'fecha_inicio', 'fecha_fin',
        'monto', 'generar_automatico', 'activo', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha_inicio'        => 'date',
        'fecha_fin'           => 'date',
        'generar_automatico'  => 'boolean',
        'activo'              => 'boolean',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(EduConceptoCobro::class, 'concepto_id');
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(EduNivelAcademico::class, 'nivel_id');
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(EduPrograma::class, 'programa_id');
    }

    public function grado(): BelongsTo
    {
        return $this->belongsTo(EduGrado::class, 'grado_id');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(EduGrupo::class, 'grupo_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(EduEstudiante::class, 'estudiante_id');
    }
}
