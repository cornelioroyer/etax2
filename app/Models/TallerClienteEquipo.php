<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerClienteEquipo extends Model
{
    protected $table = 'taller_clientes_equipos';

    protected $fillable = [
        'taller_id', 'cliente_id', 'equipo_id', 'relacion',
        'principal', 'fecha_inicio', 'fecha_fin', 'activo',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activo'       => 'boolean',
            'principal'    => 'boolean',
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
        ];
    }

    public const RELACIONES = [
        'propietario'  => 'Propietario',
        'usuario'      => 'Usuario',
        'responsable'  => 'Responsable',
        'aseguradora'  => 'Aseguradora',
    ];

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(TallerEquipo::class, 'equipo_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Contacto::class, 'cliente_id');
    }
}
