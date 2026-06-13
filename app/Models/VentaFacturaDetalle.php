<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaFacturaDetalle extends Model
{
    protected $table = 'ventas_facturas_detalle';

    protected $fillable = [
        'factura_id', 'linea', 'item_id', 'descripcion', 'cantidad',
        'precio_unitario', 'descuento', 'impuesto_id', 'impuesto_monto',
        'total_linea', 'cuenta_ingreso_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'        => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'descuento'       => 'decimal:2',
            'impuesto_monto'  => 'decimal:2',
            'total_linea'     => 'decimal:2',
        ];
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(VentaFactura::class, 'factura_id');
    }

    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(TaxImpuesto::class, 'impuesto_id');
    }

    public function cuentaIngreso(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_ingreso_id');
    }
}
