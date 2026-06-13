<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caja extends Model
{
    protected $table = 'caj_cajas';

    protected $fillable = [
        'compania_id',
        'codigo',
        'nombre',
        'cuenta_contable_id',
        'responsable_id',
        'activa',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_contable_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(CajaMovimiento::class, 'caja_id')->orderByDesc('fecha')->orderByDesc('id');
    }

    public function reembolsos(): HasMany
    {
        return $this->hasMany(CajaReembolso::class, 'caja_id')->orderByDesc('fecha')->orderByDesc('id');
    }

    public function vales(): HasMany
    {
        return $this->hasMany(CajaVale::class, 'caja_id')->orderByDesc('fecha')->orderByDesc('id');
    }

    public function arqueos(): HasMany
    {
        return $this->hasMany(CajaArqueo::class, 'caja_id')->orderByDesc('fecha')->orderByDesc('id');
    }

    /**
     * Saldo en efectivo que debería haber en la caja:
     *   ingresos (reembolsos aplicados + movimientos INGRESO)
     *   − egresos (movimientos EGRESO)
     *   − vales pendientes (efectivo entregado aún sin liquidar)
     */
    public function saldoSistema(): float
    {
        $reembolsos = (float) $this->reembolsos()
            ->where('estado', CajaReembolso::ESTADO_APLICADO)->sum('monto');

        $ingresos = (float) $this->movimientos()
            ->where('tipo_movimiento', CajaMovimiento::TIPO_INGRESO)->sum('monto');

        $egresos = (float) $this->movimientos()
            ->where('tipo_movimiento', CajaMovimiento::TIPO_EGRESO)->sum('monto');

        $valesPendientes = (float) $this->vales()
            ->where('estado', CajaVale::ESTADO_PENDIENTE)->sum('monto');

        return round($reembolsos + $ingresos - $egresos - $valesPendientes, 2);
    }
}
