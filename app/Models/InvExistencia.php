<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvExistencia extends Model
{
    protected $table = 'inv_existencias';

    protected $fillable = ['compania_id', 'almacen_id', 'item_id', 'cantidad', 'costo_promedio', 'updated_by'];

    protected function casts(): array
    {
        return [
            'cantidad'       => 'decimal:4',
            'costo_promedio' => 'decimal:4',
        ];
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(InvAlmacen::class, 'almacen_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemProducto::class, 'item_id');
    }
}
