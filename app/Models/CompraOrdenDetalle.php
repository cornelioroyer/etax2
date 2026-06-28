<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class CompraOrdenDetalle extends Model
{
    protected $table = 'compras_ordenes_detalle';

    protected $fillable = [
        'orden_id',
        'linea',
        'item_id',
        'descripcion',
        'cantidad',
        'cantidad_facturada',
        'precio_unitario',
        'impuesto_id',
        'cuenta_id',
        'total_linea',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'cantidad_facturada' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'total_linea' => 'decimal:2',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(CompraOrden::class, 'orden_id');
    }

    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(TaxImpuesto::class, 'impuesto_id');
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_id');
    }
}
