<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Compania extends Model
{
    protected $table = 'core_companias';

    protected $fillable = [
        'nombre',
        'razon_social',
        'ruc',
        'dv',
        'firma_cartas',
        'direccion',
        'telefono',
        'telefono2',
        'email',
        'cargo',
        'mensaje',
        'correlativo_ss',
        'fecha_de_apertura',
        'fecha_de_expiracion',
        'activa',
        'no_patronal',
        'act_economica',
        'cedula',
        'licencia',
        'repre_legal',
        'zonas_id',
        'plan_id',
        'tipo_de_entidad',
        'constitucion',
        'logo_url',
        'sello_url',
        'token',
        'cliente_id',
        'nit',
        'cedula_repre_legal',
        'municipio',
        'clave_municipio',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_de_apertura' => 'date',
            'fecha_de_expiracion' => 'date',
            'activa' => 'boolean',
        ];
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'zonas_id');
    }
}
