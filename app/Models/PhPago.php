<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhPago extends Model
{
    protected $table = 'ph_pagos';

    const FORMAS_PAGO = ['EFECTIVO', 'TRANSFERENCIA', 'CHEQUE', 'TARJETA', 'OTRO'];

    protected $fillable = [
        'cuota_id', 'fecha_pago', 'monto', 'referencia', 'forma_pago', 'notas',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_pago' => 'date',
            'monto'      => 'decimal:2',
        ];
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(PhCuota::class, 'cuota_id');
    }
}
