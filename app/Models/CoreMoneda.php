<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoreMoneda extends Model
{
    protected $table = 'core_monedas';

    protected $fillable = [
        'compania_id', 'codigo', 'nombre', 'simbolo', 'activa',
        'created_by', 'updated_by',
    ];

    protected $casts = ['activa' => 'boolean'];

    public function tasas(): HasMany
    {
        return $this->hasMany(CoreTasaCambio::class, 'moneda_id')->orderByDesc('fecha');
    }
}
