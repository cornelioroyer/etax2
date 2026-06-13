<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BcoDeposito extends Model
{
    protected $table = 'bco_depositos';

    protected $fillable = [
        'compania_id', 'cuenta_bancaria_id', 'fecha', 'referencia',
        'monto', 'asiento_id', 'created_by', 'updated_by',
    ];

    protected $casts = ['fecha' => 'date', 'monto' => 'decimal:2'];

    public function cuentaBancaria(): BelongsTo
    {
        return $this->belongsTo(BcoCuenta::class, 'cuenta_bancaria_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(CglAsiento::class, 'asiento_id');
    }
}
