<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BancoCuenta extends Model
{
    protected $table = 'banco_cuentas';

    protected $fillable = [
        'compania_id',
        'banco_nombre',
        'numero_cuenta',
        'tipo',
        'moneda',
        'cuenta_contable_id',
        'saldo_inicial',
        'activa',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activa'        => 'boolean',
            'saldo_inicial' => 'decimal:2',
        ];
    }

    const TIPOS = ['CORRIENTE' => 'Corriente', 'AHORROS' => 'Ahorros', 'INVERSION' => 'Inversión'];
    const MONEDAS = ['PAB' => 'Balboa (PAB)', 'USD' => 'Dólar (USD)'];

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_contable_id');
    }
}
