<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerArea extends Model
{
    protected $table = 'taller_areas';

    public const TIPOS = [
        'recepcion'   => 'Recepción',
        'diagnostico' => 'Diagnóstico',
        'reparacion'  => 'Reparación',
        'pintura'     => 'Pintura',
        'lavado'      => 'Lavado',
        'entrega'     => 'Entrega',
        'bodega'      => 'Bodega',
        'calidad'     => 'Control de calidad',
        'servicio'    => 'Servicio al cliente',
    ];

    protected $fillable = [
        'taller_id', 'sucursal_id', 'codigo', 'nombre', 'tipo_area',
        'capacidad', 'activo', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function taller(): BelongsTo
    {
        return $this->belongsTo(TallerTaller::class, 'taller_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(TallerSucursal::class, 'sucursal_id');
    }
}
