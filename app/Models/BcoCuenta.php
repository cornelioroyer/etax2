<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BcoCuenta extends Model
{
    protected $table = 'bco_cuentas';

    public const TIPOS = [
        'CORRIENTE'        => 'Corriente',
        'AHORROS'          => 'Ahorros',
        'INVERSION'        => 'Inversión',
        'TARJETA_CREDITO'  => 'Tarjeta de crédito',
        'LINEA_CREDITO'    => 'Línea de crédito',
    ];

    /**
     * Tipos cuya cuenta contable es de PASIVO (saldo acreedor): la deuda crece
     * con los cargos (crédito en el mayor → débito/egreso en bancos). El saldo
     * del módulo se calcula con signo invertido para mostrar la deuda en
     * positivo y que cuadre contra el estado de cuenta de la tarjeta.
     */
    public const TIPOS_PASIVO = ['TARJETA_CREDITO', 'LINEA_CREDITO'];

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

    /** ¿La cuenta representa un pasivo (tarjeta / línea de crédito)? */
    public function esPasivo(): bool
    {
        return in_array($this->tipo_cuenta, self::TIPOS_PASIVO, true);
    }

    /**
     * Saldo a partir del neto de movimientos (créditos - débitos), aplicando el
     * signo según la naturaleza de la cuenta. Activo (banco): saldo sube con los
     * ingresos. Pasivo (tarjeta): la deuda sube con los cargos, así que se
     * invierte el neto para mostrarla en positivo.
     */
    public function saldoDesdeNeto(float $neto): float
    {
        $neto = $this->esPasivo() ? -$neto : $neto;

        return round((float) $this->saldo_inicial + $neto, 2);
    }

    /** Saldo calculado a la fecha actual (con signo según naturaleza). */
    public function getSaldoActualAttribute(): float
    {
        $neto = (float) $this->movimientos()
            ->selectRaw('COALESCE(SUM(credito),0) - COALESCE(SUM(debito),0) as neto')
            ->value('neto');

        return $this->saldoDesdeNeto($neto);
    }
}
