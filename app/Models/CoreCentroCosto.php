<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreCentroCosto extends Model
{
    protected $table = 'core_centros_costos';

    protected $fillable = ['compania_id', 'codigo', 'nombre', 'activo', 'created_by', 'updated_by'];

    protected $casts = ['activo' => 'boolean'];
}
