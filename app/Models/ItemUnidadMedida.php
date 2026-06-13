<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemUnidadMedida extends Model
{
    protected $table = 'item_unidades_medida';

    protected $fillable = ['codigo', 'nombre', 'created_by', 'updated_by'];
}
