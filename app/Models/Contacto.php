<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contacto extends Model
{
    protected $table = 'contact_contactos';

    protected $fillable = [
        'compania_id',
        'codigo',
        'nombre',
        'razon_social',
        'tipo_persona',
        'identificacion',
        'dv',
        'email',
        'telefono',
        'direccion',
        'pais',
        'provincia',
        'distrito',
        'activo',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function tipos(): BelongsToMany
    {
        return $this->belongsToMany(TipoContacto::class, 'contact_contactos_tipos', 'contacto_id', 'tipo_id');
    }

    public function esTipo(string $codigo): bool
    {
        return $this->tipos->contains('codigo', $codigo);
    }
}
