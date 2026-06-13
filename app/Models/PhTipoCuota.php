<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhTipoCuota extends Model
{
    protected $table = 'ph_tipos_cuota';

    const PERIODICIDADES = ['MENSUAL', 'TRIMESTRAL', 'SEMESTRAL', 'ANUAL', 'EVENTUAL'];

    protected $fillable = [
        'compania_id', 'codigo', 'nombre', 'descripcion', 'monto_base', 'periodicidad', 'activo',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activo'      => 'boolean',
            'monto_base'  => 'decimal:2',
        ];
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(PhCuota::class, 'tipo_cuota_id');
    }
}
