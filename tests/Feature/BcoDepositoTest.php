<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\BcoBanco;
use App\Models\BcoCuenta;
use App\Models\BcoDeposito;
use App\Models\BcoMovimiento;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BcoDepositoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private CuentaContable $glBanco;

    private CuentaContable $glCaja;

    private BcoCuenta $banco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);

        $crear = fn (string $codigo, string $nombre, string $naturaleza) => CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'nivel' => 3,
            'naturaleza' => $naturaleza,
            'permite_movimiento' => true,
            'activa' => true,
        ]);

        $this->glBanco = $crear('10102', 'Bancos', 'DEBITO');
        $this->glCaja = $crear('10101', 'Caja', 'DEBITO');

        $bancoEntidad = BcoBanco::create(['nombre' => 'Banco General', 'activo' => true]);
        $this->banco = BcoCuenta::create([
            'compania_id' => $this->compania->id,
            'banco_id' => $bancoEntidad->id,
            'cuenta_contable_id' => $this->glBanco->id,
            'numero_cuenta' => '0001',
            'nombre' => 'Cuenta corriente',
            'saldo_inicial' => 0,
            'activa' => true,
        ]);
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    public function test_deposito_genera_asiento_dr_banco_cr_origen(): void
    {
        $this->actuar()->post(route('admin.bco.depositos.store'), [
            'cuenta_bancaria_id' => $this->banco->id,
            'fecha' => '2026-06-12',
            'referencia' => 'DEP-001',
            'monto' => 500,
            'cuenta_origen_id' => $this->glCaja->id,
        ])->assertSessionHasNoErrors();

        $deposito = BcoDeposito::firstOrFail();
        $this->assertNotNull($deposito->asiento_id, 'El depósito debe quedar enlazado a su asiento.');

        // Movimiento bancario de ingreso (crédito = ingreso a la cuenta).
        $movDeposito = BcoMovimiento::where('tipo_movimiento', BcoMovimiento::TIPO_DEPOSITO)->sole();
        $this->assertEquals(500, $movDeposito->credito);

        $asiento = Asiento::findOrFail($deposito->asiento_id);
        $this->assertSame(Asiento::ESTADO_POSTEADO, $asiento->estado);
        $this->assertSame('BANCOS', $asiento->origen_modulo);
        $this->assertSame('bco_depositos', $asiento->origen_tabla);
        $this->assertSame($deposito->id, $asiento->origen_id);
        $this->assertEquals(500, $asiento->total_debito);
        $this->assertEquals(500, $asiento->total_credito);

        // DR banco / CR cuenta origen.
        $dr = $asiento->detalle()->where('cuenta_id', $this->glBanco->id)->sole();
        $cr = $asiento->detalle()->where('cuenta_id', $this->glCaja->id)->sole();
        $this->assertEquals(500, $dr->debito);
        $this->assertEquals(0, $dr->credito);
        $this->assertEquals(0, $cr->debito);
        $this->assertEquals(500, $cr->credito);
    }

    public function test_deposito_sin_cuenta_origen_no_genera_asiento(): void
    {
        $this->actuar()->post(route('admin.bco.depositos.store'), [
            'cuenta_bancaria_id' => $this->banco->id,
            'fecha' => '2026-06-12',
            'monto' => 250,
        ])->assertSessionHasNoErrors();

        $deposito = BcoDeposito::firstOrFail();
        $this->assertNull($deposito->asiento_id);
        $this->assertSame(0, Asiento::count());
    }
}
