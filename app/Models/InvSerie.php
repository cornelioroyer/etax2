<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvSerie extends Model
{
    protected $table = 'inv_series';

    const ESTADO_DISPONIBLE = 'DISPONIBLE';
    const ESTADO_VENDIDO    = 'VENDIDO';
    const ESTADO_ANULADO    = 'ANULADO';

    protected $fillable = ['item_id', 'serie', 'estado', 'created_by', 'updated_by'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemProducto::class, 'item_id');
    }
}
