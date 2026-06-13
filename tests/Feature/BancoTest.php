<?php

namespace Tests\Feature;

use App\Models\BancoCuenta;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BancoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private CuentaContable $cuenta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);
        $this->cuenta = CuentaContable::create([
            'compania_id' => $this->compania->id, 'codigo' => '11102', 'nombre' => 'Banco General',
            'nivel' => 3, 'naturaleza' => 'DEBITO', 'permite_movimiento' => true, 'conciliable' => true, 'activa' => true,
        ]);
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'banco_nombre' => 'Banco General',
            'numero_cuenta' => '04-1234567',
            'tipo' => 'CORRIENTE',
            'moneda' => 'PAB',
            'cuenta_contable_id' => $this->cuenta->id,
            'saldo_inicial' => 1500,
        ], $extra);
    }

    public function test_listado_se_muestra(): void
    {
        $this->actuar()->get(route('admin.bancos.index'))->assertOk();
    }

    public function test_crear_cuenta_bancaria(): void
    {
        $this->actuar()->post(route('admin.bancos.store'), $this->datos())
            ->assertSessionHasNoErrors();

        $cuenta = BancoCuenta::firstOrFail();
        $this->assertSame('04-1234567', $cuenta->numero_cuenta);
        $this->assertSame('CORRIENTE', $cuenta->tipo);
        $this->assertSame('1500.00', (string) $cuenta->saldo_inicial);
        $this->assertTrue((bool) $cuenta->activa);
        $this->assertSame($this->cuenta->id, $cuenta->cuenta_contable_id);
    }

    public function test_numero_de_cuenta_no_se_repite(): void
    {
        $this->actuar()->post(route('admin.bancos.store'), $this->datos())->assertSessionHasNoErrors();

        $this->actuar()->post(route('admin.bancos.store'), $this->datos(['banco_nombre' => 'Otro Banco']))
            ->assertSessionHasErrors('numero_cuenta');

        $this->assertSame(1, BancoCuenta::count());
    }

    public function test_tipo_invalido_es_rechazado(): void
    {
        $this->actuar()->post(route('admin.bancos.store'), $this->datos(['tipo' => 'XXX']))
            ->assertSessionHasErrors('tipo');

        $this->assertSame(0, BancoCuenta::count());
    }

    public function test_actualizar_cuenta(): void
    {
        $this->actuar()->post(route('admin.bancos.store'), $this->datos())->assertSessionHasNoErrors();
        $cuenta = BancoCuenta::firstOrFail();

        $this->actuar()->put(route('admin.bancos.update', $cuenta), [
            'banco_nombre' => 'Banco Nacional',
            'tipo' => 'AHORROS',
            'moneda' => 'USD',
            'saldo_inicial' => 200,
        ])->assertSessionHasNoErrors();

        $cuenta->refresh();
        $this->assertSame('Banco Nacional', $cuenta->banco_nombre);
        $this->assertSame('AHORROS', $cuenta->tipo);
        $this->assertSame('USD', $cuenta->moneda);
    }

    public function test_toggle_activa(): void
    {
        $this->actuar()->post(route('admin.bancos.store'), $this->datos())->assertSessionHasNoErrors();
        $cuenta = BancoCuenta::firstOrFail();

        $this->actuar()->post(route('admin.bancos.toggle', $cuenta))->assertSessionHasNoErrors();
        $this->assertFalse((bool) $cuenta->fresh()->activa);

        $this->actuar()->post(route('admin.bancos.toggle', $cuenta))->assertSessionHasNoErrors();
        $this->assertTrue((bool) $cuenta->fresh()->activa);
    }
}
