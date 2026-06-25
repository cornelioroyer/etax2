<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CxpRecurrenteDetalle extends Model
{
    protected $table = 'cxp_recurrentes_detalle';

    protected $fillable = [
        'recurrente_id',
        'linea',
        'item_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'tasa_itbms',
        'cuenta_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'tasa_itbms' => 'integer',
        ];
    }

    public function recurrente(): BelongsTo
    {
        return $this->belongsTo(CxpRecurrente::class, 'recurrente_id');
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_id');
    }
}
