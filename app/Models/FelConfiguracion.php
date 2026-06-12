<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FelConfiguracion extends Model
{
    protected $table = 'fel_configuracion';

    protected $fillable = [
        'compania_id',
        'ambiente',
        'proveedor',
        'token_empresa',
        'token_password',
        'punto_facturacion',
        'codigo_sucursal',
        'correlativo',
        'activa',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'token_empresa' => 'encrypted',
            'token_password' => 'encrypted',
            'activa' => 'boolean',
        ];
    }
}
