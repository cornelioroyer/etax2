<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DimUbicacion extends Model
{
    protected $table = 'dim_ubicaciones';

    protected $fillable = ['compania_id', 'codigo', 'nombre', 'activo', 'created_by', 'updated_by'];

    public function compania(): BelongsTo
    {
        return $this->belongsTo(Compania::class);
    }

    public function asientosDetalle(): HasMany
    {
        return $this->hasMany(AsientoDetalle::class, 'ubicacion_id');
    }
}
