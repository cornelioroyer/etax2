<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduCargoCxc extends Model
{
    protected $table = 'edu_cargos_cxc';

    protected $fillable = [
        'institucion_id', 'estudiante_id', 'matricula_id', 'concepto_id',
        'plan_cobro_id', 'generacion_cobro_id', 'cxc_documento_id', 'periodo_id',
        'anio', 'mes', 'numero_cuota', 'monto', 'descuento', 'recargo', 'total',
        'pagado', 'saldo', 'estado', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(EduEstudiante::class, 'estudiante_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(EduMatricula::class, 'matricula_id');
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(EduConceptoCobro::class, 'concepto_id');
    }

    public function planCobro(): BelongsTo
    {
        return $this->belongsTo(EduPlanCobro::class, 'plan_cobro_id');
    }

    public function generacionCobro(): BelongsTo
    {
        return $this->belongsTo(EduGeneracionCobro::class, 'generacion_cobro_id');
    }
}
