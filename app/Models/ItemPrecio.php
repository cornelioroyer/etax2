<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPrecio extends Model
{
    protected $table = 'item_precios';

    protected $fillable = [
        'item_id', 'lista', 'precio', 'fecha_inicio', 'fecha_fin',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'precio'      => 'decimal:2',
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemProducto::class, 'item_id');
    }
}
