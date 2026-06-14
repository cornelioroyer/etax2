<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceComprobacionTest extends TestCase
{
    use RefreshDatabase;

    private function cuenta(Compania $compania, string $codigo, string $nombre, string $naturaleza): CuentaContable
    {
        return CuentaContable::create([
            'compania_id' => $compania->id, 'codigo' => $codigo, 'nombre' => $nombre,
            'nivel' => 3, 'naturaleza' => $naturaleza,
            'permite_movimiento' => true, 'conciliable' => false, 'activa' => true,
        ]);
    }

    private function postear(User $admin, Compania $compania, string $fecha, array $lineas): void
    {
        $this->actingAs($admin)
            ->withSession(['compania_activa_id' => $compania->id])
            ->post(route('admin.asientos.store'), [
                'fecha' => $fecha,
                'descripcion' => 'Asiento '.$fecha,
                'lineas' => $lineas,
                'accion' => 'postear',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_balance_por_rango_separa_inicial_y_movimiento(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $compania = Compania::create(['nombre' => 'COMPANIA COMPROBACION', 'activa' => true]);

        $caja = $this->cuenta($compania, '10101', 'Caja', 'DEBITO');
        $prov = $this->cuenta($compania, '20101', 'Proveedores', 'CREDITO');
        $ventas = $this->cuenta($compania, '40101', 'Ventas', 'CREDITO');

        // Mayo (antes del rango) → forma el Balance Inicial.
        $this->postear($admin, $compania, '2026-05-15', [
            ['cuenta_id' => $caja->id, 'debito' => 1000, 'credito' => 0],
            ['cuenta_id' => $ventas->id, 'debito' => 0, 'credito' => 1000],
        ]);

        // Junio (dentro del rango) → movimiento del período.
        $this->postear($admin, $compania, '2026-06-10', [
            ['cuenta_id' => $caja->id, 'debito' => 500, 'credito' => 0],
            ['cuenta_id' => $prov->id, 'debito' => 0, 'credito' => 500],
        ]);

        // Rango junio: Caja inicial 1000 + débito 500 = final 1500 ; Ventas inicial (1000)
        // sin movimiento ; Proveedores crédito 500 corriente (500). Débito=Crédito=500.
        $this->actingAs($admin)
            ->withSession(['compania_activa_id' => $compania->id])
            ->get(route('admin.reportes.comprobacion', ['desde' => '2026-06-01', 'hasta' => '2026-06-30']))
            ->assertOk()
            ->assertSee('Balance de Comprobación')
            ->assertSee('Del 01/06/2026 al 30/06/2026')
            ->assertSee('1,500.00')      // Balance Final de Caja
            ->assertSee('(1,000.00)')    // Ventas: saldo acreedor heredado del inicial
            ->assertSee('(500.00)')      // Proveedores: corriente/final acreedor
            ->assertDontSee('no cuadra');
    }

    public function test_detalle_del_saldo_lista_inicial_y_movimientos(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $compania = Compania::create(['nombre' => 'COMPANIA DETALLE', 'activa' => true]);

        $caja = $this->cuenta($compania, '10101', 'Caja', 'DEBITO');
        $ventas = $this->cuenta($compania, '40101', 'Ventas', 'CREDITO');

        // Mayo → balance inicial de Caja = 1000.
        $this->postear($admin, $compania, '2026-05-15', [
            ['cuenta_id' => $caja->id, 'debito' => 1000, 'credito' => 0],
            ['cuenta_id' => $ventas->id, 'debito' => 0, 'credito' => 1000],
        ]);

        // Junio → un movimiento dentro del rango (+500), final = 1500.
        $this->postear($admin, $compania, '2026-06-10', [
            ['cuenta_id' => $caja->id, 'debito' => 500, 'credito' => 0],
            ['cuenta_id' => $ventas->id, 'debito' => 0, 'credito' => 500],
        ]);

        $this->actingAs($admin)
            ->withSession(['compania_activa_id' => $compania->id])
            ->getJson(route('admin.reportes.comprobacion.detalle', [
                'cuenta' => $caja->id, 'desde' => '2026-06-01', 'hasta' => '2026-06-30',
            ]))
            ->assertOk()
            ->assertJsonPath('cuenta.codigo', '10101')
            ->assertJsonPath('inicial', 1000)
            ->assertJsonPath('final', 1500)
            ->assertJsonCount(1, 'movimientos')
            ->assertJsonPath('movimientos.0.debito', 500)
            ->assertJsonPath('movimientos.0.saldo', 1500);
    }

    public function test_comprobacion_sin_asientos_muestra_aviso(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $compania = Compania::create(['nombre' => 'COMPANIA VACIA', 'activa' => true]);

        $this->actingAs($user)
            ->withSession(['compania_activa_id' => $compania->id])
            ->get(route('admin.reportes.comprobacion'))
            ->assertOk()
            ->assertSee('no tiene asientos posteados');
    }
}
