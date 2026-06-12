<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\PeriodoContable;
use App\Models\TipoCuenta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BalanceSituacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_de_situacion_con_datos(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $compania = Compania::create(['nombre' => 'COMPANIA BALANCE', 'activa' => true]);

        $tipos = collect([
            ['ACTIVO', 'DEBITO'], ['PASIVO', 'CREDITO'], ['PATRIMONIO', 'CREDITO'],
            ['INGRESO', 'CREDITO'], ['GASTO', 'DEBITO'],
        ])->mapWithKeys(fn ($t) => [$t[0] => TipoCuenta::firstOrCreate(['codigo' => $t[0]], ['nombre' => ucfirst(strtolower($t[0])), 'naturaleza' => $t[1]])->id]);

        $periodo = PeriodoContable::create([
            'compania_id' => $compania->id, 'anio' => 2026, 'mes' => 5,
            'fecha_inicio' => '2026-05-01', 'fecha_fin' => '2026-05-31', 'estado' => 'ABIERTO',
        ]);

        $cuenta = fn (string $codigo, string $nombre, string $tipo) => CuentaContable::create([
            'compania_id' => $compania->id, 'codigo' => $codigo, 'nombre' => $nombre,
            'nivel' => strlen($codigo) >= 5 ? 3 : 2, 'tipo_cuenta_id' => $tipos[$tipo],
            'naturaleza' => in_array($tipo, ['ACTIVO', 'GASTO']) ? 'DEBITO' : 'CREDITO',
            'permite_movimiento' => strlen($codigo) >= 5, 'conciliable' => false, 'activa' => true,
        ]);

        $cuenta('101', 'Activo Corriente', 'ACTIVO');
        $caja = $cuenta('10101', 'Caja', 'ACTIVO');
        $cuenta('201', 'Pasivo Corriente', 'PASIVO');
        $prov = $cuenta('20101', 'Proveedores', 'PASIVO');
        $cuenta('401', 'Ingresos', 'INGRESO');
        $ventas = $cuenta('40101', 'Ventas', 'INGRESO');

        // Venta al contado 1000 + compra a crédito que queda por pagar 250
        $saldo = fn ($cuentaId, $debito, $credito) => DB::table('cgl_saldos')->insert([
            'compania_id' => $compania->id, 'periodo_id' => $periodo->id, 'cuenta_id' => $cuentaId,
            'debito' => $debito, 'credito' => $credito, 'saldo' => $debito - $credito,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $saldo($caja->id, 1000, 0);
        $saldo($ventas->id, 0, 750);
        $saldo($prov->id, 0, 250);

        // Activos 1000 = Pasivos 250 + Utilidad 750
        $this->actingAs($user)
            ->withSession(['compania_activa_id' => $compania->id])
            ->get(route('admin.reportes.balance'))
            ->assertOk()
            ->assertSee('Balance de Situación')
            ->assertSee('TOTAL ACTIVOS')
            ->assertSee('1,000.00')
            ->assertSee('250.00')
            ->assertSee('750.00');
    }

    public function test_balance_sin_asientos_muestra_aviso(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $compania = Compania::create(['nombre' => 'COMPANIA VACIA', 'activa' => true]);

        $this->actingAs($user)
            ->withSession(['compania_activa_id' => $compania->id])
            ->get(route('admin.reportes.balance'))
            ->assertOk()
            ->assertSee('no tiene asientos posteados');
    }
}
