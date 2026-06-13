<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Formateo centralizado de fechas para la interfaz.
 *
 * Los timestamps se almacenan en UTC; al mostrarlos se convierten a la zona
 * horaria de Panamá (GMT-5, sin horario de verano). Las fechas puras (sin hora)
 * no se convierten para no correrse de día.
 */
class Fechas
{
    public const ZONA = 'America/Panama';

    /** Fecha con hora, convertida a GMT-5: 12/06/2026 16:20 */
    public static function hora(?CarbonInterface $valor, string $vacio = '—'): string
    {
        return $valor?->timezone(self::ZONA)->format('d/m/Y H:i') ?? $vacio;
    }

    /** Solo fecha (sin conversión de zona): 12/06/2026 */
    public static function fecha(?CarbonInterface $valor, string $vacio = '—'): string
    {
        return $valor?->format('d/m/Y') ?? $vacio;
    }
}
