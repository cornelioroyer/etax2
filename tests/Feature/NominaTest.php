<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\NomConcepto;
use App\Models\NomEmpleado;
use App\Models\NomNovedad;
use App\Models\NomPeriodo;
use App\Models\NomPlanilla;
use App\Models\User;
use App\Services\NomCatalogoDefault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NominaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA NOMINA', 'activa' => true]);

        $crear = fn (string $codigo, string $nombre, string $naturaleza) => CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'nivel' => 3,
            'naturaleza' => $naturaleza,
            'permite_movimiento' => true,
            'activa' => true,
        ]);

        $gastoSalarios = $crear('60201', 'Gasto de Salarios', 'DEBITO');
        $gastoPatronal = $crear('60202', 'Prestaciones Patronales', 'DEBITO');
        $salariosPagar = $crear('20111', 'Salarios por Pagar', 'CREDITO');
        $cssPagar = $crear('20112', 'CSS por Pagar', 'CREDITO');
        $isrRetenido = $crear('20113', 'ISR Retenido', 'CREDITO');

        foreach ([
            'GASTO_SALARIOS' => $gastoSalarios,
            'GASTO_CUOTA_PATRONAL' => $gastoPatronal,
            'SALARIOS_POR_PAGAR' => $salariosPagar,
            'CSS_POR_PAGAR' => $cssPagar,
            'ISR_RETENIDO' => $isrRetenido,
        ] as $clave => $cuenta) {
            CuentaDefault::create([
                'compania_id' => $this->compania->id,
                'clave' => $clave,
                'cuenta_id' => $cuenta->id,
            ]);
        }

        NomCatalogoDefault::aplicarParametrosLegales();
        NomCatalogoDefault::aplicar($this->compania->id, $this->admin->email);
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function crearPeriodoQuincenal(): NomPeriodo
    {
        return NomPeriodo::create([
            'compania_id' => $this->compania->id,
            'tipo_planilla' => 'QUINCENAL',
            'anio' => 2026,
            'numero' => 13,
            'desde' => '2026-07-01',
            'hasta' => '2026-07-15',
            'fecha_pago' => '2026-07-15',
            'estado' => NomPeriodo::ESTADO_ABIERTO,
        ]);
    }

    private function crearEmpleadoFijo(float $salarioMensual = 1200): NomEmpleado
    {
        return NomEmpleado::create([
            'compania_id' => $this->compania->id,
            'codigo' => 'E001',
            'nombre' => 'Ana',
            'apellido' => 'Prueba',
            'cedula' => '8-100-100',
            'fecha_inicio' => '2025-01-01',
            'tipo_salario' => NomEmpleado::TIPO_SALARIO_FIJO,
            'salario_mensual' => $salarioMensual,
            'tipo_planilla' => 'QUINCENAL',
            'forma_pago' => 'TRANSFERENCIA',
            'status' => NomEmpleado::STATUS_ACTIVO,
        ]);
    }

    public function test_catalogo_default_es_idempotente(): void
    {
        $antes = NomConcepto::where('compania_id', $this->compania->id)->count();

        NomCatalogoDefault::aplicar($this->compania->id);

        $this->assertSame($antes, NomConcepto::where('compania_id', $this->compania->id)->count());
        $this->assertTrue(NomConcepto::where('compania_id', $this->compania->id)->where('codigo', '03')->exists());
        $this->assertTrue(NomConcepto::where('compania_id', $this->compania->id)->where('codigo', '102')->exists());
    }

    public function test_planilla_quincenal_calcula_css_se_isr(): void
    {
        $periodo = $this->crearPeriodoQuincenal();
        $this->crearEmpleadoFijo(1200); // quincena = 600

        $this->actuar()->post(route('admin.nomina.planillas.store'), [
            'periodo_id' => $periodo->id,
            'fecha' => '2026-07-15',
        ])->assertSessionHasNoErrors();

        $planilla = NomPlanilla::firstOrFail();
        $this->assertSame(NomPlanilla::ESTADO_PROCESADA, $planilla->estado);

        $montoDe = fn (string $codigo) => (float) $planilla->movimientos()
            ->whereHas('concepto', fn ($q) => $q->where('codigo', $codigo))
            ->sum('monto');

        $this->assertEqualsWithDelta(600.00, $montoDe('03'), 0.001);   // salario quincenal
        $this->assertEqualsWithDelta(58.50, $montoDe('102'), 0.001);   // CSS 9.75%
        $this->assertEqualsWithDelta(7.50, $montoDe('103'), 0.001);    // SE 1.25%
        // ISR: 600 x 24 = 14,400 anual -> (14400-11000) x 15% = 510 / 24 = 21.25
        $this->assertEqualsWithDelta(21.25, $montoDe('104'), 0.001);
        $this->assertEqualsWithDelta(73.50, $montoDe('902'), 0.001);   // CSS patrono 12.25%
        $this->assertEqualsWithDelta(9.00, $montoDe('903'), 0.001);    // SE patrono 1.50%
        $this->assertEqualsWithDelta(5.88, $montoDe('904'), 0.001);    // riesgo prof. 0.98%

        $this->assertEqualsWithDelta(600.00, (float) $planilla->total_ingresos, 0.001);
        $this->assertEqualsWithDelta(87.25, (float) $planilla->total_deducciones, 0.001);
        $this->assertEqualsWithDelta(512.75, (float) $planilla->total_neto, 0.001);
        $this->assertEqualsWithDelta(88.38, (float) $planilla->total_patronal, 0.001);
    }

    public function test_contabilizar_postea_asiento_cuadrado(): void
    {
        $periodo = $this->crearPeriodoQuincenal();
        $this->crearEmpleadoFijo(1200);

        $this->actuar()->post(route('admin.nomina.planillas.store'), [
            'periodo_id' => $periodo->id,
            'fecha' => '2026-07-15',
        ]);

        $planilla = NomPlanilla::firstOrFail();

        $this->actuar()->post(route('admin.nomina.planillas.contabilizar', $planilla))
            ->assertSessionHasNoErrors();

        $planilla->refresh();
        $this->assertSame(NomPlanilla::ESTADO_CONTABILIZADA, $planilla->estado);
        $this->assertNotNull($planilla->asiento_id);

        $asiento = Asiento::findOrFail($planilla->asiento_id);
        $this->assertSame('POSTEADO', $asiento->estado);
        // Débitos = gasto salarios 600 + gasto patronal 88.38 = 688.38
        $this->assertEqualsWithDelta(688.38, (float) $asiento->total_debito, 0.001);
        $this->assertEqualsWithDelta((float) $asiento->total_debito, (float) $asiento->total_credito, 0.001);
    }

    public function test_anular_reversa_el_asiento(): void
    {
        $periodo = $this->crearPeriodoQuincenal();
        $this->crearEmpleadoFijo(1200);

        $this->actuar()->post(route('admin.nomina.planillas.store'), [
            'periodo_id' => $periodo->id,
            'fecha' => '2026-07-15',
        ]);

        $planilla = NomPlanilla::firstOrFail();
        $this->actuar()->post(route('admin.nomina.planillas.contabilizar', $planilla));
        $this->actuar()->post(route('admin.nomina.planillas.anular', $planilla->refresh()))
            ->assertSessionHasNoErrors();

        $planilla->refresh();
        $this->assertSame(NomPlanilla::ESTADO_ANULADA, $planilla->estado);
        $this->assertSame('ANULADO', Asiento::findOrFail($planilla->asiento_id)->estado);
    }

    public function test_empleado_por_hora_usa_novedad_de_horas(): void
    {
        $periodo = $this->crearPeriodoQuincenal();

        $empleado = NomEmpleado::create([
            'compania_id' => $this->compania->id,
            'codigo' => 'E002',
            'nombre' => 'Luis',
            'apellido' => 'PorHora',
            'fecha_inicio' => '2025-01-01',
            'tipo_salario' => NomEmpleado::TIPO_SALARIO_POR_HORA,
            'tasa_hora' => 5,
            'tipo_planilla' => 'QUINCENAL',
            'forma_pago' => 'EFECTIVO',
            'status' => NomEmpleado::STATUS_ACTIVO,
        ]);

        $concepto03 = NomConcepto::where('compania_id', $this->compania->id)->where('codigo', '03')->firstOrFail();

        NomNovedad::create([
            'compania_id' => $this->compania->id,
            'empleado_id' => $empleado->id,
            'concepto_id' => $concepto03->id,
            'tipo_registro' => NomNovedad::TIPO_VARIABLE,
            'periodo_id' => $periodo->id,
            'cantidad' => 80,
            'monto' => 0,
            'activo' => true,
        ]);

        $this->actuar()->post(route('admin.nomina.planillas.store'), [
            'periodo_id' => $periodo->id,
            'fecha' => '2026-07-15',
        ])->assertSessionHasNoErrors();

        $planilla = NomPlanilla::firstOrFail();
        // 80 h x B/.5 = 400
        $this->assertEqualsWithDelta(400.00, (float) $planilla->total_ingresos, 0.001);
    }

    public function test_no_permite_dos_planillas_vigentes_del_mismo_periodo(): void
    {
        $periodo = $this->crearPeriodoQuincenal();
        $this->crearEmpleadoFijo(1200);

        $this->actuar()->post(route('admin.nomina.planillas.store'), [
            'periodo_id' => $periodo->id,
            'fecha' => '2026-07-15',
        ]);

        $this->actuar()->post(route('admin.nomina.planillas.store'), [
            'periodo_id' => $periodo->id,
            'fecha' => '2026-07-15',
        ])->assertSessionHasErrors('periodo_id');

        $this->assertSame(1, NomPlanilla::count());
    }

    public function test_usuario_sin_permiso_no_ve_nomina(): void
    {
        $comun = User::factory()->create(['is_admin' => false]);

        $this->actingAs($comun)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->get(route('admin.nomina.planillas.index'))
            ->assertForbidden();
    }
}
