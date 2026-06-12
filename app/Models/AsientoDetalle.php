<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsientoDetalle extends Model
{
    protected $table = 'cgl_asientos_detalle';

    protected $fillable = [
        'asiento_id',
        'linea',
        'cuenta_id',
        'contacto_id',
        'descripcion',
        'debito',
        'credito',
        'tasa_cambio',
        'debito_local',
        'credito_local',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'debito' => 'decimal:2',
            'credito' => 'decimal:2',
            'debito_local' => 'decimal:2',
            'credito_local' => 'decimal:2',
        ];
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_id');
    }

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }
}
