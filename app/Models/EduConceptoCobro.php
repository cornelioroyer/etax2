<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EduConceptoCobro extends Model
{
    protected $table = 'edu_conceptos_cobro';

    protected $fillable = [
        'institucion_id', 'codigo', 'nombre', 'descripcion', 'tipo_concepto',
        'frecuencia', 'monto_base', 'item_id', 'cuenta_ingreso_id',
        'cuenta_por_cobrar_id', 'impuesto_id', 'activo', 'created_by', 'updated_by',
    ];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(EduInstitucion::class, 'institucion_id');
    }
}
