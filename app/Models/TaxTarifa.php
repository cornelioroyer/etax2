<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxTarifa extends Model
{
    protected $table = 'tax_tarifas';

    protected $fillable = [
        'impuesto_id', 'fecha_inicio', 'fecha_fin', 'porcentaje',
        'activa', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
        'porcentaje'   => 'decimal:4',
        'activa'       => 'boolean',
    ];

    public function impuesto(): BelongsTo
    {
        return $this->belongsTo(TaxImpuesto::class, 'impuesto_id');
    }
}
