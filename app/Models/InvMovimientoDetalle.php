<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvMovimientoDetalle extends Model
{
    protected $table = 'inv_movimientos_detalle';

    protected $fillable = [
        'movimiento_id', 'item_id', 'cantidad', 'costo_unitario', 'total',
        'cantidad_anterior', 'costo_anterior', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'          => 'decimal:4',
            'costo_unitario'    => 'decimal:4',
            'total'             => 'decimal:2',
            'cantidad_anterior' => 'decimal:4',
            'costo_anterior'    => 'decimal:4',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemProducto::class, 'item_id');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(InvMovimiento::class, 'movimiento_id');
    }
}
