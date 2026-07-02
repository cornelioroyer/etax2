<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NomPeriodo extends Model
{
    protected $table = 'nom_periodos';

    public const ESTADO_ABIERTO = 'ABIERTO';

    public const ESTADO_CERRADO = 'CERRADO';

    protected $fillable = [
        'compania_id',
        'tipo_planilla',
        'anio',
        'numero',
        'desde',
        'hasta',
        'fecha_pago',
        'estado',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'desde' => 'date',
            'hasta' => 'date',
            'fecha_pago' => 'date',
        ];
    }

    public function planillas(): HasMany
    {
        return $this->hasMany(NomPlanilla::class, 'periodo_id');
    }

    public function estaAbierto(): bool
    {
        return $this->estado === self::ESTADO_ABIERTO;
    }

    public function etiqueta(): string
    {
        return sprintf(
            '%s %d-%02d (%s al %s)',
            NomEmpleado::TIPOS_PLANILLA[$this->tipo_planilla] ?? $this->tipo_planilla,
            $this->anio,
            $this->numero,
            $this->desde?->format('d/m'),
            $this->hasta?->format('d/m/Y'),
        );
    }
}
