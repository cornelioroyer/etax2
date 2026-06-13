<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\CajaVale;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CajaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private CuentaContable $efectivo;

    private CuentaContable $gasto;

    private CuentaContable $banco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);

        $crear = fn (string $codigo, string $nombre, string $naturaleza) => CuentaContable::create([
            'compania_id' => $this->compania->id, 'codigo' => $codigo, 'nombre' => $nombre,
            'nivel' => 3, 'naturaleza' => $naturaleza, 'permite_movimiento' => true, 'conciliable' => false, 'activa' => true,
        ]);

        $this->efectivo = $crear('11101', 'Caja Menuda', 'DEBITO');
        $this->gasto = $crear('60101', 'Gastos Varios', 'DEBITO');
        $this->banco = $crear('11102', 'Banco General', 'DEBITO');
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function crearCaja(bool $conCuenta = true): Caja
    {
        return Caja::create([
            'compania_id' => $this->compania->id,
            'codigo' => 'CAJA01',
            'nombre' => 'Caja principal',
            'cuenta_contable_id' => $conCuenta ? $this->efectivo->id : null,
            'activa' => true,
        ]);
    }

    private function reembolsar(Caja $caja, float $monto): void
    {
        $this->actuar()->post(route('admin.caja.cajas.reembolsos', $caja), [
            'fecha' => '2026-06-13', 'monto' => $monto, 'cuenta_banco_id' => $this->banco->id,
        ])->assertSessionHasNoErrors();
    }

    private function movimiento(Caja $caja, string $tipo, float $monto): \Illuminate\Testing\TestResponse
    {
        return $this->actuar()->post(route('admin.caja.cajas.movimientos', $caja), [
            'tipo_movimiento' => $tipo, 'fecha' => '2026-06-13', 'monto' => $monto,
            'beneficiario' => 'Juan', 'descripcion' => 'Prueba', 'cuenta_contable_id' => $this->gasto->id,
        ]);
    }

    public function test_listado_se_muestra(): void
    {
        $this->actuar()->get(route('admin.caja.cajas.index'))->assertOk()->assertSee('Caja menuda');
    }

    public function test_crear_caja(): void
    {
        $this->actuar()->post(route('admin.caja.cajas.store'), [
            'codigo' => 'CHICA', 'nombre' => 'Caja chica', 'cuenta_contable_id' => $this->efectivo->id,
        ])->assertSessionHasNoErrors();

        $caja = Caja::where('codigo', 'CHICA')->firstOrFail();
        $this->assertSame('Caja chica', $caja->nombre);
        $this->assertTrue((bool) $caja->activa);
    }

    public function test_codigo_no_se_repite(): void
    {
        $this->crearCaja();
        $this->actuar()->post(route('admin.caja.cajas.store'), ['codigo' => 'CAJA01', 'nombre' => 'Otra'])
            ->assertSessionHasErrors('codigo');
        $this->assertSame(1, Caja::count());
    }

    public function test_egreso_postea_asiento_y_baja_saldo(): void
    {
        $caja = $this->crearCaja();
        $this->reembolsar($caja, 100);

        $this->movimiento($caja, 'EGRESO', 30)->assertSessionHasNoErrors();

        $mov = CajaMovimiento::where('tipo_movimiento', 'EGRESO')->firstOrFail();
        $asiento = $mov->asiento;
        $this->assertNotNull($asiento);
        $this->assertSame('POSTEADO', $asiento->estado);
        $this->assertSame('CAJA', $asiento->origen_modulo);
        // D gasto 30 / C caja-efectivo 30
        $this->assertSame($this->gasto->id, $asiento->detalle[0]->cuenta_id);
        $this->assertSame('30.00', (string) $asiento->detalle[0]->debito);
        $this->assertSame($this->efectivo->id, $asiento->detalle[1]->cuenta_id);
        $this->assertSame('30.00', (string) $asiento->detalle[1]->credito);

        $this->assertSame(70.0, $caja->fresh()->saldoSistema());
    }

    public function test_ingreso_postea_asiento_y_sube_saldo(): void
    {
        $caja = $this->crearCaja();
        $this->reembolsar($caja, 100);

        $this->movimiento($caja, 'INGRESO', 25)->assertSessionHasNoErrors();

        $mov = CajaMovimiento::where('tipo_movimiento', 'INGRESO')->firstOrFail();
        // D caja-efectivo 25 / C contrapartida 25
        $this->assertSame($this->efectivo->id, $mov->asiento->detalle[0]->cuenta_id);
        $this->assertSame('25.00', (string) $mov->asiento->detalle[0]->debito);

        $this->assertSame(125.0, $caja->fresh()->saldoSistema());
    }

    public function test_movimiento_requiere_cuenta_de_efectivo(): void
    {
        $caja = $this->crearCaja(conCuenta: false);

        $this->movimiento($caja, 'EGRESO', 30)->assertSessionHasErrors('movimiento');

        $this->assertSame(0, CajaMovimiento::count());
        $this->assertSame(0, Asiento::count());
    }

    public function test_contrapartida_no_puede_ser_la_cuenta_de_la_caja(): void
    {
        $caja = $this->crearCaja();

        $this->actuar()->post(route('admin.caja.cajas.movimientos', $caja), [
            'tipo_movimiento' => 'EGRESO', 'fecha' => '2026-06-13', 'monto' => 10,
            'cuenta_contable_id' => $this->efectivo->id,
        ])->assertSessionHasErrors('cuenta_contable_id');
    }

    public function test_reembolso_postea_asiento(): void
    {
        $caja = $this->crearCaja();
        $this->reembolsar($caja, 200);

        $asiento = Asiento::where('origen_modulo', 'CAJA')->firstOrFail();
        // D caja-efectivo 200 / C banco 200
        $this->assertSame($this->efectivo->id, $asiento->detalle[0]->cuenta_id);
        $this->assertSame('200.00', (string) $asiento->detalle[0]->debito);
        $this->assertSame($this->banco->id, $asiento->detalle[1]->cuenta_id);
        $this->assertSame('200.00', (string) $asiento->detalle[1]->credito);

        $this->assertSame(200.0, $caja->fresh()->saldoSistema());
    }

    public function test_vale_pendiente_reduce_saldo_sin_asiento(): void
    {
        $caja = $this->crearCaja();
        $this->reembolsar($caja, 100);

        $this->actuar()->post(route('admin.caja.cajas.vales', $caja), [
            'fecha' => '2026-06-13', 'beneficiario' => 'Ana', 'monto' => 40, 'motivo' => 'Compra urgente',
        ])->assertSessionHasNoErrors();

        $vale = CajaVale::firstOrFail();
        $this->assertSame('PENDIENTE', $vale->estado);
        // El vale no genera asiento, pero reduce el saldo disponible.
        $this->assertSame(1, Asiento::where('origen_modulo', 'CAJA')->count()); // solo el del reembolso
        $this->assertSame(60.0, $caja->fresh()->saldoSistema());
    }

    public function test_liquidar_vale_crea_egreso_contabilizado(): void
    {
        $caja = $this->crearCaja();
        $this->reembolsar($caja, 100);

        $this->actuar()->post(route('admin.caja.cajas.vales', $caja), [
            'fecha' => '2026-06-13', 'beneficiario' => 'Ana', 'monto' => 40,
        ])->assertSessionHasNoErrors();
        $vale = CajaVale::firstOrFail();

        $this->actuar()->post(route('admin.caja.vales.liquidar', $vale), [
            'fecha' => '2026-06-14', 'cuenta_contable_id' => $this->gasto->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('LIQUIDADO', $vale->fresh()->estado);

        $mov = CajaMovimiento::where('tipo_movimiento', 'EGRESO')->firstOrFail();
        $this->assertSame('40.00', (string) $mov->monto);
        $this->assertNotNull($mov->asiento);
        $this->assertSame('POSTEADO', $mov->asiento->estado);

        // Saldo: 100 reembolso - 40 egreso - 0 vales pendientes = 60 (igual que antes de liquidar)
        $this->assertSame(60.0, $caja->fresh()->saldoSistema());
    }

    public function test_arqueo_calcula_diferencia(): void
    {
        $caja = $this->crearCaja();
        $this->reembolsar($caja, 100);

        // Conteo físico: 4x20 + 3x5 = 95  -> diferencia -5 vs sistema 100
        $this->actuar()->post(route('admin.caja.cajas.arqueos', $caja), [
            'fecha' => '2026-06-13',
            'denominaciones' => [
                ['denominacion' => 20, 'cantidad' => 4],
                ['denominacion' => 5, 'cantidad' => 3],
            ],
        ])->assertSessionHasNoErrors();

        $arqueo = $caja->arqueos()->firstOrFail();
        $this->assertSame('100.00', (string) $arqueo->saldo_sistema);
        $this->assertSame('95.00', (string) $arqueo->saldo_fisico);
        $this->assertSame('-5.00', (string) $arqueo->diferencia);
        $this->assertSame(2, $arqueo->detalle()->count());
    }

    public function test_saldo_combina_reembolsos_ingresos_y_egresos(): void
    {
        $caja = $this->crearCaja();
        $this->reembolsar($caja, 100);
        $this->movimiento($caja, 'EGRESO', 30)->assertSessionHasNoErrors();
        $this->movimiento($caja, 'INGRESO', 10)->assertSessionHasNoErrors();

        // 100 + 10 - 30 = 80
        $this->assertSame(80.0, $caja->fresh()->saldoSistema());
    }
}
