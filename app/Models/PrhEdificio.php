<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrhEdificio extends Model
{
    protected $table = 'prh_edificios';

    protected $fillable = [
        'compania_id', 'codigo', 'nombre', 'direccion', 'descripcion', 'activo',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(PrhUnidad::class, 'edificio_id');
    }
}
