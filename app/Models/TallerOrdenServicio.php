<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerOrdenServicio extends Model
{
    protected $table = 'taller_orden_servicios';

    public const ESTADOS = [
        'pendiente'   => 'Pendiente',
        'en_proceso'  => 'En proceso',
        'completado'  => 'Completado',
        'anulado'     => 'Anulado',
    ];

    protected $fillable = [
        'orden_id',
        'servicio_id',
        'tecnico_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'descuento',
        'impuesto_id',
        'impuesto',
        'total',
        'estado',
        'garantia_dias',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'       => 'decimal:4',
            'precio_unitario'=> 'decimal:4',
            'descuento'      => 'decimal:2',
            'impuesto'       => 'decimal:2',
            'total'          => 'decimal:2',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(TallerOrden::class, 'orden_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(TallerServicioEstandar::class, 'servicio_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(TallerTecnico::class, 'tecnico_id');
    }
}
