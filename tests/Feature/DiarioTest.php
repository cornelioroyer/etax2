<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\Diario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'codigo' => 'VTA',
            'nombre' => 'Diario de Ventas',
            'tipo_diario' => 'VENTAS',
            'requiere_aprobacion' => false,
        ], $extra);
    }

    public function test_listado_se_muestra(): void
    {
        $this->actuar()->get(route('admin.diarios.index'))->assertOk();
    }

    public function test_crear_diario(): void
    {
        $this->actuar()->post(route('admin.diarios.store'), $this->datos())
            ->assertSessionHasNoErrors();

        $diario = Diario::where('codigo', 'VTA')->firstOrFail();
        $this->assertSame('Diario de Ventas', $diario->nombre);
        $this->assertSame('VENTAS', $diario->tipo_diario);
        $this->assertTrue((bool) $diario->activo);
    }

    public function test_codigo_en_minusculas_es_rechazado(): void
    {
        // El código exige mayúsculas (regex ^[A-Z0-9_]+$).
        $this->actuar()->post(route('admin.diarios.store'), $this->datos(['codigo' => 'caja']))
            ->assertSessionHasErrors('codigo');

        $this->assertSame(0, Diario::count());
    }

    public function test_codigo_no_se_repite(): void
    {
        $this->actuar()->post(route('admin.diarios.store'), $this->datos())->assertSessionHasNoErrors();

        $this->actuar()->post(route('admin.diarios.store'), $this->datos(['nombre' => 'Otro']))
            ->assertSessionHasErrors('codigo');

        $this->assertSame(1, Diario::where('codigo', 'VTA')->count());
    }

    public function test_codigo_con_caracteres_invalidos_es_rechazado(): void
    {
        $this->actuar()->post(route('admin.diarios.store'), $this->datos(['codigo' => 'a-b c']))
            ->assertSessionHasErrors('codigo');

        $this->assertSame(0, Diario::count());
    }

    public function test_tipo_invalido_es_rechazado(): void
    {
        $this->actuar()->post(route('admin.diarios.store'), $this->datos(['tipo_diario' => 'XXX']))
            ->assertSessionHasErrors('tipo_diario');
    }

    public function test_actualizar_diario(): void
    {
        $this->actuar()->post(route('admin.diarios.store'), $this->datos())->assertSessionHasNoErrors();
        $diario = Diario::where('codigo', 'VTA')->firstOrFail();

        $this->actuar()->put(route('admin.diarios.update', $diario), [
            'nombre' => 'Ventas y Servicios',
            'tipo_diario' => 'GENERAL',
            'requiere_aprobacion' => true,
        ])->assertSessionHasNoErrors();

        $diario->refresh();
        $this->assertSame('Ventas y Servicios', $diario->nombre);
        $this->assertSame('GENERAL', $diario->tipo_diario);
        $this->assertTrue((bool) $diario->requiere_aprobacion);
    }

    public function test_toggle_activo(): void
    {
        $this->actuar()->post(route('admin.diarios.store'), $this->datos())->assertSessionHasNoErrors();
        $diario = Diario::where('codigo', 'VTA')->firstOrFail();

        $this->actuar()->post(route('admin.diarios.toggle', $diario))->assertSessionHasNoErrors();
        $this->assertFalse((bool) $diario->fresh()->activo);
    }
}
