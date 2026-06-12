<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PeriodoContable extends Model
{
    protected $table = 'cgl_periodos';

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
                'estado' => 'ABIERTO',
                'created_by' => $usuario,
            ]
        );
    }

    public function estaAbierto(): bool
    {
        return $this->estado === 'ABIERTO';
    }
}
