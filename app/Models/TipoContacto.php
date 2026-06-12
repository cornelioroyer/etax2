<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoContacto extends Model
{
    protected $table = 'contact_tipos';

    protected $fillable = ['codigo', 'nombre', 'created_by', 'updated_by'];
}
