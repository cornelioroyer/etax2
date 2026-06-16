<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrhUnidad extends Model
{
    protected $table = 'prh_unidades';

    const TIPOS = ['APARTAMENTO', 'OFICINA', 'LOCAL', 'PARQUEO', 'BODEGA', 'OTRO'];

    protected $fillable = [
        'edificio_id', 'codigo', 'numero', 'tipo', 'piso', 'area_m2', 'coeficiente',
        'propietario_id', 'activo', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activo'      => 'boolean',
            'area_m2'     => 'decimal:2',
            'coeficiente' => 'decimal:6',
        ];
    }

    public function edificio(): BelongsTo
    {
        return $this->belongsTo(PrhEdificio::class, 'edificio_id');
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(PrhPropietario::class, 'propietario_id');
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(PrhCuota::class, 'unidad_id');
    }
}
