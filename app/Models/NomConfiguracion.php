<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración de nómina por compañía. Todo lo parametrizable por compañía
 * vive AQUÍ — jamás en ramas de código por id (lección del sistema legacy).
 */
class NomConfiguracion extends Model
{
    protected $table = 'nom_configuracion';

    protected $fillable = [
        'compania_id',
        'riesgo_profesional',
        'horas_semanales_default',
        'tipo_planilla_default',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'riesgo_profesional' => 'decimal:4',
            'horas_semanales_default' => 'decimal:2',
        ];
    }

    public static function deCompania(int $companiaId): self
    {
        // Valores explícitos: firstOrCreate devuelve el modelo con SOLO los
        // atributos dados (los default de columna viven en la BD y no se
        // reflejan en el objeto recién creado).
        return self::firstOrCreate(['compania_id' => $companiaId], [
            'riesgo_profesional' => 0.98,
            'horas_semanales_default' => 48,
            'tipo_planilla_default' => 'QUINCENAL',
        ]);
    }
}
