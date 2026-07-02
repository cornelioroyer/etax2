<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NomDepartamento extends Model
{
    protected $table = 'nom_departamentos';

    protected $fillable = [
        'compania_id',
        'codigo',
        'nombre',
        'activo',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(NomEmpleado::class, 'departamento_id');
    }
}
