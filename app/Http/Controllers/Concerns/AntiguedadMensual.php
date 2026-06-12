<?php

namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;

/**
 * Cubetas de antigüedad nombradas por mes calendario, contando hacia atrás
 * desde la fecha de corte. Ejemplo (corte en junio): Junio · Mayo · Abril ·
 * "Marzo y anteriores". El mes del corte (m0) agrupa lo corriente/no vencido
 * y lo del propio mes; el último mes visible agrupa todo lo más antiguo.
 */
trait AntiguedadMensual
{
    /** Meses calendario visibles antes de la cubeta de arrastre. */
    private int $mesesVisibles = 3;

    private const MESES_ES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /**
     * @return array<string,string> claves m0..mN => etiqueta (nombre de mes)
     */
    protected function columnasMensuales(Carbon $corte): array
    {
        $columnas = [];

        for ($i = 0; $i < $this->mesesVisibles; $i++) {
            $mes = $corte->copy()->startOfMonth()->subMonths($i);
            $etiqueta = self::MESES_ES[$mes->month];
            // Añade el año si difiere del año del corte (para evitar ambigüedad).
            if ($mes->year !== $corte->year) {
                $etiqueta .= ' '.$mes->year;
            }
            $columnas['m'.$i] = $etiqueta;
        }

        $arrastre = $corte->copy()->startOfMonth()->subMonths($this->mesesVisibles);
        $columnas['m'.$this->mesesVisibles] = self::MESES_ES[$arrastre->month].' y anteriores';

        return $columnas;
    }

    /** Cubeta (m0..mN) según el mes calendario del vencimiento. */
    protected function cubetaMensual(Carbon $corte, Carbon $vence): string
    {
        $mesesAtras = ($corte->year * 12 + $corte->month) - ($vence->year * 12 + $vence->month);
        $idx = $mesesAtras <= 0 ? 0 : min($mesesAtras, $this->mesesVisibles);

        return 'm'.$idx;
    }
}
