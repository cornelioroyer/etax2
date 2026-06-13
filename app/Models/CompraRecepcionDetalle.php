<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraRecepcionDetalle extends Model
{
    protected $table = 'compras_recepciones_detalle';

    protected $fillable = [
        'recepcion_id',
        'orden_detalle_id',
        'item_id',
        'descripcion',
        'cantidad',
        'costo',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'costo' => 'decimal:4',
        ];
    }

    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(CompraRecepcion::class, 'recepcion_id');
    }

    public function ordenDetalle(): BelongsTo
    {
        return $this->belongsTo(CompraOrdenDetalle::class, 'orden_detalle_id');
    }
}
