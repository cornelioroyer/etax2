<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AsientoRecurrente extends Model
{
    protected $table = 'cgl_asientos_recurrentes';

    public const ESTADO_ACTIVA = 'ACTIVA';

    public const ESTADO_PAUSADA = 'PAUSADA';

    public const ESTADO_FINALIZADA = 'FINALIZADA';

    /** Origen que llevan los asientos generados desde una plantilla. */
    public const ORIGEN_TABLA = 'cgl_asientos_recurrentes';

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
        'diario_id',
        'nombre',
        'descripcion',
        'referencia',
        'frecuencia',
        'fecha_inicio',
        'fecha_fin',
        'ocurrencias_max',
        'ocurrencias_generadas',
        'proxima_fecha',
        'ultima_generacion',
        'estado',
        'total_debito',
        'total_credito',
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
            'total_debito' => 'decimal:2',
            'total_credito' => 'decimal:2',
        ];
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(AsientoRecurrenteDetalle::class, 'recurrente_id')->orderBy('linea');
    }

    public function diario(): BelongsTo
    {
        return $this->belongsTo(Diario::class, 'diario_id');
    }

    /** Asientos ya generados a partir de esta plantilla. */
    public function asientosGenerados(): HasMany
    {
        return $this->hasMany(Asiento::class, 'origen_id')
            ->where('origen_tabla', self::ORIGEN_TABLA);
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
