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

class EstadoResultadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_estado_de_resultado_con_datos(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $compania = Compania::create(['nombre' => 'COMPANIA RESULTADO', 'activa' => true]);

        $tipos = collect([
            ['ACTIVO', 'DEBITO'], ['INGRESO', 'CREDITO'], ['COSTO', 'DEBITO'], ['GASTO', 'DEBITO'],
        ])->mapWithKeys(fn ($t) => [$t[0] => TipoCuenta::firstOrCreate(['codigo' => $t[0]], ['nombre' => ucfirst(strtolower($t[0])), 'naturaleza' => $t[1]])->id]);

        $cuenta = fn (string $codigo, string $nombre, string $tipo) => CuentaContable::create([
            'compania_id' => $compania->id, 'codigo' => $codigo, 'nombre' => $nombre,
            'nivel' => strlen($codigo) >= 5 ? 3 : 2, 'tipo_cuenta_id' => $tipos[$tipo],
            'naturaleza' => in_array($tipo, ['ACTIVO', 'COSTO', 'GASTO']) ? 'DEBITO' : 'CREDITO',
            'permite_movimiento' => strlen($codigo) >= 5, 'conciliable' => false, 'activa' => true,
        ]);

        $cuenta('401', 'Ingresos', 'INGRESO');
        $ventas = $cuenta('40101', 'Ventas', 'INGRESO');
        $cuenta('501', 'Costos', 'COSTO');
        $costo = $cuenta('50101', 'Costo de Ventas', 'COSTO');
        $cuenta('601', 'Gastos de Operacion', 'GASTO');
        $alquiler = $cuenta('60101', 'Alquileres', 'GASTO');

        $periodo = fn (int $mes) => PeriodoContable::create([
            'compania_id' => $compania->id, 'anio' => 2026, 'mes' => $mes,
            'fecha_inicio' => sprintf('2026-%02d-01', $mes), 'fecha_fin' => sprintf('2026-%02d-28', $mes), 'estado' => 'ABIERTO',
        ]);
        $ene = $periodo(1);
        $feb = $periodo(2);

        $saldo = fn ($periodoId, $cuentaId, $debito, $credito) => DB::table('cgl_saldos')->insert([
            'compania_id' => $compania->id, 'periodo_id' => $periodoId, 'cuenta_id' => $cuentaId,
            'debito' => $debito, 'credito' => $credito, 'saldo' => $debito - $credito,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Enero: ventas 1000, costo 300, gasto 200 → neta 500
        $saldo($ene->id, $ventas->id, 0, 1000);
        $saldo($ene->id, $costo->id, 300, 0);
        $saldo($ene->id, $alquiler->id, 200, 0);
        // Febrero: ventas 2000, costo 500, gasto 400 → neta 1100; YTD 1600
        $saldo($feb->id, $ventas->id, 0, 2000);
        $saldo($feb->id, $costo->id, 500, 0);
        $saldo($feb->id, $alquiler->id, 400, 0);

        $this->actingAs($user)
            ->withSession(['compania_activa_id' => $compania->id])
            ->get(route('admin.reportes.resultado', ['anio' => 2026, 'mes' => 2]))
            ->assertOk()
            ->assertSee('Estado de Resultado')
            ->assertSee('UTILIDAD BRUTA')
            ->assertSee('1,100.00')   // neta del mes (febrero)
            ->assertSee('1,600.00')   // neta acumulada
            ->assertSee('3,000.00');  // ingresos YTD
    }

    public function test_estado_resultado_sin_asientos_muestra_aviso(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $compania = Compania::create(['nombre' => 'COMPANIA VACIA', 'activa' => true]);

        $this->actingAs($user)
            ->withSession(['compania_activa_id' => $compania->id])
            ->get(route('admin.reportes.resultado'))
            ->assertOk()
            ->assertSee('no tiene asientos posteados');
    }
}
