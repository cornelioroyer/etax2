<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo DGI "Otros costos y gastos" (anexo 94 de la declaración de renta).
 * Catálogo GLOBAL (sin compañía); ids espejo de dba.v_otros_costos_gastos de planilla.
 */
class OtroCostoGasto extends Model
{
    protected $table = 'core_otros_costos_gastos';

    public $timestamps = false;

    protected $fillable = ['id', 'descripcion', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
