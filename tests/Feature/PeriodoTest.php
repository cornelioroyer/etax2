<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\CierreContable;
use App\Models\Compania;
use App\Models\PeriodoContable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PeriodoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function cerrar(int $anio = 2026, int $mes = 3, array $extra = [])
    {
        return $this->actuar()->post(route('admin.periodos.cerrar'), array_merge([
            'anio' => $anio,
            'mes' => $mes,
            'observacion' => 'Cierre mensual',
        ], $extra));
    }

    private function periodo(int $anio = 2026, int $mes = 3): PeriodoContable
    {
        return PeriodoContable::where('compania_id', $this->compania->id)
            ->where('anio', $anio)->where('mes', $mes)->firstOrFail();
    }

    private function asientoBorrador(string $fecha): Asiento
    {
        return Asiento::create([
            'compania_id' => $this->compania->id,
            'numero' => Asiento::siguienteNumero($this->compania->id),
            'fecha' => $fecha,
            'estado' => Asiento::ESTADO_BORRADOR,
            'total_debito' => 100,
            'total_credito' => 100,
        ]);
    }

    public function test_cerrar_periodo_lo_marca_cerrado_y_registra_cierre(): void
    {
        $this->cerrar(2026, 3)->assertSessionHasNoErrors();

        $periodo = $this->periodo(2026, 3);
        $this->assertSame('CERRADO', $periodo->estado);
        $this->assertNotNull($periodo->fecha_cierre);

        $cierre = CierreContable::where('periodo_id', $periodo->id)->firstOrFail();
        $this->assertSame('CERRADO', $cierre->estado);
        $this->assertSame('Cierre mensual', $cierre->observacion);
    }

    public function test_cerrar_periodo_ya_cerrado_falla(): void
    {
        $this->cerrar(2026, 3)->assertSessionHasNoErrors();

        $this->cerrar(2026, 3)->assertSessionHasErrors('periodo');
    }

    public function test_cerrar_con_borradores_sin_forzar_es_rechazado(): void
    {
        $this->asientoBorrador('2026-03-15');

        $this->cerrar(2026, 3)->assertSessionHasErrors('periodo');

        $this->assertSame('ABIERTO', $this->periodo(2026, 3)->estado);
    }

    public function test_cerrar_con_forzar_cierra_pese_a_borradores(): void
    {
        $this->asientoBorrador('2026-03-15');

        $this->cerrar(2026, 3, ['forzar' => 1])->assertSessionHasNoErrors();

        $this->assertSame('CERRADO', $this->periodo(2026, 3)->estado);
    }

    public function test_reabrir_requiere_motivo(): void
    {
        $this->cerrar(2026, 3)->assertSessionHasNoErrors();
        $periodo = $this->periodo(2026, 3);

        $this->actuar()->post(route('admin.periodos.reabrir', $periodo), ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->assertSame('CERRADO', $periodo->fresh()->estado);
    }

    public function test_reabrir_periodo_cerrado_registra_auditoria(): void
    {
        $this->cerrar(2026, 3)->assertSessionHasNoErrors();
        $periodo = $this->periodo(2026, 3);

        $this->actuar()->post(route('admin.periodos.reabrir', $periodo), [
            'motivo' => 'Ajuste contable solicitado por gerencia',
        ])->assertSessionHasNoErrors();

        $periodo->refresh();
        $this->assertSame('ABIERTO', $periodo->estado);
        $this->assertNull($periodo->fecha_cierre);

        $this->assertSame('REABIERTO', CierreContable::where('periodo_id', $periodo->id)->value('estado'));

        $this->assertSame(1, DB::table('audit_reaperturas')->where('periodo_id', $periodo->id)->count());
        $this->assertSame('Ajuste contable solicitado por gerencia',
            DB::table('audit_reaperturas')->where('periodo_id', $periodo->id)->value('motivo'));
    }

    public function test_reabrir_periodo_abierto_falla(): void
    {
        $periodo = PeriodoContable::paraFecha($this->compania->id, \Carbon\Carbon::create(2026, 3, 1), $this->admin->email);

        $this->actuar()->post(route('admin.periodos.reabrir', $periodo), [
            'motivo' => 'Motivo cualquiera',
        ])->assertSessionHasErrors('periodo');
    }

    public function test_no_se_puede_postear_en_periodo_cerrado(): void
    {
        // Cerrar marzo 2026 y luego intentar registrar un gasto en esa fecha.
        $this->cerrar(2026, 3)->assertSessionHasNoErrors();

        $gasto = \App\Models\CuentaContable::create([
            'compania_id' => $this->compania->id, 'codigo' => '50101', 'nombre' => 'Gasto',
            'nivel' => 3, 'naturaleza' => 'DEBITO', 'permite_movimiento' => true, 'conciliable' => false, 'activa' => true,
        ]);
        $banco = \App\Models\CuentaContable::create([
            'compania_id' => $this->compania->id, 'codigo' => '11102', 'nombre' => 'Banco',
            'nivel' => 3, 'naturaleza' => 'DEBITO', 'permite_movimiento' => true, 'conciliable' => false, 'activa' => true,
        ]);

        $this->actuar()->post(route('admin.compras.gastos.store'), [
            'fecha' => '2026-03-15',
            'descripcion' => 'Gasto en periodo cerrado',
            'cuenta_gasto_id' => $gasto->id,
            'cuenta_pago_id' => $banco->id,
            'monto' => 50,
        ])->assertSessionHasErrors('fecha');

        $this->assertSame(0, Asiento::where('origen_modulo', 'GASTO')->count());
    }
}
