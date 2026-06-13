<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BcoTransferencia extends Model
{
    protected $table = 'bco_transferencias';

    public const ESTADO_APLICADA = 'APLICADA';
    public const ESTADO_ANULADA  = 'ANULADA';

    protected $fillable = [
        'compania_id', 'cuenta_origen_id', 'cuenta_destino_id', 'fecha',
        'monto', 'referencia', 'asiento_id', 'estado',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function cuentaOrigen(): BelongsTo
    {
        return $this->belongsTo(BcoCuenta::class, 'cuenta_origen_id');
    }

    public function cuentaDestino(): BelongsTo
    {
        return $this->belongsTo(BcoCuenta::class, 'cuenta_destino_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    public function esAnulada(): bool
    {
        return $this->estado === self::ESTADO_ANULADA;
    }
}
