<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BcoConciliacion extends Model
{
    protected $table = 'bco_conciliaciones';

    public const ESTADO_ABIERTA = 'ABIERTA';
    public const ESTADO_CERRADA = 'CERRADA';
    public const ESTADO_ANULADA = 'ANULADA';

    protected $fillable = [
        'compania_id', 'cuenta_bancaria_id', 'cuenta_contable_id', 'fecha_corte',
        'saldo_banco', 'saldo_libros', 'diferencia', 'estado', 'usuario_id',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_corte'  => 'date',
            'saldo_banco'  => 'decimal:2',
            'saldo_libros' => 'decimal:2',
            'diferencia'   => 'decimal:2',
        ];
    }

    public function cuentaBancaria(): BelongsTo
    {
        return $this->belongsTo(BcoCuenta::class, 'cuenta_bancaria_id');
    }

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_contable_id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(BcoConciliacionDetalle::class, 'conciliacion_id');
    }

    public function esCerrada(): bool
    {
        return $this->estado === self::ESTADO_CERRADA;
    }
}
