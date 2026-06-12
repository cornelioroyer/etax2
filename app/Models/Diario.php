<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Diario extends Model
{
    protected $table = 'cgl_diarios';

    protected $fillable = [
        'compania_id',
        'codigo',
        'nombre',
        'tipo_diario',
        'cuenta_default_id',
        'requiere_aprobacion',
        'activo',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'requiere_aprobacion' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * Diario GENERAL de la compañía (lo crea si no existe).
     */
    public static function general(int $companiaId, ?string $usuario = null): self
    {
        return self::firstOrCreate(
            ['compania_id' => $companiaId, 'codigo' => 'GENERAL'],
            [
                'nombre' => 'Diario General',
                'tipo_diario' => 'GENERAL',
                'activo' => true,
                'created_by' => $usuario,
            ]
        );
    }
}
