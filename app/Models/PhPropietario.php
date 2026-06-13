<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhPropietario extends Model
{
    protected $table = 'ph_propietarios';

    protected $fillable = [
        'compania_id', 'identificacion', 'nombre', 'email', 'telefono', 'direccion', 'activo',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(PhUnidad::class, 'propietario_id');
    }
}
