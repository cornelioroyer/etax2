<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsientoRecurrenteDetalle extends Model
{
    protected $table = 'cgl_asientos_recurrentes_detalle';

    protected $fillable = [
        'recurrente_id',
        'linea',
        'cuenta_id',
        'contacto_id',
        'descripcion',
        'debito',
        'credito',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'debito' => 'decimal:2',
            'credito' => 'decimal:2',
        ];
    }

    public function recurrente(): BelongsTo
    {
        return $this->belongsTo(AsientoRecurrente::class, 'recurrente_id');
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_id');
    }
}
