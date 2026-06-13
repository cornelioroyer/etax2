<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TallerTaller extends Model
{
    protected $table = 'taller_talleres';

    public const TIPOS = [
        'general'          => 'General',
        'autos'            => 'Autos',
        'motos'            => 'Motos',
        'electrodomesticos'=> 'Electrodomésticos',
        'aires'            => 'Aires acondicionados',
        'electronica'      => 'Electrónica',
        'industrial'       => 'Industrial',
        'mixto'            => 'Mixto',
    ];

    protected $fillable = [
        'compania_id', 'codigo', 'nombre', 'tipo_taller',
        'direccion', 'telefono', 'email', 'responsable_id',
        'activo', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function sucursales(): HasMany
    {
        return $this->hasMany(TallerSucursal::class, 'taller_id');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(TallerArea::class, 'taller_id');
    }

    public function tiposEquipo(): HasMany
    {
        return $this->hasMany(TallerTipoEquipo::class, 'taller_id');
    }

    public function marcas(): HasMany
    {
        return $this->hasMany(TallerMarca::class, 'taller_id');
    }

    public function modelos(): HasMany
    {
        return $this->hasMany(TallerModelo::class, 'taller_id');
    }

    public function especialidades(): HasMany
    {
        return $this->hasMany(TallerEspecialidad::class, 'taller_id');
    }

    public function sintomas(): HasMany
    {
        return $this->hasMany(TallerSintoma::class, 'taller_id');
    }

    public function serviciosEstandar(): HasMany
    {
        return $this->hasMany(TallerServicioEstandar::class, 'taller_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TallerChecklist::class, 'taller_id');
    }

    public function tecnicos(): HasMany
    {
        return $this->hasMany(TallerTecnico::class, 'taller_id');
    }

    public function configuracion(): HasOne
    {
        return $this->hasOne(TallerConfiguracion::class, 'taller_id');
    }
}
