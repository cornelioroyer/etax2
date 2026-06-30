<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix de seguridad (auditoría 2026-06-29, #1): la gestión de un usuario en una
 * compañía (cambiar rol, abrir/editar permisos, quitar acceso) exige que el
 * usuario objetivo YA pertenezca a la compañía activa. Antes, como
 * UsuarioCompaniaController recibe el usuario por el id de la URL y syncRoles()
 * opera sobre el team activo, un admin de la compañía A podía tomar el id de
 * cualquier usuario del sistema y otorgarle/cambiarle/quitarle acceso sobre A.
 *
 * El guard asegurarUsuarioEnCompania() corta con 403 si el objetivo no tiene
 * rol en la compañía activa. store (alta) queda fuera: ahí el alta es la
 * intención. El guard NO exime al super_admin —la pantalla por compañía solo
 * lista usuarios de la compañía activa; el acceso transversal va por las
 * pantallas globales—, lo que además permite probarlo de forma determinista
 * con el super_admin como actor (bypasa middleware y Gate, así que el único
 * 403 posible proviene del propio guard).
 */
class AislamientoUsuarioCompaniaTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $ajenoB;

    private User $miembroA;

    private Compania $companiaA;

    private Compania $companiaB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);

        $this->superAdmin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        // La compañía 1 es la "sistema" (solo lectura en el Gate): relleno para
        // que A y B no sean la id 1.
        Compania::create(['nombre' => 'SISTEMA', 'activa' => true]);
        $this->companiaA = Compania::create(['nombre' => 'COMPANIA A', 'activa' => true]);
        $this->companiaB = Compania::create(['nombre' => 'COMPANIA B', 'activa' => true]);

        // miembroA: usuario que pertenece a la compañía A.
        $this->enCompania($this->companiaA)->post(route('admin.usuarios-compania.store'), [
            'email' => 'miembro.a@prueba.com',
            'name' => 'Miembro A',
            'password' => 'secret123',
            'rol' => 'usuario',
        ])->assertSessionHasNoErrors();
        $this->miembroA = User::where('email', 'miembro.a@prueba.com')->firstOrFail();

        // ajenoB: usuario que solo existe en la compañía B.
        $this->enCompania($this->companiaB)->post(route('admin.usuarios-compania.store'), [
            'email' => 'ajeno.b@prueba.com',
            'name' => 'Ajeno B',
            'password' => 'secret123',
            'rol' => 'usuario',
        ])->assertSessionHasNoErrors();
        $this->ajenoB = User::where('email', 'ajeno.b@prueba.com')->firstOrFail();
    }

    /** Actúa como super_admin con la compañía indicada como activa. */
    private function enCompania(Compania $compania)
    {
        return $this->actingAs($this->superAdmin)
            ->withSession(['compania_activa_id' => $compania->id]);
    }

    public function test_no_se_puede_cambiar_el_rol_de_un_usuario_ajeno_a_la_compania_activa(): void
    {
        $this->enCompania($this->companiaA)->put(route('admin.usuarios-compania.update', $this->ajenoB), [
            'rol' => 'admin_compania',
        ])->assertForbidden();

        // No se le creó ningún rol en la compañía A.
        $this->assertDatabaseMissing('seg_usuarios_roles', [
            'model_type' => User::class,
            'model_id' => $this->ajenoB->id,
            'compania_id' => $this->companiaA->id,
        ]);
    }

    public function test_no_se_pueden_abrir_los_permisos_de_un_usuario_ajeno(): void
    {
        $this->enCompania($this->companiaA)
            ->get(route('admin.usuarios-compania.permisos.edit', $this->ajenoB))
            ->assertForbidden();
    }

    public function test_no_se_pueden_editar_los_permisos_de_un_usuario_ajeno(): void
    {
        $this->enCompania($this->companiaA)
            ->put(route('admin.usuarios-compania.permisos.update', $this->ajenoB), [])
            ->assertForbidden();

        $this->assertDatabaseMissing('seg_usuarios_permisos', [
            'model_type' => User::class,
            'model_id' => $this->ajenoB->id,
            'compania_id' => $this->companiaA->id,
        ]);
    }

    public function test_no_se_puede_quitar_el_acceso_de_un_usuario_ajeno(): void
    {
        $this->enCompania($this->companiaA)
            ->delete(route('admin.usuarios-compania.destroy', $this->ajenoB))
            ->assertForbidden();

        // El usuario ajeno conserva su rol en la compañía B.
        $this->assertDatabaseHas('seg_usuarios_roles', [
            'model_type' => User::class,
            'model_id' => $this->ajenoB->id,
            'compania_id' => $this->companiaB->id,
        ]);
    }

    public function test_si_se_puede_gestionar_a_un_usuario_de_la_compania_activa(): void
    {
        // Control positivo: el guard deja pasar cuando el usuario SÍ pertenece a
        // la compañía activa.
        $this->enCompania($this->companiaA)->put(route('admin.usuarios-compania.update', $this->miembroA), [
            'rol' => 'admin_compania',
        ])->assertRedirect(route('admin.usuarios-compania.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('seg_usuarios_roles', [
            'model_type' => User::class,
            'model_id' => $this->miembroA->id,
            'compania_id' => $this->companiaA->id,
        ]);
    }
}
