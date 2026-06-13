<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaReciboDetalle extends Model
{
    protected $table = 'ventas_recibos_detalle';

    protected $fillable = [
        'recibo_id', 'factura_id', 'cxc_documento_id', 'monto',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }

    public function recibo(): BelongsTo
    {
        return $this->belongsTo(VentaRecibo::class, 'recibo_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(VentaFactura::class, 'factura_id');
    }

    public function cxcDocumento(): BelongsTo
    {
        return $this->belongsTo(CxcDocumento::class, 'cxc_documento_id');
    }
}
