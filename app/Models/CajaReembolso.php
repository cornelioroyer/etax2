<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CajaReembolso extends Model
{
    protected $table = 'caj_reembolsos';

    public const ESTADO_APLICADO = 'APLICADO';

    public const ESTADO_ANULADO = 'ANULADO';

    protected $fillable = [
        'caja_id',
        'fecha',
        'monto',
        'asiento_id',
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

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }
}
