<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreDepartamento extends Model
{
    protected $table = 'core_departamentos';

    protected $fillable = ['compania_id', 'codigo', 'nombre', 'activo', 'created_by', 'updated_by'];

    protected $casts = ['activo' => 'boolean'];
}
