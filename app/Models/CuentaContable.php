<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuentaContable extends Model
{
    protected $table = 'cgl_cuentas';

    protected $fillable = [
        'compania_id',
        'codigo',
        'nombre',
        'cuenta_padre_id',
        'nivel',
        'tipo_cuenta_id',
        'naturaleza',
        'permite_movimiento',
        'requiere_contacto',
        'requiere_centro_costo',
        'requiere_proyecto',
        'conciliable',
        'activa',
        'renglon_isr',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'permite_movimiento' => 'boolean',
            'requiere_contacto' => 'boolean',
            'requiere_centro_costo' => 'boolean',
            'requiere_proyecto' => 'boolean',
            'conciliable' => 'boolean',
            'activa' => 'boolean',
        ];
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cuenta_padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'cuenta_padre_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoCuenta::class, 'tipo_cuenta_id');
    }
}
