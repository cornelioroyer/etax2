<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CajaArqueo extends Model
{
    protected $table = 'caj_arqueos';

    public const ESTADO_CERRADO = 'CERRADO';

    protected $fillable = [
        'caja_id',
        'fecha',
        'saldo_sistema',
        'saldo_fisico',
        'diferencia',
        'usuario_id',
        'estado',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'saldo_sistema' => 'decimal:2',
            'saldo_fisico' => 'decimal:2',
            'diferencia' => 'decimal:2',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'caja_id');
    }

    public function detalle(): HasMany
    {
        return $this->hasMany(CajaArqueoDetalle::class, 'arqueo_id');
    }
}
