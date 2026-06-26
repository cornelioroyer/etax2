<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plantilla (maestro GLOBAL) de plan de cuentas: el catálogo que se copia a una
 * compañía nueva cuando su plan está vacío (ver App\Services\PlantillaCuentas).
 *
 * No tiene compania_id: las plantillas las comparten todas las compañías y solo
 * las administra un super_admin.
 */
class PlantillaCuenta extends Model
{
    protected $table = 'core_plantillas_cuentas';

    protected $fillable = [
        'codigo',
        'nombre',
        'pais',
        'descripcion',
        'activa',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PlantillaCuentaDetalle::class, 'plantilla_id');
    }
}
