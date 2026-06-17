<?php

namespace App\Services;

/**
 * Calcula el dígito verificador (DV) de un RUC panameño según el algoritmo
 * oficial de la DGI/ANIP (módulo 11, "ALGORITMO PARA EL CÁLCULO DEL DÍGITO
 * VERIFICADOR DE LA RUC Y RECIBO", https://www.anip.gob.pa/documentos/DV_RUC.pdf).
 *
 * El DV siempre es de 2 dígitos. Devuelve null si el RUC no tiene un formato
 * reconocible (en cuyo caso el llamador debe dejar el campo vacío).
 */
class RucDigitoVerificador
{
    /** Tabla de referencia cruzada para RUC jurídicos del régimen antiguo. */
    private const ARRVAL = [
        '00' => '00', '10' => '01', '11' => '02', '12' => '03', '13' => '04',
        '14' => '05', '15' => '06', '16' => '07', '17' => '08', '18' => '09',
        '19' => '01', '20' => '02', '21' => '03', '22' => '04', '23' => '07',
        '24' => '08', '25' => '09', '26' => '02', '27' => '03', '28' => '04',
        '29' => '05', '30' => '06', '31' => '07', '32' => '08', '33' => '09',
        '34' => '01', '35' => '02', '36' => '03', '37' => '04', '38' => '05',
        '39' => '06', '40' => '07', '41' => '08', '42' => '09', '43' => '01',
        '44' => '02', '45' => '03', '46' => '04', '47' => '05', '48' => '06',
        '49' => '07',
    ];

    /** Devuelve el DV de 2 dígitos (ej. "07"), o null si el RUC es inválido. */
    public static function calcular(?string $ruc): ?string
    {
        $ruc = trim((string) $ruc);
        if ($ruc === '') {
            return null;
        }

        $rs    = explode('-', $ruc);
        $nseg  = count($rs);

        if (($nseg === 4 && $rs[1] !== 'NT') || $nseg < 3 || $nseg > 5) {
            return null;
        }

        $sw    = false;
        $z     = static fn (int $n): string => str_repeat('0', max(0, $n));

        if ($ruc[0] === 'E') {
            $ructb = $z(4 - strlen($rs[1])).'0000005'.'00'.'50'.$z(3 - strlen($rs[1])).$rs[1].$z(5 - strlen($rs[2])).$rs[2];
        } elseif ($rs[1] === 'NT') {
            $ructb = $z(4 - strlen($rs[1])).'0000005'.str_repeat('00', max(0, 2 - strlen(substr($rs[0], 0, -2)))).substr($rs[0], 0, -2).'43'.$z(3 - strlen($rs[2])).$rs[2].$z(5 - strlen($rs[3])).$rs[3];
        } elseif (substr($rs[0], -2) === 'AV') {
            $ructb = $z(4 - strlen($rs[1])).'0000005'.str_repeat('00', max(0, 2 - strlen(substr($rs[0], 0, -2)))).substr($rs[0], 0, -2).'15'.$z(3 - strlen($rs[1])).$rs[1].$z(5 - strlen($rs[2])).$rs[2];
        } elseif (substr($rs[0], -2) === 'PI') {
            $ructb = $z(4 - strlen($rs[1])).'0000005'.str_repeat('00', max(0, 2 - strlen(substr($rs[0], 0, -2)))).substr($rs[0], 0, -2).'79'.$z(3 - strlen($rs[1])).$rs[1].$z(5 - strlen($rs[2])).$rs[2];
        } elseif ($rs[0] === 'PE') {
            $ructb = $z(4 - strlen($rs[1])).'0000005'.'00'.'75'.$z(3 - strlen($rs[1])).$rs[1].$z(5 - strlen($rs[2])).$rs[2];
        } elseif ($ruc[0] === 'N') {
            $ructb = $z(4 - strlen($rs[1])).'0000005'.'00'.'40'.$z(3 - strlen($rs[1])).$rs[1].$z(5 - strlen($rs[2])).$rs[2];
        } elseif (strlen($rs[0]) > 0 && strlen($rs[1]) === 4) {
            // Segmento intermedio de 4 dígitos.
            $ructb = $z(5 - strlen($rs[1])).'0000005'.$z(2 - strlen($rs[0])).$rs[0].'00'.$z(4 - strlen($rs[1])).$rs[1].$z(5 - strlen($rs[2])).$rs[2];
        } elseif (strlen($rs[0]) > 0 && strlen($rs[0]) <= 2) {
            // Persona natural (cédula): provincia de 1-2 caracteres.
            $ructb = $z(4 - strlen($rs[1])).'0000005'.$z(2 - strlen($rs[0])).$rs[0].'00'.$z(3 - strlen($rs[1])).$rs[1].$z(5 - strlen($rs[2])).$rs[2];
        } else {
            // RUC jurídico.
            $ructb = $z(10 - strlen($rs[0])).$rs[0].$z(4 - strlen($rs[1])).$rs[1].$z(6 - strlen($rs[2])).$rs[2];
            // sw = true si es RUC jurídico del régimen antiguo.
            $sw = $ructb[3] === '0' && $ructb[4] === '0' && $ructb[5] < '5';
        }

        // Rutina de referencia cruzada (solo RUC jurídico antiguo).
        if ($sw) {
            $par   = substr($ructb, 5, 2);
            $ructb = substr($ructb, 0, 5).(self::ARRVAL[$par] ?? $par).substr($ructb, 7);
        }

        $dv1 = self::digitDV($sw, $ructb);
        $dv2 = self::digitDV($sw, $ructb.chr(48 + $dv1));

        return chr(48 + $dv1).chr(48 + $dv2);
    }

    /** Una pasada del cálculo módulo 11. */
    private static function digitDV(bool $sw, string $ructb): int
    {
        $j     = 2;
        $nsuma = 0;

        for ($i = strlen($ructb) - 1; $i >= 0; $i--) {
            if ($sw && $j === 12) {
                $sw = false;
                $j -= 1;
            }
            $nsuma += $j * (ord($ructb[$i]) - 48);
            $j += 1;
        }

        $r = $nsuma % 11;

        return $r > 1 ? 11 - $r : 0;
    }
}
