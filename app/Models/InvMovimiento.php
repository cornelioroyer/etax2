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
        'estado', 'reversa_de_id', 'created_by', 'updated_by',
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

    /** Movimiento original que este reverso compensa (null si no es un reverso). */
    public function reversaDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversa_de_id');
    }

    /** Reverso vigente que compensa a este movimiento (si fue reversado). */
    public function reversadoPor(): HasMany
    {
        return $this->hasMany(self::class, 'reversa_de_id')->where('estado', '!=', 'ANULADO');
    }

    /** Este movimiento es la transacción de reverso de otro. */
    public function esReverso(): bool
    {
        return $this->reversa_de_id !== null;
    }
}
