<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CxpDocumento;
use App\Models\CxpRecurrente;
use App\Models\User;
use App\Services\GeneradorCxpRecurrentes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CxpRecurrenteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private CuentaContable $gasto;

    private Contacto $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);

        $this->gasto = CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => '60105',
            'nombre' => 'Alquileres',
            'nivel' => 3,
            'naturaleza' => 'DEBITO',
            'permite_movimiento' => true,
            'activa' => true,
        ]);

        $this->proveedor = Contacto::create([
            'compania_id' => $this->compania->id,
            'nombre' => 'ARRENDADOR S.A.',
            'tipo_persona' => 'JURIDICA',
            'activo' => true,
        ]);
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'proveedor_id' => $this->proveedor->id,
            'nombre' => 'Alquiler local',
            'frecuencia' => 'MENSUAL',
            'fecha_inicio' => '2026-01-15',
            'dias_credito' => 5,
            'lineas' => [
                ['descripcion' => 'Alquiler mensual', 'cantidad' => 1, 'precio_unitario' => 500, 'tasa_itbms' => 7, 'cuenta_id' => $this->gasto->id],
            ],
        ], $override);
    }

    public function test_crear_plantilla_calcula_totales_y_fija_proxima_fecha(): void
    {
        $this->actuar()->post(route('admin.cxp.recurrentes.store'), $this->payload())
            ->assertRedirect();

        $p = CxpRecurrente::first();
        $this->assertNotNull($p);
        $this->assertSame('2026-01-15', $p->proxima_fecha->format('Y-m-d'));
        $this->assertEquals(500, $p->subtotal);
        $this->assertEquals(35, $p->impuesto);   // 500 * 7%
        $this->assertEquals(535, $p->total);
        $this->assertCount(1, $p->detalle);
    }

    public function test_plantilla_sin_total_se_rechaza(): void
    {
        $this->actuar()->post(route('admin.cxp.recurrentes.store'), $this->payload([
            'lineas' => [
                ['descripcion' => 'Sin valor', 'cantidad' => 1, 'precio_unitario' => 0, 'tasa_itbms' => 0, 'cuenta_id' => $this->gasto->id],
            ],
        ]))->assertSessionHasErrors('lineas');

        $this->assertSame(0, CxpRecurrente::count());
    }

    public function test_generar_crea_facturas_borrador_y_es_idempotente(): void
    {
        $plantilla = $this->crearPlantilla(['fecha_inicio' => '2026-01-15']);

        $generador = app(GeneradorCxpRecurrentes::class);

        // "Hoy" = 2026-03-20 → vencen ene-15, feb-15, mar-15 = 3 facturas.
        $creadas = $generador->generarPlantilla($plantilla, Carbon::parse('2026-03-20'), 'test');

        $this->assertSame(3, $creadas);

        $facturas = CxpDocumento::where('recurrente_id', $plantilla->id)->get();
        $this->assertCount(3, $facturas);
        foreach ($facturas as $f) {
            $this->assertSame(CxpDocumento::ESTADO_BORRADOR, $f->estado);
            $this->assertSame(CxpDocumento::TIPO_FACTURA, $f->tipo_documento);
            $this->assertEquals(535, $f->total);          // 500 + 7%
            $this->assertEquals(535, $f->saldo);
            $this->assertSame($this->proveedor->id, $f->proveedor_id);
        }

        // Vencimiento = fecha + dias_credito (5).
        $primera = $facturas->first(fn ($f) => $f->fecha->format('Y-m-d') === '2026-01-15');
        $this->assertSame('2026-01-20', $primera->fecha_vencimiento->format('Y-m-d'));

        $plantilla->refresh();
        $this->assertSame(3, $plantilla->ocurrencias_generadas);
        $this->assertSame('2026-04-15', $plantilla->proxima_fecha->format('Y-m-d'));

        // Segunda corrida hasta la misma fecha no duplica nada.
        $this->assertSame(0, $generador->generarPlantilla($plantilla, Carbon::parse('2026-03-20'), 'test'));
        $this->assertSame(3, CxpDocumento::where('recurrente_id', $plantilla->id)->count());
    }

    public function test_finaliza_al_alcanzar_el_maximo_de_ocurrencias(): void
    {
        $plantilla = $this->crearPlantilla([
            'fecha_inicio' => '2026-01-15',
            'ocurrencias_max' => 2,
        ]);

        $creadas = app(GeneradorCxpRecurrentes::class)
            ->generarPlantilla($plantilla, Carbon::parse('2026-12-31'), 'test');

        $this->assertSame(2, $creadas);

        $plantilla->refresh();
        $this->assertSame(CxpRecurrente::ESTADO_FINALIZADA, $plantilla->estado);
        $this->assertSame(2, $plantilla->ocurrencias_generadas);
    }

    public function test_plantilla_pausada_no_genera(): void
    {
        $plantilla = $this->crearPlantilla(['fecha_inicio' => '2026-01-15']);
        $plantilla->update(['estado' => CxpRecurrente::ESTADO_PAUSADA]);

        $creadas = app(GeneradorCxpRecurrentes::class)
            ->generarPlantilla($plantilla, Carbon::parse('2026-06-30'), 'test');

        $this->assertSame(0, $creadas);
        $this->assertSame(0, CxpDocumento::where('recurrente_id', $plantilla->id)->count());
    }

    private function crearPlantilla(array $override = []): CxpRecurrente
    {
        $base = $this->payload($override);

        $plantilla = CxpRecurrente::create([
            'compania_id' => $this->compania->id,
            'proveedor_id' => $base['proveedor_id'],
            'nombre' => $base['nombre'],
            'frecuencia' => $base['frecuencia'],
            'fecha_inicio' => $base['fecha_inicio'],
            'fecha_fin' => $base['fecha_fin'] ?? null,
            'dias_credito' => $base['dias_credito'] ?? 0,
            'ocurrencias_max' => $base['ocurrencias_max'] ?? null,
            'proxima_fecha' => $base['fecha_inicio'],
            'estado' => CxpRecurrente::ESTADO_ACTIVA,
            'subtotal' => 500,
            'impuesto' => 35,
            'total' => 535,
        ]);

        foreach ($base['lineas'] as $i => $l) {
            $plantilla->detalle()->create([
                'linea' => $i + 1,
                'descripcion' => $l['descripcion'],
                'cantidad' => $l['cantidad'],
                'precio_unitario' => $l['precio_unitario'],
                'tasa_itbms' => $l['tasa_itbms'],
                'cuenta_id' => $l['cuenta_id'],
            ]);
        }

        return $plantilla;
    }
}
