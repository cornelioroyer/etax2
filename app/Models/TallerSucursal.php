<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallerSucursal extends Model
{
    protected $table = 'taller_sucursales';

    protected $fillable = [
        'taller_id', 'codigo', 'nombre', 'direccion', 'telefono',
        'email', 'almacen_id', 'activo', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function taller(): BelongsTo
    {
        return $this->belongsTo(TallerTaller::class, 'taller_id');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(TallerArea::class, 'sucursal_id');
    }
}
