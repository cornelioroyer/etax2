<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BcoCuenta extends Model
{
    protected $table = 'bco_cuentas';

    public const TIPOS = [
        'CORRIENTE'  => 'Corriente',
        'AHORROS'    => 'Ahorros',
        'INVERSION'  => 'Inversión',
    ];

    protected $fillable = [
        'compania_id', 'banco_id', 'cuenta_contable_id', 'numero_cuenta',
        'nombre', 'tipo_cuenta', 'moneda_id', 'saldo_inicial', 'activa',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activa'        => 'boolean',
            'saldo_inicial' => 'decimal:2',
        ];
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(BcoBanco::class, 'banco_id');
    }

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_contable_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(BcoMovimiento::class, 'cuenta_bancaria_id');
    }

    /** Saldo calculado: saldo inicial + créditos - débitos */
    public function getSaldoActualAttribute(): float
    {
        $movs = $this->movimientos()->selectRaw('COALESCE(SUM(credito),0) - COALESCE(SUM(debito),0) as neto')->first();

        return round((float) $this->saldo_inicial + (float) ($movs->neto ?? 0), 2);
    }
}
