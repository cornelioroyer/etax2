<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CxpAplicacion extends Model
{
    protected $table = 'cxp_aplicaciones';

    protected $fillable = [
        'compania_id',
        'proveedor_id',
        'documento_origen_id',
        'documento_destino_id',
        'fecha',
        'monto_aplicado',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto_aplicado' => 'decimal:2',
        ];
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(CxpDocumento::class, 'documento_origen_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(CxpDocumento::class, 'documento_destino_id');
    }
}
