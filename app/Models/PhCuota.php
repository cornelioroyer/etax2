<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhCuota extends Model
{
    protected $table = 'ph_cuotas';

    const ESTADO_PENDIENTE = 'PENDIENTE';
    const ESTADO_PAGADO    = 'PAGADO';
    const ESTADO_VENCIDO   = 'VENCIDO';
    const ESTADO_ANULADO   = 'ANULADO';

    protected $fillable = [
        'compania_id', 'unidad_id', 'tipo_cuota_id', 'periodo',
        'fecha_emision', 'fecha_vencimiento', 'monto', 'monto_pagado',
        'concepto', 'estado', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision'    => 'date',
            'fecha_vencimiento' => 'date',
            'monto'            => 'decimal:2',
            'monto_pagado'     => 'decimal:2',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(PhUnidad::class, 'unidad_id');
    }

    public function tipoCuota(): BelongsTo
    {
        return $this->belongsTo(PhTipoCuota::class, 'tipo_cuota_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PhPago::class, 'cuota_id');
    }

    public function saldoPendiente(): float
    {
        return round((float) $this->monto - (float) $this->monto_pagado, 2);
    }

    public function recalcularEstado(): void
    {
        if ($this->estado === self::ESTADO_ANULADO) {
            return;
        }

        if ((float) $this->monto_pagado >= (float) $this->monto) {
            $this->estado = self::ESTADO_PAGADO;
        } elseif (now()->toDateString() > $this->fecha_vencimiento->toDateString()) {
            $this->estado = self::ESTADO_VENCIDO;
        } else {
            $this->estado = self::ESTADO_PENDIENTE;
        }
    }
}
