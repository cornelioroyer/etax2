<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerServicioEstandar extends Model
{
    protected $table = 'taller_servicios_estandar';

    protected $fillable = [
        'taller_id', 'tipo_equipo_id', 'especialidad_id', 'codigo', 'nombre',
        'descripcion', 'item_id', 'tiempo_estimado_min', 'precio_base', 'costo_base',
        'cuenta_ingreso_id', 'impuesto_id', 'requiere_aprobacion', 'garantia_dias',
        'activo', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activo'              => 'boolean',
            'requiere_aprobacion' => 'boolean',
            'precio_base'         => 'decimal:2',
            'costo_base'          => 'decimal:2',
        ];
    }

    public function taller(): BelongsTo
    {
        return $this->belongsTo(TallerTaller::class, 'taller_id');
    }

    public function tipoEquipo(): BelongsTo
    {
        return $this->belongsTo(TallerTipoEquipo::class, 'tipo_equipo_id');
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(TallerEspecialidad::class, 'especialidad_id');
    }
}
