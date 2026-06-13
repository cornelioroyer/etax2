<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CajaVale extends Model
{
    protected $table = 'caj_vales';

    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_LIQUIDADO = 'LIQUIDADO';

    public const ESTADO_ANULADO = 'ANULADO';

    protected $fillable = [
        'caja_id',
        'fecha',
        'beneficiario',
        'monto',
        'motivo',
        'adjunto_id',
        'estado',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }
}
