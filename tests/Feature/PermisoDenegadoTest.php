<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Capa de denegación de permisos por usuario y compañía (override negativo):
 *   efectivos = (permisos del rol ∪ directos) − denegados
 */
class PermisoDenegadoTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Compania $compania;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);

        $this->superAdmin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        // La compañía 1 es la "sistema" (solo lectura para no-super-admin en el Gate).
        // Creamos una de relleno para que la de prueba NO sea la id 1.
        Compania::create(['nombre' => 'SISTEMA', 'activa' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);

        // Rol con dos permisos, asignado a un usuario normal en la compañía.
        $this->actuar()->post(route('admin.roles.store'), [
            'name' => 'Operador',
            'permisos' => ['ventas.ver', 'ventas.gestionar'],
        ])->assertSessionHasNoErrors();

        $this->actuar()->post(route('admin.usuarios-compania.store'), [
            'email' => 'op@prueba.com',
            'name' => 'Operador Prueba',
            'password' => 'secret123',
            'rol' => 'operador',
        ])->assertSessionHasNoErrors();

        $this->usuario = User::where('email', 'op@prueba.com')->firstOrFail();
    }

    private function actuar()
    {
        return $this->actingAs($this->superAdmin)
            ->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function permisoId(string $name): int
    {
        return Permission::where('name', $name)->where('guard_name', 'web')->value('id');
    }

    /** El usuario, sin overrides, resuelve los permisos de su rol. */
    private function enContextoCompania(): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->compania->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->usuario->fresh();
    }

    public function test_denegar_un_permiso_del_rol_lo_quita_solo_a_ese_usuario(): void
    {
        // Punto de partida: tiene ambos permisos por el rol.
        $u = $this->enContextoCompania();
        $this->assertTrue($u->can('ventas.ver'));
        $this->assertTrue($u->can('ventas.gestionar'));

        // Denegar 'ventas.gestionar' a este usuario (deja 'ventas.ver' intacto).
        $this->actuar()->put(route('admin.usuarios-compania.permisos.update', $this->usuario), [
            'denegados' => [$this->permisoId('ventas.gestionar')],
        ])->assertRedirect(route('admin.usuarios-compania.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('seg_usuarios_permisos_denegados', [
            'permiso_id' => $this->permisoId('ventas.gestionar'),
            'model_id' => $this->usuario->id,
            'compania_id' => $this->compania->id,
        ]);

        $u = $this->enContextoCompania();
        $this->assertTrue($u->can('ventas.ver'), 'El otro permiso del rol sigue vigente.');
        $this->assertFalse($u->can('ventas.gestionar'), 'El permiso denegado ya no aplica aunque el rol lo otorga.');
    }

    public function test_agregar_extra_y_denegar_del_rol_conviven(): void
    {
        $this->actuar()->put(route('admin.usuarios-compania.permisos.update', $this->usuario), [
            'permisos' => [$this->permisoId('cxc.ver')],          // extra fuera del rol
            'denegados' => [$this->permisoId('ventas.gestionar')],    // quitar uno del rol
        ])->assertSessionHasNoErrors();

        $u = $this->enContextoCompania();
        $this->assertTrue($u->can('ventas.ver'));
        $this->assertFalse($u->can('ventas.gestionar'));
        $this->assertTrue($u->can('cxc.ver'), 'El permiso extra se concede.');
    }

    public function test_un_permiso_marcado_extra_y_denegado_gana_la_denegacion(): void
    {
        // Si por error se manda el mismo id en ambas listas, la denegación manda
        // y no se inserta como directo.
        $id = $this->permisoId('ventas.gestionar');

        $this->actuar()->put(route('admin.usuarios-compania.permisos.update', $this->usuario), [
            'permisos' => [$id],
            'denegados' => [$id],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('seg_usuarios_permisos', [
            'permiso_id' => $id,
            'model_id' => $this->usuario->id,
            'compania_id' => $this->compania->id,
        ]);
        $this->assertDatabaseHas('seg_usuarios_permisos_denegados', [
            'permiso_id' => $id,
            'model_id' => $this->usuario->id,
            'compania_id' => $this->compania->id,
        ]);

        $u = $this->enContextoCompania();
        $this->assertFalse($u->can('ventas.gestionar'));
    }

    public function test_super_admin_no_se_ve_afectado_por_denegaciones(): void
    {
        // Aunque exista una fila de denegación para el super admin, pasa todo.
        DB::table('seg_usuarios_permisos_denegados')->insert([
            'permiso_id' => $this->permisoId('ventas.gestionar'),
            'model_type' => User::class,
            'model_id' => $this->superAdmin->id,
            'compania_id' => $this->compania->id,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->compania->id);
        $this->assertTrue($this->superAdmin->fresh()->can('ventas.gestionar'));
    }

    public function test_quitar_la_denegacion_restaura_el_permiso_del_rol(): void
    {
        $id = $this->permisoId('ventas.gestionar');

        // Denegar y confirmar.
        $this->actuar()->put(route('admin.usuarios-compania.permisos.update', $this->usuario), [
            'denegados' => [$id],
        ])->assertSessionHasNoErrors();
        $this->assertFalse($this->enContextoCompania()->can('ventas.gestionar'));

        // Guardar sin denegados → se restaura el permiso del rol.
        $this->actuar()->put(route('admin.usuarios-compania.permisos.update', $this->usuario), [])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('seg_usuarios_permisos_denegados', [
            'permiso_id' => $id,
            'model_id' => $this->usuario->id,
            'compania_id' => $this->compania->id,
        ]);
        $this->assertTrue($this->enContextoCompania()->can('ventas.gestionar'));
    }
}
