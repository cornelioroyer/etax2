<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvMovimiento extends Model
{
    protected $table = 'inv_movimientos';

    public const TIPO_ENTRADA      = 'ENTRADA';
    public const TIPO_SALIDA       = 'SALIDA';
    public const TIPO_AJUSTE       = 'AJUSTE';
    public const TIPO_TRANSFERENCIA = 'TRANSFERENCIA';

    protected $fillable = [
        'compania_id', 'almacen_id', 'fecha', 'tipo_movimiento',
        'documento_origen', 'documento_id', 'descripcion', 'asiento_id',
        'estado', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(InvAlmacen::class, 'almacen_id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(InvMovimientoDetalle::class, 'movimiento_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }
}
