<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Parámetro legal NACIONAL con vigencia (tasas CSS, Seguro Educativo...).
 * Global — no lleva compañía. Fuente única del motor de cálculo: las tasas
 * jamás se hardcodean en el código.
 */
class NomParametroLegal extends Model
{
    protected $table = 'nom_parametros_legales';

    public const CSS_EMPLEADO = 'CSS_EMPLEADO';

    public const CSS_PATRONO = 'CSS_PATRONO';

    public const SE_EMPLEADO = 'SE_EMPLEADO';

    public const SE_PATRONO = 'SE_PATRONO';

    protected $fillable = [
        'clave',
        'valor',
        'vigente_desde',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:6',
            'vigente_desde' => 'date',
        ];
    }

    /** Valor vigente de una clave a una fecha (la vigencia más reciente <= fecha). */
    public static function vigente(string $clave, Carbon $fecha): float
    {
        $fila = self::where('clave', $clave)
            ->where('vigente_desde', '<=', $fecha->toDateString())
            ->orderByDesc('vigente_desde')
            ->first();

        if (! $fila) {
            throw new \RuntimeException("No hay parámetro legal '$clave' vigente al {$fecha->toDateString()}. Configúralo en nom_parametros_legales.");
        }

        return (float) $fila->valor;
    }
}
