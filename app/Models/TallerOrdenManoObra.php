<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerOrdenManoObra extends Model
{
    protected $table = 'taller_orden_mano_obra';

    protected $fillable = [
        'orden_id',
        'tecnico_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'descripcion',
        'horas',
        'costo_hora',
        'precio_hora',
        'costo_total',
        'precio_total',
        'facturable',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha'        => 'date',
            'hora_inicio'  => 'datetime',
            'hora_fin'     => 'datetime',
            'horas'        => 'decimal:4',
            'costo_hora'   => 'decimal:4',
            'precio_hora'  => 'decimal:4',
            'costo_total'  => 'decimal:2',
            'precio_total' => 'decimal:2',
            'facturable'   => 'boolean',
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
