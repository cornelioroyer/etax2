<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaCotizacionDetalle extends Model
{
    protected $table = 'ventas_cotizaciones_detalle';

    protected $fillable = [
        'cotizacion_id',
        'linea',
        'item_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'descuento',
        'impuesto_id',
        'impuesto_monto',
        'total_linea',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'descuento' => 'decimal:2',
            'impuesto_monto' => 'decimal:2',
            'total_linea' => 'decimal:2',
        ];
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(VentaCotizacion::class, 'cotizacion_id');
    }

    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(TaxImpuesto::class, 'impuesto_id');
    }
}
