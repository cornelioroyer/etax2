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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_sin_compania_activa(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Estado Financiero');
    }

    public function test_dashboard_con_saldos_reales(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $compania = Compania::create(['nombre' => 'COMPANIA DASH', 'activa' => true]);

        $tipos = collect([
            ['ACTIVO', 'DEBITO'], ['PASIVO', 'CREDITO'], ['PATRIMONIO', 'CREDITO'],
            ['INGRESO', 'CREDITO'], ['GASTO', 'DEBITO'],
        ])->mapWithKeys(fn ($t) => [$t[0] => TipoCuenta::firstOrCreate(['codigo' => $t[0]], ['nombre' => ucfirst(strtolower($t[0])), 'naturaleza' => $t[1]])->id]);

        $periodo = PeriodoContable::create([
            'compania_id' => $compania->id, 'anio' => 2026, 'mes' => 6,
            'fecha_inicio' => '2026-06-01', 'fecha_fin' => '2026-06-30', 'estado' => 'ABIERTO',
        ]);

        $cuenta = fn (string $codigo, string $nombre, string $tipo) => CuentaContable::create([
            'compania_id' => $compania->id, 'codigo' => $codigo, 'nombre' => $nombre,
            'nivel' => strlen($codigo) >= 5 ? 3 : 2, 'tipo_cuenta_id' => $tipos[$tipo],
            'naturaleza' => in_array($tipo, ['ACTIVO', 'GASTO']) ? 'DEBITO' : 'CREDITO',
            'permite_movimiento' => strlen($codigo) >= 5, 'conciliable' => false, 'activa' => true,
        ]);

        $cuenta('101', 'Activo Corriente', 'ACTIVO');
        $caja = $cuenta('10101', 'Caja', 'ACTIVO');
        $cuenta('401', 'Ingresos por Ventas', 'INGRESO');
        $ventas = $cuenta('40101', 'Ventas', 'INGRESO');
        $cuenta('601', 'Gastos de Operacion', 'GASTO');
        $alquiler = $cuenta('60101', 'Alquileres', 'GASTO');

        // Venta de 1000 y gasto de 400 → utilidad 600, activos 600
        $saldo = fn ($cuentaId, $debito, $credito) => DB::table('cgl_saldos')->insert([
            'compania_id' => $compania->id, 'periodo_id' => $periodo->id, 'cuenta_id' => $cuentaId,
            'debito' => $debito, 'credito' => $credito, 'saldo' => $debito - $credito,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $saldo($caja->id, 1000, 400);
        $saldo($ventas->id, 0, 1000);
        $saldo($alquiler->id, 400, 0);

        $this->actingAs($user)
            ->withSession(['compania_activa_id' => $compania->id])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Estado Financiero')
            ->assertSee('B/. 600.00');
    }
}
