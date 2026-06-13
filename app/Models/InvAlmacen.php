<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvAlmacen extends Model
{
    protected $table = 'inv_almacenes';

    protected $fillable = ['compania_id', 'codigo', 'nombre', 'activo', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(InvMovimiento::class, 'almacen_id');
    }

    public function existencias(): HasMany
    {
        return $this->hasMany(InvExistencia::class, 'almacen_id');
    }
}
