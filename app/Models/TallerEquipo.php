<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TallerEquipo extends Model
{
    protected $table = 'taller_equipos';

    protected $fillable = [
        'taller_id', 'tipo_equipo_id', 'marca_id', 'modelo_id',
        'codigo', 'nombre', 'numero_serie', 'placa', 'vin',
        'anio', 'color', 'descripcion', 'especificaciones',
        'activo', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activo'          => 'boolean',
            'especificaciones' => 'array',
        ];
    }

    public function taller(): BelongsTo
    {
        return $this->belongsTo(TallerTaller::class, 'taller_id');
    }

    public function tipoEquipo(): BelongsTo
    {
        return $this->belongsTo(TallerTipoEquipo::class, 'tipo_equipo_id');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(TallerMarca::class, 'marca_id');
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(TallerModelo::class, 'modelo_id');
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(TallerClienteEquipo::class, 'equipo_id');
    }

    public function clientePrincipal(): HasOne
    {
        return $this->hasOne(TallerClienteEquipo::class, 'equipo_id')
            ->where('principal', true)
            ->where('activo', true);
    }

    public function mediciones(): HasMany
    {
        return $this->hasMany(TallerEquipoMedicion::class, 'equipo_id')->latest('fecha');
    }
}
