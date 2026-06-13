<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduMatricula extends Model
{
    protected $table = 'edu_matriculas';

    protected $fillable = [
        'institucion_id', 'estudiante_id', 'periodo_id', 'sede_id', 'nivel_id',
        'programa_id', 'grado_id', 'grupo_id', 'fecha_matricula', 'estado',
        'cxc_documento_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha_matricula' => 'date',
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

    public function sede(): BelongsTo
    {
        return $this->belongsTo(EduSede::class, 'sede_id');
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

    public function detalles(): HasMany
    {
        return $this->hasMany(EduMatriculaDetalle::class, 'matricula_id');
    }
}
