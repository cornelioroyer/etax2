<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallerControlCalidad extends Model
{
    protected $table = 'taller_control_calidad';

    public $timestamps = false;

    public const RESULTADOS = [
        'pendiente'           => 'Pendiente',
        'aprobado'            => 'Aprobado',
        'rechazado'           => 'Rechazado',
        'requiere_correccion' => 'Requiere corrección',
    ];

    protected $fillable = [
        'orden_id', 'tecnico_id', 'usuario_id',
        'fecha', 'resultado', 'observacion',
        'created_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha'      => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function orden(): BelongsTo
    {
        return $this->belongsTo(TallerOrden::class, 'orden_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(TallerTecnico::class, 'tecnico_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(TallerControlCalidadDetalle::class, 'control_calidad_id');
    }
}
