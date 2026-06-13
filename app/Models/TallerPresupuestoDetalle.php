<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerPresupuestoDetalle extends Model
{
    protected $table = 'taller_presupuestos_detalle';

    public $timestamps = false;

    protected $fillable = [
        'presupuesto_id', 'tipo_linea',
        'item_id', 'servicio_id',
        'descripcion', 'cantidad', 'precio_unitario',
        'descuento', 'impuesto_id', 'impuesto', 'total', 'orden',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'       => 'float',
            'precio_unitario'=> 'float',
            'descuento'      => 'decimal:2',
            'impuesto'       => 'decimal:2',
            'total'          => 'decimal:2',
        ];
    }

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(TallerPresupuesto::class, 'presupuesto_id');
    }
}
