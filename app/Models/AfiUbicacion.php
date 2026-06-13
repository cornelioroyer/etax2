<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AfiUbicacion extends Model
{
    protected $table = 'afi_ubicaciones';

    protected $fillable = [
        'compania_id', 'codigo', 'nombre',
        'created_by', 'updated_by',
    ];

    public function compania(): BelongsTo
    {
        return $this->belongsTo(Compania::class);
    }

    public function activos(): HasMany
    {
        return $this->hasMany(AfiActivo::class, 'ubicacion_id');
    }
}
