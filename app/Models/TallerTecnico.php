<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallerTecnico extends Model
{
    protected $table = 'taller_tecnicos';

    public const TIPOS = [
        'interno'   => 'Interno',
        'externo'   => 'Externo',
        'proveedor' => 'Proveedor',
    ];

    protected $fillable = [
        'taller_id', 'contacto_id', 'usuario_id', 'codigo', 'nombre_publico',
        'tipo_tecnico', 'costo_hora', 'precio_hora', 'capacidad_horas_dia',
        'activo', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activo'               => 'boolean',
            'costo_hora'           => 'decimal:2',
            'precio_hora'          => 'decimal:2',
            'capacidad_horas_dia'  => 'decimal:2',
        ];
    }

    public function taller(): BelongsTo
    {
        return $this->belongsTo(TallerTaller::class, 'taller_id');
    }

    public function especialidades(): HasMany
    {
        return $this->hasMany(TallerTecnicoEspecialidad::class, 'tecnico_id');
    }
}
