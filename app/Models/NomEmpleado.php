<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NomEmpleado extends Model
{
    protected $table = 'nom_empleados';

    public const STATUS_ACTIVO = 'ACTIVO';

    public const STATUS_VACACIONES = 'VACACIONES';

    public const STATUS_LICENCIA = 'LICENCIA';

    public const STATUS_INACTIVO = 'INACTIVO';

    public const STATUS_TERMINADO = 'TERMINADO';

    public const STATUSES = [
        self::STATUS_ACTIVO => 'Activo',
        self::STATUS_VACACIONES => 'De vacaciones',
        self::STATUS_LICENCIA => 'Con licencia',
        self::STATUS_INACTIVO => 'Inactivo',
        self::STATUS_TERMINADO => 'Terminado',
    ];

    public const TIPO_SALARIO_FIJO = 'FIJO';

    public const TIPO_SALARIO_POR_HORA = 'POR_HORA';

    public const TIPOS_PLANILLA = [
        'SEMANAL' => 'Semanal',
        'QUINCENAL' => 'Quincenal',
        'MENSUAL' => 'Mensual',
    ];

    public const FORMAS_PAGO = [
        'TRANSFERENCIA' => 'Transferencia / ACH',
        'CHEQUE' => 'Cheque',
        'EFECTIVO' => 'Efectivo',
    ];

    protected $fillable = [
        'compania_id',
        'codigo',
        'nombre',
        'apellido',
        'cedula',
        'seguro_social',
        'fecha_nacimiento',
        'sexo',
        'estado_civil',
        'email',
        'telefono',
        'direccion',
        'fecha_inicio',
        'fecha_terminacion',
        'tipo_salario',
        'salario_mensual',
        'tasa_hora',
        'horas_semanales',
        'tipo_planilla',
        'forma_pago',
        'banco',
        'cuenta_bancaria',
        'tipo_cuenta',
        'departamento_id',
        'cargo_id',
        'dependientes',
        'status',
        'observacion',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_inicio' => 'date',
            'fecha_terminacion' => 'date',
            'salario_mensual' => 'decimal:2',
            'tasa_hora' => 'decimal:4',
            'horas_semanales' => 'decimal:2',
        ];
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(NomDepartamento::class, 'departamento_id');
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(NomCargo::class, 'cargo_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(NomMovimiento::class, 'empleado_id');
    }

    public function novedades(): HasMany
    {
        return $this->hasMany(NomNovedad::class, 'empleado_id');
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre.' '.$this->apellido);
    }

    /** ¿Entra en la corrida de planilla? (activo o de vacaciones cobra) */
    public function pagable(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVO, self::STATUS_VACACIONES], true);
    }

    public function esPorHora(): bool
    {
        return $this->tipo_salario === self::TIPO_SALARIO_POR_HORA;
    }

    /**
     * Salario del período según el tipo de planilla del empleado (para salario
     * FIJO). Semanal y quincenal usan la convención panameña salario mensual /
     * cantidad de pagos del mes contractual.
     */
    public function salarioDelPeriodo(string $tipoPlanilla): float
    {
        return match ($tipoPlanilla) {
            'SEMANAL' => round((float) $this->salario_mensual * 12 / 52, 2),
            'QUINCENAL' => round((float) $this->salario_mensual / 2, 2),
            default => (float) $this->salario_mensual,
        };
    }

    public function etiquetaStatus(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
