<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerCita extends Model
{
    protected $table = 'taller_citas';

    public const ESTADOS = [
        'programada'  => 'Programada',
        'confirmada'  => 'Confirmada',
        'atendida'    => 'Atendida',
        'cancelada'   => 'Cancelada',
        'no_asistio'  => 'No asistió',
    ];

    protected $fillable = [
        'taller_id', 'sucursal_id', 'area_id', 'cliente_id',
        'equipo_id', 'tecnico_id',
        'fecha_inicio', 'fecha_fin',
        'motivo', 'estado', 'presupuesto_id',
        'compania_id',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_fin'    => 'datetime',
        ];
    }

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function taller(): BelongsTo
    {
        return $this->belongsTo(TallerTaller::class, 'taller_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'cliente_id');
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(TallerEquipo::class, 'equipo_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(TallerTecnico::class, 'tecnico_id');
    }
}
