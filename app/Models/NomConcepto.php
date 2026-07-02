<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Concepto de nómina. Hereda la numeración del sistema planilla legacy
 * (03=Salario, 102=CSS, 103=SE, 104=ISR, 108=Vacaciones, 109=XIII, 220=Prima
 * de Antigüedad) para que contadores y reportes hablen el mismo idioma.
 * El monto en nom_movimientos SIEMPRE es positivo; el efecto lo da `tipo`.
 */
class NomConcepto extends Model
{
    protected $table = 'nom_conceptos';

    public const TIPO_INGRESO = 'INGRESO';

    public const TIPO_DEDUCCION = 'DEDUCCION';

    public const TIPO_PATRONAL = 'PATRONAL';

    public const TIPOS = [
        self::TIPO_INGRESO => 'Ingreso',
        self::TIPO_DEDUCCION => 'Deducción',
        self::TIPO_PATRONAL => 'Aporte patronal',
    ];

    public const CALCULO_MANUAL = 'MANUAL';

    public const CALCULO_SALARIO = 'SALARIO';

    public const CALCULO_PORCENTAJE = 'PORCENTAJE';

    public const CALCULO_ISR = 'ISR';

    // Códigos del motor (misma numeración que planilla legacy)
    public const COD_SALARIO = '03';

    public const COD_CSS = '102';

    public const COD_SEGURO_EDUCATIVO = '103';

    public const COD_ISR = '104';

    public const COD_HORAS_EXTRA = '05';

    public const COD_VACACIONES = '108';

    public const COD_XIII = '109';

    public const COD_PRIMA_ANTIGUEDAD = '220';

    public const COD_CSS_PATRONO = '902';

    public const COD_SE_PATRONO = '903';

    public const COD_RIESGO_PROFESIONAL = '904';

    protected $fillable = [
        'compania_id',
        'codigo',
        'descripcion',
        'tipo',
        'calculo',
        'porcentaje',
        'gravable_css',
        'gravable_isr',
        'acumula_xiii',
        'acumula_vacaciones',
        'cuenta_gasto_id',
        'cuenta_pasivo_id',
        'imprime_en_recibo',
        'orden_impresion',
        'de_sistema',
        'activo',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'porcentaje' => 'decimal:4',
            'gravable_css' => 'boolean',
            'gravable_isr' => 'boolean',
            'acumula_xiii' => 'boolean',
            'acumula_vacaciones' => 'boolean',
            'imprime_en_recibo' => 'boolean',
            'de_sistema' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function cuentaGasto(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_gasto_id');
    }

    public function movimientos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NomMovimiento::class, 'concepto_id');
    }

    public function novedades(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NomNovedad::class, 'concepto_id');
    }

    public function cuentaPasivo(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_pasivo_id');
    }

    public function esIngreso(): bool
    {
        return $this->tipo === self::TIPO_INGRESO;
    }

    public function esDeduccion(): bool
    {
        return $this->tipo === self::TIPO_DEDUCCION;
    }

    public function esPatronal(): bool
    {
        return $this->tipo === self::TIPO_PATRONAL;
    }

    public function etiquetaTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }
}
