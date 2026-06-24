<?php

namespace Tests\Feature;

use App\Models\TaxImpuesto;
use App\Services\FelDocumentoBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test-guardia de D-06 (ver docs/DECISIONES.md).
 *
 * Garantiza que las tres representaciones de las tasas ITBMS no diverjan:
 *   1. La fuente canónica TaxImpuesto::PORCENTAJES_ITBMS.
 *   2. Las filas globales de la tabla tax_impuestos.
 *   3. Los factores del catálogo DGI usados por FEL (FelDocumentoBuilder).
 *
 * Si alguien cambia una tasa en un solo lugar, este test falla.
 */
class ItbmsConsistenciaTest extends TestCase
{
    use RefreshDatabase;

    /** Los controladores CxC/CxP deben usar exactamente la fuente canónica. */
    public function test_controladores_cxc_cxp_usan_la_fuente_canonica(): void
    {
        $canon = TaxImpuesto::PORCENTAJES_ITBMS;

        $this->assertSame($canon, \App\Http\Controllers\Admin\CxcFacturaController::TASAS_ITBMS);
        $this->assertSame($canon, \App\Http\Controllers\Admin\CxcNotaController::TASAS_ITBMS);
        $this->assertSame($canon, \App\Http\Controllers\Admin\CxpNotaController::TASAS_ITBMS);
    }

    /** Las filas globales de tax_impuestos deben cubrir las tasas canónicas. */
    public function test_tabla_tax_impuestos_coincide_con_la_fuente_canonica(): void
    {
        $porcentajesEnBd = TaxImpuesto::itbmsGlobales()
            ->pluck('porcentaje')
            ->map(fn ($p) => (int) round((float) $p))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            TaxImpuesto::PORCENTAJES_ITBMS,
            $porcentajesEnBd,
            'Las tasas ITBMS globales en tax_impuestos no coinciden con TaxImpuesto::PORCENTAJES_ITBMS.'
        );
    }

    /** Los factores FEL deben derivarse de los porcentajes canónicos. */
    public function test_factores_fel_coinciden_con_la_fuente_canonica(): void
    {
        foreach (FelDocumentoBuilder::TASAS_ITBMS as $codigoDgi => $factor) {
            $this->assertEqualsWithDelta(
                TaxImpuesto::factorItbmsPorCodigoDgi((string) $codigoDgi),
                $factor,
                0.0001,
                "El factor FEL del código DGI {$codigoDgi} no coincide con la fuente canónica."
            );
        }

        // Y a la inversa: cada porcentaje canónico tiene su código DGI en FEL.
        foreach (TaxImpuesto::DGI_CODIGO_POR_PORCENTAJE as $porcentaje => $codigoDgi) {
            $this->assertArrayHasKey(
                $codigoDgi,
                FelDocumentoBuilder::TASAS_ITBMS,
                "Falta el código DGI {$codigoDgi} ({$porcentaje}%) en FelDocumentoBuilder::TASAS_ITBMS."
            );
        }
    }
}
