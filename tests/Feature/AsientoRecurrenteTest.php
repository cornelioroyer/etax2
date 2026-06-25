<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\AsientoRecurrente;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\User;
use App\Services\GeneradorAsientosRecurrentes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AsientoRecurrenteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private CuentaContable $gasto;

    private CuentaContable $banco;

    private CuentaContable $cxp;

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

        $this->gasto = $crear('60101', 'Alquiler', 'DEBITO');
        $this->banco = $crear('10102', 'Bancos', 'DEBITO');
        $this->cxp = $crear('20101', 'Cuentas por Pagar', 'CREDITO');

        // CXP queda registrada como cuenta de control para probar el bloqueo.
        CuentaDefault::create([
            'compania_id' => $this->compania->id,
            'clave' => 'CXP',
            'cuenta_id' => $this->cxp->id,
        ]);
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'nombre' => 'Alquiler local',
            'descripcion' => 'Alquiler mensual',
            'frecuencia' => 'MENSUAL',
            'fecha_inicio' => '2026-01-15',
            'lineas' => [
                ['cuenta_id' => $this->gasto->id, 'debito' => 500, 'credito' => 0],
                ['cuenta_id' => $this->banco->id, 'debito' => 0, 'credito' => 500],
            ],
        ], $override);
    }

    public function test_crear_plantilla_cuadrada_fija_proxima_fecha_en_el_inicio(): void
    {
        $this->actuar()->post(route('admin.asientos-recurrentes.store'), $this->payload())
            ->assertRedirect();

        $plantilla = AsientoRecurrente::first();

        $this->assertNotNull($plantilla);
        $this->assertSame('ACTIVA', $plantilla->estado);
        $this->assertSame('2026-01-15', $plantilla->proxima_fecha->format('Y-m-d'));
        $this->assertCount(2, $plantilla->detalle);
        $this->assertEquals(500, $plantilla->total_debito);
    }

    public function test_plantilla_descuadrada_se_rechaza(): void
    {
        $this->actuar()->post(route('admin.asientos-recurrentes.store'), $this->payload([
            'lineas' => [
                ['cuenta_id' => $this->gasto->id, 'debito' => 500, 'credito' => 0],
                ['cuenta_id' => $this->banco->id, 'debito' => 0, 'credito' => 400],
            ],
        ]))->assertSessionHasErrors('lineas');

        $this->assertSame(0, AsientoRecurrente::count());
    }

    public function test_cuenta_de_control_se_bloquea(): void
    {
        $this->actuar()->post(route('admin.asientos-recurrentes.store'), $this->payload([
            'lineas' => [
                ['cuenta_id' => $this->gasto->id, 'debito' => 500, 'credito' => 0],
                ['cuenta_id' => $this->cxp->id, 'debito' => 0, 'credito' => 500],
            ],
        ]))->assertSessionHasErrors('lineas.1');

        $this->assertSame(0, AsientoRecurrente::count());
    }

    public function test_cuenta_bancaria_se_bloquea(): void
    {
        // La cuenta contable del banco es de control en cuanto existe una cuenta
        // bancaria que la usa: se mueve solo desde el módulo de Bancos.
        DB::table('bco_cuentas')->insert([
            'compania_id' => $this->compania->id,
            'banco_id' => 1,
            'cuenta_contable_id' => $this->banco->id,
            'numero_cuenta' => '00-11-22',
            'nombre' => 'Cuenta Corriente',
        ]);

        // El payload por defecto acredita la cuenta de banco en la línea 1.
        $this->actuar()->post(route('admin.asientos-recurrentes.store'), $this->payload())
            ->assertSessionHasErrors('lineas.1');

        $this->assertSame(0, AsientoRecurrente::count());
    }

    public function test_generar_crea_borradores_cuadrados_y_es_idempotente(): void
    {
        $plantilla = $this->crearPlantilla(['fecha_inicio' => '2026-01-15']);

        $generador = app(GeneradorAsientosRecurrentes::class);

        // "Hoy" = 2026-03-20 → vencen ene-15, feb-15, mar-15 = 3 asientos.
        $hasta = Carbon::parse('2026-03-20');
        $creados = $generador->generarPlantilla($plantilla, $hasta, 'test');

        $this->assertSame(3, $creados);

        $asientos = Asiento::where('origen_tabla', AsientoRecurrente::ORIGEN_TABLA)
            ->where('origen_id', $plantilla->id)->get();

        $this->assertCount(3, $asientos);
        foreach ($asientos as $a) {
            $this->assertSame('BORRADOR', $a->estado);
            $this->assertEquals($a->total_debito, $a->total_credito);
            $this->assertEquals(500, $a->total_debito);
        }

        $plantilla->refresh();
        $this->assertSame(3, $plantilla->ocurrencias_generadas);
        $this->assertSame('2026-04-15', $plantilla->proxima_fecha->format('Y-m-d'));

        // Segunda corrida hasta la misma fecha no duplica nada.
        $this->assertSame(0, $generador->generarPlantilla($plantilla, $hasta, 'test'));
        $this->assertSame(3, Asiento::where('origen_id', $plantilla->id)
            ->where('origen_tabla', AsientoRecurrente::ORIGEN_TABLA)->count());
    }

    public function test_finaliza_al_alcanzar_el_maximo_de_ocurrencias(): void
    {
        $plantilla = $this->crearPlantilla([
            'fecha_inicio' => '2026-01-15',
            'ocurrencias_max' => 2,
        ]);

        $creados = app(GeneradorAsientosRecurrentes::class)
            ->generarPlantilla($plantilla, Carbon::parse('2026-12-31'), 'test');

        $this->assertSame(2, $creados);

        $plantilla->refresh();
        $this->assertSame('FINALIZADA', $plantilla->estado);
        $this->assertSame(2, $plantilla->ocurrencias_generadas);
    }

    public function test_plantilla_pausada_no_genera(): void
    {
        $plantilla = $this->crearPlantilla(['fecha_inicio' => '2026-01-15']);
        $plantilla->update(['estado' => AsientoRecurrente::ESTADO_PAUSADA]);

        $creados = app(GeneradorAsientosRecurrentes::class)
            ->generarPlantilla($plantilla, Carbon::parse('2026-06-30'), 'test');

        $this->assertSame(0, $creados);
        $this->assertSame(0, Asiento::where('origen_id', $plantilla->id)
            ->where('origen_tabla', AsientoRecurrente::ORIGEN_TABLA)->count());
    }

    private function crearPlantilla(array $override = []): AsientoRecurrente
    {
        $base = $this->payload($override);

        $plantilla = AsientoRecurrente::create([
            'compania_id' => $this->compania->id,
            'nombre' => $base['nombre'],
            'descripcion' => $base['descripcion'] ?? null,
            'frecuencia' => $base['frecuencia'],
            'fecha_inicio' => $base['fecha_inicio'],
            'fecha_fin' => $base['fecha_fin'] ?? null,
            'ocurrencias_max' => $base['ocurrencias_max'] ?? null,
            'proxima_fecha' => $base['fecha_inicio'],
            'estado' => AsientoRecurrente::ESTADO_ACTIVA,
            'total_debito' => 500,
            'total_credito' => 500,
        ]);

        foreach ($base['lineas'] as $i => $l) {
            $plantilla->detalle()->create([
                'linea' => $i + 1,
                'cuenta_id' => $l['cuenta_id'],
                'debito' => $l['debito'],
                'credito' => $l['credito'],
            ]);
        }

        return $plantilla;
    }
}
