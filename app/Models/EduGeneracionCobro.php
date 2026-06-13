<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EduGeneracionCobro extends Model
{
    protected $table = 'edu_generaciones_cobros';

    protected $fillable = [
        'institucion_id', 'periodo_id', 'plan_cobro_id', 'anio', 'mes',
        'numero_cuota', 'total_cuotas', 'fecha_generacion', 'fecha_vencimiento',
        'descripcion', 'estado', 'asiento_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha_generacion'  => 'date',
        'fecha_vencimiento' => 'date',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(EduPeriodoAcademico::class, 'periodo_id');
    }

    public function planCobro(): BelongsTo
    {
        return $this->belongsTo(EduPlanCobro::class, 'plan_cobro_id');
    }

    public function cargosCxc(): HasMany
    {
        return $this->hasMany(EduCargoCxc::class, 'generacion_cobro_id');
    }
}
