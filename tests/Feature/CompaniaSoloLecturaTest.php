<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Bandera "solo lectura" por compañía (core_companias.solo_lectura), que
 * sustituye al bloqueo hardcodeado de la compañía 1. Una compañía marcada como
 * solo lectura impide toda acción de escritura a los no-super-admin; por defecto
 * NINGUNA lo es y los permisos aplican igual en todas. Solo el super_admin puede
 * fijar la bandera.
 */
class CompaniaSoloLecturaTest extends TestCase
{
    use RefreshDatabase;

    private PermissionRegistrar $reg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reg = app(PermissionRegistrar::class);
    }

    /** Usuario no-admin con permisos directos (nuevo modelo) en una compañía. */
    private function usuarioConPermisos(Compania $compania): User
    {
        $this->reg->setPermissionsTeamId($compania->id);
        Permission::findOrCreate('ventas.facturas.acceder', 'web');
        Permission::findOrCreate('ventas.facturas.insertar', 'web');

        $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);
        $user->givePermissionTo('ventas.facturas.acceder', 'ventas.facturas.insertar');

        $this->reg->forgetCachedPermissions();

        return $user->fresh();
    }

    public function test_compania_solo_lectura_bloquea_la_escritura_a_no_admin(): void
    {
        $compania = Compania::create(['nombre' => 'SOLO LECTURA', 'activa' => true, 'solo_lectura' => true]);
        $u = $this->usuarioConPermisos($compania);

        // Lectura permitida; escritura bloqueada por el Gate aunque tenga el permiso.
        $this->assertTrue($u->can('ventas.facturas.acceder'));
        $this->assertFalse($u->can('ventas.facturas.insertar'));
    }

    public function test_compania_operativa_por_defecto_permite_escritura_segun_permiso(): void
    {
        $compania = Compania::create(['nombre' => 'OPERATIVA', 'activa' => true]);
        $this->assertFalse($compania->fresh()->solo_lectura); // default de BD

        $u = $this->usuarioConPermisos($compania);

        $this->assertTrue($u->can('ventas.facturas.acceder'));
        $this->assertTrue($u->can('ventas.facturas.insertar'));
    }

    public function test_super_admin_escribe_aunque_la_compania_sea_solo_lectura(): void
    {
        $compania = Compania::create(['nombre' => 'SOLO LECTURA', 'activa' => true, 'solo_lectura' => true]);
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->reg->setPermissionsTeamId($compania->id);
        $this->reg->forgetCachedPermissions();

        $this->assertTrue($admin->fresh()->can('ventas.facturas.insertar'));
    }

    public function test_la_cache_de_ids_solo_lectura_se_invalida_al_cambiar_la_bandera(): void
    {
        $compania = Compania::create(['nombre' => 'X', 'activa' => true]);
        $this->assertSame([], Compania::idsSoloLectura());

        $compania->update(['solo_lectura' => true]);
        $this->assertSame([$compania->id], Compania::idsSoloLectura());

        $compania->update(['solo_lectura' => false]);
        $this->assertSame([], Compania::idsSoloLectura());
    }

    public function test_super_admin_puede_marcar_una_compania_como_solo_lectura(): void
    {
        $zona = Zona::create(['description' => 'Zona Prueba']);
        $compania = Compania::create([
            'nombre' => 'EDITAR', 'ruc' => '111-1-1', 'dv' => '11',
            'direccion' => 'Calle 1', 'email' => 'e@e.com', 'correlativo_ss' => 0,
            'fecha_de_apertura' => '2026-01-01', 'activa' => true, 'zonas_id' => $zona->id,
        ]);
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)
            ->withSession(['compania_activa_id' => $compania->id])
            ->put(route('admin.companias.update', $compania), [
                'nombre' => 'EDITAR', 'ruc' => '111-1-1', 'dv' => '11',
                'direccion' => 'Calle 1', 'email' => 'e@e.com', 'correlativo_ss' => 0,
                'fecha_de_apertura' => '2026-01-01', 'activa' => 1, 'zonas_id' => $zona->id,
                'solo_lectura' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($compania->fresh()->solo_lectura);
    }
}
