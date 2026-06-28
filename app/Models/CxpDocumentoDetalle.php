<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CxpDocumentoDetalle extends Model
{
    protected $table = 'cxp_documentos_detalle';

    protected $fillable = [
        'documento_id',
        'orden_detalle_id',
        'linea',
        'item_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'descuento',
        'impuesto_id',
        'impuesto_monto',
        'total_linea',
        'cuenta_id',
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

    public function documento(): BelongsTo
    {
        return $this->belongsTo(CxpDocumento::class, 'documento_id');
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_id');
    }
}
