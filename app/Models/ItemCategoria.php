<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemCategoria extends Model
{
    protected $table = 'item_categorias';

    protected $fillable = ['compania_id', 'nombre', 'activa', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(ItemProducto::class, 'categoria_id');
    }
}
