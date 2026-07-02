<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Novedad de nómina: ingreso o deducción por empleado que alimenta la corrida.
 * FIJA = aplica en cada período mientras esté vigente (cuota de préstamo, bono
 * fijo). VARIABLE = aplica solo al período indicado (horas extra, comisión).
 */
class NomNovedad extends Model
{
    protected $table = 'nom_novedades';

    public const TIPO_FIJA = 'FIJA';

    public const TIPO_VARIABLE = 'VARIABLE';

    protected $fillable = [
        'compania_id',
        'empleado_id',
        'concepto_id',
        'tipo_registro',
        'periodo_id',
        'cantidad',
        'monto',
        'vigente_desde',
        'vigente_hasta',
        'descripcion',
        'activo',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:4',
            'monto' => 'decimal:2',
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
            'activo' => 'boolean',
        ];
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(NomEmpleado::class, 'empleado_id');
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(NomConcepto::class, 'concepto_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(NomPeriodo::class, 'periodo_id');
    }

    /** ¿Esta novedad aplica al período dado? */
    public function aplicaA(NomPeriodo $periodo): bool
    {
        if (! $this->activo) {
            return false;
        }

        if ($this->tipo_registro === self::TIPO_VARIABLE) {
            return $this->periodo_id === $periodo->id;
        }

        // FIJA: vigente si su rango cubre el período
        if ($this->vigente_desde && $this->vigente_desde->gt($periodo->hasta)) {
            return false;
        }

        if ($this->vigente_hasta && $this->vigente_hasta->lt($periodo->desde)) {
            return false;
        }

        return true;
    }
}
