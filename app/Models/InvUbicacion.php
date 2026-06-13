<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvUbicacion extends Model
{
    protected $table = 'inv_ubicaciones';

    protected $fillable = ['almacen_id', 'codigo', 'nombre', 'created_by', 'updated_by'];

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(InvAlmacen::class, 'almacen_id');
    }
}
