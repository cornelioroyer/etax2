<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\FelConfiguracion;
use App\Models\User;
use App\Services\FelConfiguracionDefault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FelConfiguracionTest extends TestCase
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

    /** Crea una config FEL con los tokens demo compartidos (estado por defecto). */
    private function configDemo(): FelConfiguracion
    {
        return FelConfiguracion::create(FelConfiguracionDefault::VALORES + [
            'compania_id' => $this->compania->id,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'ambiente' => 'PRUEBAS',
            'punto_facturacion' => '075',
            'codigo_sucursal' => '3075',
            'correlativo' => 0,
            'token_empresa' => '',
            'token_password' => '',
        ], $overrides);
    }

    public function test_no_permite_produccion_con_tokens_demo(): void
    {
        $this->configDemo();

        // Cambiar a PRODUCCIÓN dejando los tokens en blanco (conserva los demo).
        $this->actuar()->put(route('admin.fel.configuracion.update'), $this->payload([
            'ambiente' => 'PRODUCCION',
        ]))->assertSessionHasErrors('ambiente');

        // La config NO cambió de ambiente.
        $this->assertSame('PRUEBAS', FelConfiguracion::firstWhere('compania_id', $this->compania->id)->ambiente);
    }

    public function test_no_permite_produccion_sin_tokens(): void
    {
        // Sin config previa: firstOrNew arranca sin tokens.
        $this->actuar()->put(route('admin.fel.configuracion.update'), $this->payload([
            'ambiente' => 'PRODUCCION',
        ]))->assertSessionHasErrors('ambiente');

        $this->assertNull(FelConfiguracion::firstWhere('compania_id', $this->compania->id));
    }

    public function test_permite_produccion_con_tokens_propios(): void
    {
        $this->configDemo();

        $this->actuar()->put(route('admin.fel.configuracion.update'), $this->payload([
            'ambiente' => 'PRODUCCION',
            'token_empresa' => 'TOKEN-PROD-PROPIO-123',
            'token_password' => 'PASS-PROD-PROPIO-123',
        ]))->assertSessionHasNoErrors();

        $config = FelConfiguracion::firstWhere('compania_id', $this->compania->id);
        $this->assertSame('PRODUCCION', $config->ambiente);
        $this->assertSame('TOKEN-PROD-PROPIO-123', $config->token_empresa);
        $this->assertFalse(app(FelConfiguracionDefault::class)->esDemo($config));
    }

    public function test_pantalla_config_renderiza_badge_y_endpoint(): void
    {
        $this->configDemo();

        $this->actuar()->get(route('admin.fel.configuracion'))
            ->assertOk()
            ->assertSee('Tokens demo compartidos')
            ->assertSee('demoemision.thefactoryhka.com.pa');
    }

    public function test_pruebas_con_tokens_demo_sigue_permitido(): void
    {
        $this->configDemo();

        $this->actuar()->put(route('admin.fel.configuracion.update'), $this->payload([
            'ambiente' => 'PRUEBAS',
            'correlativo' => 5,
        ]))->assertSessionHasNoErrors();

        $config = FelConfiguracion::firstWhere('compania_id', $this->compania->id);
        $this->assertSame('PRUEBAS', $config->ambiente);
        $this->assertSame(5, (int) $config->correlativo);
        $this->assertTrue(app(FelConfiguracionDefault::class)->esDemo($config));
    }
}
