<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxImpuesto extends Model
{
    protected $table = 'tax_impuestos';

    protected $fillable = ['compania_id', 'codigo', 'nombre', 'tipo', 'porcentaje', 'activo'];

    protected function casts(): array
    {
        return ['porcentaje' => 'decimal:2', 'activo' => 'boolean'];
    }

    /** Las 4 tasas ITBMS globales (compania_id null) ordenadas por porcentaje. */
    public static function itbmsGlobales()
    {
        return static::whereNull('compania_id')
            ->where('tipo', 'VENTAS')
            ->where('activo', true)
            ->orderBy('porcentaje')
            ->get(['id', 'codigo', 'nombre', 'porcentaje']);
    }
}
