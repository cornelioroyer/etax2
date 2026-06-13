<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerModelo extends Model
{
    protected $table = 'taller_modelos';

    protected $fillable = [
        'taller_id', 'marca_id', 'tipo_equipo_id', 'codigo', 'nombre',
        'anio_desde', 'anio_hasta', 'activo', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function taller(): BelongsTo
    {
        return $this->belongsTo(TallerTaller::class, 'taller_id');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(TallerMarca::class, 'marca_id');
    }

    public function tipoEquipo(): BelongsTo
    {
        return $this->belongsTo(TallerTipoEquipo::class, 'tipo_equipo_id');
    }
}
