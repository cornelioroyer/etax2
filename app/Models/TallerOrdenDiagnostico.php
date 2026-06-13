<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerOrdenDiagnostico extends Model
{
    protected $table = 'taller_orden_diagnosticos';

    public const ESTADOS = [
        'pendiente'   => 'Pendiente',
        'en_proceso'  => 'En proceso',
        'completado'  => 'Completado',
        'anulado'     => 'Anulado',
    ];

    protected $fillable = [
        'orden_id',
        'tecnico_id',
        'falla_id',
        'fecha_inicio',
        'fecha_fin',
        'diagnostico',
        'causa',
        'solucion_propuesta',
        'requiere_aprobacion',
        'aprobado',
        'costo_estimado',
        'precio_estimado',
        'estado',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'requiere_aprobacion' => 'boolean',
            'aprobado'            => 'boolean',
            'fecha_inicio'        => 'datetime',
            'fecha_fin'           => 'datetime',
            'costo_estimado'      => 'decimal:2',
            'precio_estimado'     => 'decimal:2',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(TallerOrden::class, 'orden_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(TallerTecnico::class, 'tecnico_id');
    }
}
