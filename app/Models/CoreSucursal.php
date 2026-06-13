<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreSucursal extends Model
{
    protected $table = 'core_sucursales';

    protected $fillable = [
        'compania_id', 'codigo', 'nombre', 'direccion', 'telefono',
        'activa', 'created_by', 'updated_by',
    ];

    protected $casts = ['activa' => 'boolean'];
}
