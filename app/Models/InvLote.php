<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvLote extends Model
{
    protected $table = 'inv_lotes';

    protected $fillable = ['item_id', 'lote', 'fecha_expira', 'created_by', 'updated_by'];

    protected $casts = ['fecha_expira' => 'date'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemProducto::class, 'item_id');
    }
}
