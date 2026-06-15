<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BcoMovimiento extends Model
{
    protected $table = 'bco_movimientos';

    public const TIPO_DEPOSITO      = 'DEPOSITO';
    public const TIPO_CHEQUE        = 'CHEQUE';
    public const TIPO_TRANSFERENCIA = 'TRANSFERENCIA';
    public const TIPO_PAGO          = 'PAGO';
    public const TIPO_COBRO         = 'COBRO';
    public const TIPO_CARGO         = 'CARGO';
    public const TIPO_INTERES       = 'INTERES';
    public const TIPO_ASIENTO       = 'ASIENTO';
    public const TIPO_OTRO          = 'OTRO';

    public const TIPOS = [
        'DEPOSITO'      => 'Depósito',
        'CHEQUE'        => 'Cheque emitido',
        'TRANSFERENCIA' => 'Transferencia',
        'PAGO'          => 'Pago a proveedor',
        'COBRO'         => 'Cobro de cliente',
        'CARGO'         => 'Cargo bancario',
        'INTERES'       => 'Interés',
        'ASIENTO'       => 'Asiento contable',
        'OTRO'          => 'Otro',
    ];

    protected $fillable = [
        'compania_id', 'cuenta_bancaria_id', 'fecha', 'tipo_movimiento',
        'descripcion', 'referencia', 'debito', 'credito', 'saldo',
        'contacto_id', 'conciliado', 'asiento_id', 'documento_origen',
        'documento_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha'      => 'date',
            'debito'     => 'decimal:2',
            'credito'    => 'decimal:2',
            'saldo'      => 'decimal:2',
            'conciliado' => 'boolean',
        ];
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(BcoCuenta::class, 'cuenta_bancaria_id');
    }

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }
}
