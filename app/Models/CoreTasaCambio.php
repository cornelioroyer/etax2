<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreTasaCambio extends Model
{
    protected $table = 'core_tasas_cambio';

    protected $fillable = ['moneda_id', 'fecha', 'tasa', 'created_by', 'updated_by'];

    protected $casts = ['fecha' => 'date', 'tasa' => 'decimal:6'];

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(CoreMoneda::class, 'moneda_id');
    }
}
