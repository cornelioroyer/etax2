<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodoContable extends Model
{
    protected $table = 'cgl_periodos';

    public const ESTADO_ABIERTO = 'ABIERTO';

    public const ESTADO_CERRADO = 'CERRADO';

    /** Mes reservado para el período de ajuste / cierre anual. */
    public const MES_AJUSTE = 13;

    protected $fillable = [
        'compania_id',
        'anio',
        'mes',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'cerrado_por',
        'fecha_cierre',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'fecha_cierre' => 'datetime',
        ];
    }

    /**
     * Período del mes de la fecha dada (lo crea ABIERTO si no existe).
     */
    public static function paraFecha(int $companiaId, Carbon $fecha, ?string $usuario = null): self
    {
        return self::firstOrCreate(
            ['compania_id' => $companiaId, 'anio' => $fecha->year, 'mes' => $fecha->month],
            [
                'fecha_inicio' => $fecha->copy()->startOfMonth()->toDateString(),
                'fecha_fin' => $fecha->copy()->endOfMonth()->toDateString(),
                'estado' => self::ESTADO_ABIERTO,
                'created_by' => $usuario,
            ]
        );
    }

    /**
     * Período de ajuste (mes 13) del año, para el asiento de cierre anual.
     * Lo crea ABIERTO si no existe. Su fecha es el 31 de diciembre del año.
     */
    public static function ajusteAnual(int $companiaId, int $anio, ?string $usuario = null): self
    {
        $fin = Carbon::create($anio, 12, 31);

        return self::firstOrCreate(
            ['compania_id' => $companiaId, 'anio' => $anio, 'mes' => self::MES_AJUSTE],
            [
                'fecha_inicio' => $fin->toDateString(),
                'fecha_fin' => $fin->toDateString(),
                'estado' => self::ESTADO_ABIERTO,
                'created_by' => $usuario,
            ]
        );
    }

    public function esAjuste(): bool
    {
        return $this->mes === self::MES_AJUSTE;
    }

    public function estaAbierto(): bool
    {
        return $this->estado === self::ESTADO_ABIERTO;
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }
}
