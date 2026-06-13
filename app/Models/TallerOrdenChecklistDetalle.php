<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerOrdenChecklistDetalle extends Model
{
    protected $table = 'taller_orden_checklist_detalle';

    public $timestamps = false;

    protected $fillable = [
        'orden_checklist_id',
        'checklist_detalle_id',
        'descripcion',
        'respuesta_texto',
        'respuesta_numero',
        'respuesta_fecha',
        'respuesta_bool',
        'observacion',
        'created_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'respuesta_bool'   => 'boolean',
            'respuesta_numero' => 'decimal:4',
            'respuesta_fecha'  => 'datetime',
            'created_at'       => 'datetime',
        ];
    }

    public function ordenChecklist(): BelongsTo
    {
        return $this->belongsTo(TallerOrdenChecklist::class, 'orden_checklist_id');
    }
}
