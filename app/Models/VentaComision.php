<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaComision extends Model
{
    protected $table = 'ventas_comisiones';

    const ESTADO_PENDIENTE = 'PENDIENTE';
    const ESTADO_PAGADA    = 'PAGADA';
    const ESTADO_ANULADA   = 'ANULADA';

    protected $fillable = [
        'vendedor_id', 'factura_id', 'porcentaje', 'monto',
        'estado', 'created_by', 'updated_by',
    ];

    protected $casts = ['porcentaje' => 'decimal:4', 'monto' => 'decimal:2'];

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(VentaVendedor::class, 'vendedor_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(VentaFactura::class, 'factura_id');
    }
}
