<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallerTipoEquipo extends Model
{
    protected $table = 'taller_tipos_equipo';

    public const CATEGORIAS = [
        'vehiculo'           => 'Vehículo',
        'electrodomestico'   => 'Electrodoméstico',
        'aire_acondicionado' => 'Aire acondicionado',
        'electronico'        => 'Electrónico',
        'maquinaria'         => 'Maquinaria',
        'otro'               => 'Otro',
        'general'            => 'General',
    ];

    protected $fillable = [
        'taller_id', 'codigo', 'nombre', 'categoria',
        'requiere_placa', 'requiere_vin', 'requiere_serie', 'requiere_medidor',
        'unidad_medidor', 'activo', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activo'           => 'boolean',
            'requiere_placa'   => 'boolean',
            'requiere_vin'     => 'boolean',
            'requiere_serie'   => 'boolean',
            'requiere_medidor' => 'boolean',
        ];
    }

    public function taller(): BelongsTo
    {
        return $this->belongsTo(TallerTaller::class, 'taller_id');
    }

    public function modelos(): HasMany
    {
        return $this->hasMany(TallerModelo::class, 'tipo_equipo_id');
    }

    public function sintomas(): HasMany
    {
        return $this->hasMany(TallerSintoma::class, 'tipo_equipo_id');
    }
}
