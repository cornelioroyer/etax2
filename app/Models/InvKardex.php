<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvKardex extends Model
{
    protected $table = 'inv_kardex';

    protected $fillable = [
        'compania_id', 'item_id', 'almacen_id', 'fecha',
        'tipo_movimiento', 'documento_origen', 'documento_id',
        'entrada_cantidad', 'entrada_costo', 'salida_cantidad', 'salida_costo',
        'saldo_cantidad', 'saldo_costo', 'costo_promedio',
        'asiento_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha'            => 'date',
        'entrada_cantidad' => 'decimal:4',
        'entrada_costo'    => 'decimal:2',
        'salida_cantidad'  => 'decimal:4',
        'salida_costo'     => 'decimal:2',
        'saldo_cantidad'   => 'decimal:4',
        'saldo_costo'      => 'decimal:2',
        'costo_promedio'   => 'decimal:4',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemProducto::class, 'item_id');
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(InvAlmacen::class, 'almacen_id');
    }
}
