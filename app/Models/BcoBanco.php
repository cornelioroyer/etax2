<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BcoBanco extends Model
{
    protected $table = 'bco_bancos';

    protected $fillable = ['codigo', 'nombre', 'activo', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function cuentas(): HasMany
    {
        return $this->hasMany(BcoCuenta::class, 'banco_id');
    }
}
