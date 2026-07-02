<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Tramo de la tabla del ISR de asalariados de Panamá, con vigencia.
 * (Art. 700 CF: 0–11,000 exento; 11,000–50,000 = 15% del excedente;
 * >50,000 = 5,850 + 25% del excedente. Parametrizado, no hardcodeado.)
 */
class NomIsrTramo extends Model
{
    protected $table = 'nom_isr_tramos';

    protected $fillable = [
        'vigente_desde',
        'desde',
        'hasta',
        'tasa',
        'cuota_fija',
    ];

    protected function casts(): array
    {
        return [
            'vigente_desde' => 'date',
            'desde' => 'decimal:2',
            'hasta' => 'decimal:2',
            'tasa' => 'decimal:4',
            'cuota_fija' => 'decimal:2',
        ];
    }

    /** Tabla vigente a una fecha, ordenada por tramo. */
    public static function tablaVigente(Carbon $fecha): Collection
    {
        $vigencia = self::where('vigente_desde', '<=', $fecha->toDateString())
            ->max('vigente_desde');

        if (! $vigencia) {
            throw new \RuntimeException("No hay tabla de ISR vigente al {$fecha->toDateString()}. Configúrala en nom_isr_tramos.");
        }

        return self::where('vigente_desde', $vigencia)->orderBy('desde')->get();
    }

    /** Impuesto anual para una renta anual gravable según la tabla vigente. */
    public static function impuestoAnual(float $rentaAnual, Carbon $fecha): float
    {
        if ($rentaAnual <= 0) {
            return 0.0;
        }

        $tramo = self::tablaVigente($fecha)
            ->first(fn ($t) => $rentaAnual >= (float) $t->desde
                && ($t->hasta === null || $rentaAnual <= (float) $t->hasta));

        if (! $tramo) {
            return 0.0;
        }

        return round((float) $tramo->cuota_fija + ($rentaAnual - (float) $tramo->desde) * (float) $tramo->tasa / 100, 2);
    }
}
