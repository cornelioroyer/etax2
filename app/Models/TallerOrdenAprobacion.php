<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerOrdenAprobacion extends Model
{
    protected $table = 'taller_orden_aprobaciones';

    public $timestamps = false;

    public const TIPOS = [
        'presupuesto' => 'Presupuesto',
        'entrega'     => 'Entrega',
        'garantia'    => 'Garantía',
    ];

    protected $fillable = [
        'orden_id',
        'contacto_id',
        'tipo_aprobacion',
        'monto_aprobado',
        'aprobado',
        'fecha_respuesta',
        'medio',
        'observacion',
        'created_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'aprobado'        => 'boolean',
            'monto_aprobado'  => 'decimal:2',
            'fecha_respuesta' => 'datetime',
            'created_at'      => 'datetime',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(TallerOrden::class, 'orden_id');
    }
}
