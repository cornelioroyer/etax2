<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreProyecto extends Model
{
    protected $table = 'core_proyectos';

    const ESTADO_ACTIVO     = 'ACTIVO';
    const ESTADO_COMPLETADO = 'COMPLETADO';
    const ESTADO_SUSPENDIDO = 'SUSPENDIDO';

    protected $fillable = [
        'compania_id', 'codigo', 'nombre', 'fecha_inicio', 'fecha_fin',
        'estado', 'created_by', 'updated_by',
    ];

    protected $casts = ['fecha_inicio' => 'date', 'fecha_fin' => 'date'];
}
