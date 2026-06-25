<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CxpRecurrente extends Model
{
    protected $table = 'cxp_recurrentes';

    public const ESTADO_ACTIVA = 'ACTIVA';

    public const ESTADO_PAUSADA = 'PAUSADA';

    public const ESTADO_FINALIZADA = 'FINALIZADA';

    /**
     * Frecuencias soportadas => cómo avanza la próxima fecha. Los avances
     * mensuales usan "no overflow" (ene-31 → feb-28) para no saltar de mes.
     */
    public const FRECUENCIAS = [
        'SEMANAL' => 'Semanal',
        'QUINCENAL' => 'Quincenal (cada 15 días)',
        'MENSUAL' => 'Mensual',
        'BIMESTRAL' => 'Bimestral (cada 2 meses)',
        'TRIMESTRAL' => 'Trimestral (cada 3 meses)',
        'SEMESTRAL' => 'Semestral (cada 6 meses)',
        'ANUAL' => 'Anual',
    ];

    protected $fillable = [
        'compania_id',
        'proveedor_id',
        'nombre',
        'referencia',
        'frecuencia',
        'fecha_inicio',
        'fecha_fin',
        'dias_credito',
        'ocurrencias_max',
        'ocurrencias_generadas',
        'proxima_fecha',
        'ultima_generacion',
        'estado',
        'subtotal',
        'impuesto',
        'total',
        'usuario_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'proxima_fecha' => 'date',
            'ultima_generacion' => 'date',
            'subtotal' => 'decimal:2',
            'impuesto' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'proveedor_id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(CxpRecurrenteDetalle::class, 'recurrente_id')->orderBy('linea');
    }

    /** Facturas de compra ya generadas desde esta plantilla. */
    public function facturasGeneradas(): HasMany
    {
        return $this->hasMany(CxpDocumento::class, 'recurrente_id');
    }

    public function esActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVA;
    }

    public function estaFinalizada(): bool
    {
        return $this->estado === self::ESTADO_FINALIZADA;
    }

    /** Avanza una fecha un período según la frecuencia de la plantilla. */
    public function siguienteFecha(Carbon $desde): Carbon
    {
        return match ($this->frecuencia) {
            'SEMANAL' => $desde->copy()->addWeek(),
            'QUINCENAL' => $desde->copy()->addDays(15),
            'MENSUAL' => $desde->copy()->addMonthNoOverflow(),
            'BIMESTRAL' => $desde->copy()->addMonthsNoOverflow(2),
            'TRIMESTRAL' => $desde->copy()->addMonthsNoOverflow(3),
            'SEMESTRAL' => $desde->copy()->addMonthsNoOverflow(6),
            'ANUAL' => $desde->copy()->addYearNoOverflow(),
            default => $desde->copy()->addMonthNoOverflow(),
        };
    }

    /**
     * ¿La próxima fecha sigue dentro de los límites de la plantilla
     * (fecha_fin y nº máximo de ocurrencias)?
     */
    public function dentroDeLimites(Carbon $fecha): bool
    {
        if ($this->fecha_fin && $fecha->gt($this->fecha_fin)) {
            return false;
        }

        if ($this->ocurrencias_max && $this->ocurrencias_generadas >= $this->ocurrencias_max) {
            return false;
        }

        return true;
    }

    public function etiquetaFrecuencia(): string
    {
        return self::FRECUENCIAS[$this->frecuencia] ?? $this->frecuencia;
    }
}
