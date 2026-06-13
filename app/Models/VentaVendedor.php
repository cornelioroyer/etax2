<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VentaVendedor extends Model
{
    protected $table = 'ventas_vendedores';

    protected $fillable = [
        'compania_id', 'contacto_id', 'usuario_id', 'codigo', 'activo',
        'created_by', 'updated_by',
    ];

    protected $casts = ['activo' => 'boolean'];

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }

    public function comisiones(): HasMany
    {
        return $this->hasMany(VentaComision::class, 'vendedor_id');
    }

    public function getNombreAttribute(): string
    {
        return $this->contacto?->nombre ?? $this->codigo ?? "Vendedor #{$this->id}";
    }
}
