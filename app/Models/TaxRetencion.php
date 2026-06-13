<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRetencion extends Model
{
    protected $table = 'tax_retenciones';

    const TIPO_ITBMS    = 'ITBMS';
    const TIPO_ISR      = 'ISR';
    const TIPO_OTRO     = 'OTRO';

    protected $fillable = [
        'compania_id', 'codigo', 'nombre', 'tipo', 'porcentaje',
        'cuenta_id', 'activa', 'created_by', 'updated_by',
    ];

    protected $casts = ['porcentaje' => 'decimal:4', 'activa' => 'boolean'];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_id');
    }
}
