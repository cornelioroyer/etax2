<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCuenta extends Model
{
    protected $table = 'cgl_tipos_cuenta';

    protected $fillable = ['codigo', 'nombre', 'naturaleza', 'created_by', 'updated_by'];
}
