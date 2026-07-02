<?php

namespace App\Services;

use App\Models\NomConcepto;
use App\Models\NomIsrTramo;
use App\Models\NomParametroLegal;

/**
 * Siembra el catálogo de nómina: conceptos default de una compañía (misma
 * numeración que el sistema planilla legacy) y los parámetros legales
 * nacionales (globales, una sola vez). Idempotente.
 */
class NomCatalogoDefault
{
    /**
     * Conceptos default por compañía. codigo => atributos.
     * Los porcentajes NO van aquí: los conceptos PORCENTAJE toman su tasa de
     * nom_parametros_legales por vigencia (clave en 'parametro').
     */
    private const CONCEPTOS = [
        ['codigo' => '03', 'descripcion' => 'Salario Regular', 'tipo' => NomConcepto::TIPO_INGRESO, 'calculo' => NomConcepto::CALCULO_SALARIO, 'orden' => 10],
        ['codigo' => '05', 'descripcion' => 'Horas Extras', 'tipo' => NomConcepto::TIPO_INGRESO, 'calculo' => NomConcepto::CALCULO_MANUAL, 'orden' => 20],
        ['codigo' => '07', 'descripcion' => 'Comisiones', 'tipo' => NomConcepto::TIPO_INGRESO, 'calculo' => NomConcepto::CALCULO_MANUAL, 'orden' => 30],
        ['codigo' => '09', 'descripcion' => 'Bonificación', 'tipo' => NomConcepto::TIPO_INGRESO, 'calculo' => NomConcepto::CALCULO_MANUAL, 'orden' => 40],
        ['codigo' => '15', 'descripcion' => 'Gastos de Representación', 'tipo' => NomConcepto::TIPO_INGRESO, 'calculo' => NomConcepto::CALCULO_MANUAL, 'orden' => 50],
        ['codigo' => '102', 'descripcion' => 'Seguro Social (S.S.)', 'tipo' => NomConcepto::TIPO_DEDUCCION, 'calculo' => NomConcepto::CALCULO_PORCENTAJE, 'orden' => 110],
        ['codigo' => '103', 'descripcion' => 'Seguro Educativo (S.E.)', 'tipo' => NomConcepto::TIPO_DEDUCCION, 'calculo' => NomConcepto::CALCULO_PORCENTAJE, 'orden' => 120],
        ['codigo' => '104', 'descripcion' => 'Impuesto Sobre la Renta (I.S.R.)', 'tipo' => NomConcepto::TIPO_DEDUCCION, 'calculo' => NomConcepto::CALCULO_ISR, 'orden' => 130],
        ['codigo' => '130', 'descripcion' => 'Descuento / Préstamo', 'tipo' => NomConcepto::TIPO_DEDUCCION, 'calculo' => NomConcepto::CALCULO_MANUAL, 'orden' => 140],
        ['codigo' => '902', 'descripcion' => 'S.S. Cuota Patronal', 'tipo' => NomConcepto::TIPO_PATRONAL, 'calculo' => NomConcepto::CALCULO_PORCENTAJE, 'orden' => 910],
        ['codigo' => '903', 'descripcion' => 'S.E. Cuota Patronal', 'tipo' => NomConcepto::TIPO_PATRONAL, 'calculo' => NomConcepto::CALCULO_PORCENTAJE, 'orden' => 920],
        ['codigo' => '904', 'descripcion' => 'Riesgo Profesional', 'tipo' => NomConcepto::TIPO_PATRONAL, 'calculo' => NomConcepto::CALCULO_PORCENTAJE, 'orden' => 930],
    ];

    /** Mapeo concepto PORCENTAJE => clave de parámetro legal que lo alimenta. */
    public const PARAMETRO_DE = [
        NomConcepto::COD_CSS => NomParametroLegal::CSS_EMPLEADO,
        NomConcepto::COD_SEGURO_EDUCATIVO => NomParametroLegal::SE_EMPLEADO,
        NomConcepto::COD_CSS_PATRONO => NomParametroLegal::CSS_PATRONO,
        NomConcepto::COD_SE_PATRONO => NomParametroLegal::SE_PATRONO,
        // 904 Riesgo Profesional toma su tasa de nom_configuracion (varía por compañía)
    ];

    /** Siembra los conceptos default de la compañía. Idempotente por código. */
    public static function aplicar(int $companiaId, ?string $usuario = null): int
    {
        $creados = 0;

        foreach (self::CONCEPTOS as $c) {
            $existe = NomConcepto::where('compania_id', $companiaId)
                ->where('codigo', $c['codigo'])
                ->exists();

            if ($existe) {
                continue;
            }

            NomConcepto::create([
                'compania_id' => $companiaId,
                'codigo' => $c['codigo'],
                'descripcion' => $c['descripcion'],
                'tipo' => $c['tipo'],
                'calculo' => $c['calculo'],
                // Deducciones y patronales no integran bases gravables
                'gravable_css' => $c['tipo'] === NomConcepto::TIPO_INGRESO,
                'gravable_isr' => $c['tipo'] === NomConcepto::TIPO_INGRESO,
                'acumula_xiii' => $c['tipo'] === NomConcepto::TIPO_INGRESO,
                'acumula_vacaciones' => $c['tipo'] === NomConcepto::TIPO_INGRESO,
                'imprime_en_recibo' => $c['tipo'] !== NomConcepto::TIPO_PATRONAL,
                'orden_impresion' => $c['orden'],
                'de_sistema' => true,
                'activo' => true,
                'created_by' => $usuario,
            ]);

            $creados++;
        }

        return $creados;
    }

    /**
     * Siembra los parámetros legales nacionales y la tabla de ISR si no
     * existen (globales, una sola vez para toda la plataforma). Los valores
     * son editables desde BD/UI cuando la ley cambie — por eso viven en
     * tablas con vigencia y no en el código.
     */
    public static function aplicarParametrosLegales(): void
    {
        $parametros = [
            [NomParametroLegal::CSS_EMPLEADO, 9.75, 'Cuota CSS del empleado (%)'],
            [NomParametroLegal::CSS_PATRONO, 12.25, 'Cuota CSS del empleador (%)'],
            [NomParametroLegal::SE_EMPLEADO, 1.25, 'Seguro Educativo del empleado (%)'],
            [NomParametroLegal::SE_PATRONO, 1.50, 'Seguro Educativo del empleador (%)'],
        ];

        foreach ($parametros as [$clave, $valor, $descripcion]) {
            NomParametroLegal::firstOrCreate(
                ['clave' => $clave, 'vigente_desde' => '2020-01-01'],
                ['valor' => $valor, 'descripcion' => $descripcion],
            );
        }

        // Tabla ISR asalariados (Art. 700 CF, vigente desde 2010)
        if (! NomIsrTramo::query()->exists()) {
            NomIsrTramo::create(['vigente_desde' => '2010-01-01', 'desde' => 0, 'hasta' => 11000, 'tasa' => 0, 'cuota_fija' => 0]);
            NomIsrTramo::create(['vigente_desde' => '2010-01-01', 'desde' => 11000, 'hasta' => 50000, 'tasa' => 15, 'cuota_fija' => 0]);
            NomIsrTramo::create(['vigente_desde' => '2010-01-01', 'desde' => 50000, 'hasta' => null, 'tasa' => 25, 'cuota_fija' => 5850]);
        }
    }
}
