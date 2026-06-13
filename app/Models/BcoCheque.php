<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BcoCheque extends Model
{
    protected $table = 'bco_cheques';

    public const ESTADO_EMITIDO  = 'EMITIDO';
    public const ESTADO_COBRADO  = 'COBRADO';
    public const ESTADO_ANULADO  = 'ANULADO';
    public const ESTADO_CADUCADO = 'CADUCADO';

    public const ESTADOS = [
        'EMITIDO'  => 'Emitido',
        'COBRADO'  => 'Cobrado',
        'ANULADO'  => 'Anulado',
        'CADUCADO' => 'Caducado',
    ];

    protected $fillable = [
        'compania_id', 'cuenta_bancaria_id', 'numero_cheque', 'fecha',
        'beneficiario_id', 'monto', 'estado', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(BcoCuenta::class, 'cuenta_bancaria_id');
    }

    public function cuentaBancaria(): BelongsTo
    {
        return $this->belongsTo(BcoCuenta::class, 'cuenta_bancaria_id');
    }

    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'beneficiario_id');
    }
}
