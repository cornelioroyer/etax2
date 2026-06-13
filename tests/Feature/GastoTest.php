<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GastoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private CuentaContable $gasto;

    private CuentaContable $banco;

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
            'conciliable' => false,
            'activa' => true,
        ]);

        $this->gasto = $crear('50101', 'Gastos de Oficina', 'DEBITO');
        $this->banco = $crear('11102', 'Banco General', 'DEBITO');
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function registrarGasto(float $monto = 80, ?int $cuentaGasto = null, ?int $cuentaPago = null)
    {
        return $this->actuar()->post(route('admin.compras.gastos.store'), [
            'fecha' => '2026-06-12',
            'descripcion' => 'Compra de papeleria',
            'cuenta_gasto_id' => $cuentaGasto ?? $this->gasto->id,
            'cuenta_pago_id' => $cuentaPago ?? $this->banco->id,
            'monto' => $monto,
            'referencia' => 'REC-123',
        ]);
    }

    public function test_listado_de_gastos_se_muestra(): void
    {
        $this->actuar()->get(route('admin.compras.gastos.index'))
            ->assertOk();
    }

    public function test_registrar_gasto_postea_asiento_cuadrado(): void
    {
        $this->registrarGasto(80)->assertSessionHasNoErrors();

        $asiento = Asiento::where('origen_modulo', 'GASTO')->latest('id')->firstOrFail();
        $this->assertSame('POSTEADO', $asiento->estado);
        $this->assertSame('80.00', (string) $asiento->total_debito);
        $this->assertSame('80.00', (string) $asiento->total_credito);

        $lineas = $asiento->detalle;
        $this->assertCount(2, $lineas);
        $this->assertSame($this->gasto->id, $lineas[0]->cuenta_id);
        $this->assertSame('80.00', (string) $lineas[0]->debito);
        $this->assertSame($this->banco->id, $lineas[1]->cuenta_id);
        $this->assertSame('80.00', (string) $lineas[1]->credito);
    }

    public function test_gasto_registrado_aparece_en_listado(): void
    {
        $this->registrarGasto(80)->assertSessionHasNoErrors();

        $this->actuar()->get(route('admin.compras.gastos.index'))
            ->assertOk()
            ->assertSee('Compra de papeleria');
    }

    public function test_cuenta_gasto_y_pago_no_pueden_ser_la_misma(): void
    {
        $this->registrarGasto(80, $this->gasto->id, $this->gasto->id)
            ->assertSessionHasErrors('cuenta_pago_id');

        $this->assertSame(0, Asiento::where('origen_modulo', 'GASTO')->count());
    }

    public function test_monto_debe_ser_positivo(): void
    {
        $this->registrarGasto(0)->assertSessionHasErrors('monto');

        $this->assertSame(0, Asiento::where('origen_modulo', 'GASTO')->count());
    }
}
