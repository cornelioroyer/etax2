<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvAjuste extends Model
{
    protected $table = 'inv_ajustes';

    protected $fillable = [
        'compania_id', 'almacen_id', 'fecha', 'motivo',
        'movimiento_id', 'asiento_id', 'created_by', 'updated_by',
    ];

    protected $casts = ['fecha' => 'date'];

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(InvAlmacen::class, 'almacen_id');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(InvMovimiento::class, 'movimiento_id');
    }
}
