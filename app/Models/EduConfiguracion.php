<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduConfiguracion extends Model
{
    protected $table = 'edu_configuracion';

    protected $fillable = [
        'institucion_id', 'dia_vencimiento_mensualidad', 'generar_cargos_automaticos',
        'tipo_recargo_mora', 'recargo_monto_fijo', 'recargo_porcentaje',
        'cuenta_cxc_id', 'cuenta_ingreso_matricula_id', 'cuenta_ingreso_mensualidad_id',
        'cuenta_ingreso_recargo_id', 'cuenta_banco_default_id', 'bco_cuenta_default_id',
        'moneda_id', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }
}
