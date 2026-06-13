<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CajaMovimiento extends Model
{
    protected $table = 'caj_movimientos';

    public const TIPO_EGRESO = 'EGRESO';

    public const TIPO_INGRESO = 'INGRESO';

    protected $fillable = [
        'compania_id',
        'caja_id',
        'fecha',
        'tipo_movimiento',
        'beneficiario',
        'descripcion',
        'monto',
        'cuenta_contable_id',
        'centro_costo_id',
        'proyecto_id',
        'asiento_id',
        'adjunto_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_contable_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }
}
