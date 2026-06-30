<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Database\Seeders\PermisosPorOpcionSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Compania $compania;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        // El Gate::before traduce CUALQUIER ability con forma vieja (modulo.ver,
        // .gestionar...) al modelo nuevo por opción × acción leyendo
        // core_menu_items (PermisoLegacy::candidatos); sin estos 2 seeders esa
        // traducción siempre da [] y can() resuelve false aunque el rol tenga el
        // permiso viejo asignado literalmente.
        $this->seed(MenuItemsSeeder::class);
        $this->seed(PermisosPorOpcionSeeder::class);

        $this->superAdmin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);
    }

    private function actuar()
    {
        return $this->actingAs($this->superAdmin)
            ->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function buscarRol(string $name): ?Role
    {
        return Role::query()->whereNull('compania_id')->where('name', $name)->first();
    }

    public function test_super_admin_crea_rol_global_con_permisos(): void
    {
        $this->actuar()->post(route('admin.roles.store'), [
            'name' => 'Cajero General',
            'descripcion' => 'Registra cobros y maneja la caja',
            'permisos' => ['caja.ver', 'caja.gestionar', 'cxc.ver'],
        ])->assertRedirect(route('admin.roles.index'))->assertSessionHasNoErrors();

        $rol = $this->buscarRol('cajero_general');

        $this->assertNotNull($rol, 'El rol debe crearse con el nombre técnico normalizado.');
        $this->assertNull($rol->compania_id, 'El rol debe ser global (compania_id null).');
        $this->assertSame('Registra cobros y maneja la caja', $rol->descripcion);
        $this->assertEqualsCanonicalizing(
            ['caja.ver', 'caja.gestionar', 'cxc.ver'],
            $rol->permissions->pluck('name')->all()
        );
    }

    public function test_no_asigna_permisos_reservados_de_plataforma(): void
    {
        $this->actuar()->post(route('admin.roles.store'), [
            'name' => 'Rol Malicioso',
            'permisos' => ['cxc.ver', 'companias.eliminar', 'zonas.eliminar'],
        ])->assertSessionHasNoErrors();

        $rol = $this->buscarRol('rol_malicioso');

        $permisos = $rol->permissions->pluck('name')->all();
        $this->assertContains('cxc.ver', $permisos);
        $this->assertNotContains('companias.eliminar', $permisos, 'Un permiso reservado no debe poder asignarse.');
        $this->assertNotContains('zonas.eliminar', $permisos);
    }

    public function test_nombre_de_rol_debe_ser_unico(): void
    {
        $this->actuar()->post(route('admin.roles.store'), ['name' => 'Contador'])->assertSessionHasNoErrors();
        $this->actuar()->post(route('admin.roles.store'), ['name' => 'Contador'])->assertSessionHasErrors('name');

        $this->assertSame(1, Role::query()->whereNull('compania_id')->where('name', 'contador')->count());
    }

    public function test_no_se_puede_eliminar_rol_protegido(): void
    {
        $usuario = $this->buscarRol('usuario');

        $this->actuar()->delete(route('admin.roles.destroy', $usuario))->assertSessionHasErrors('rol');

        $this->assertNotNull($this->buscarRol('usuario'), 'El rol base no debe eliminarse.');
    }

    public function test_no_se_puede_eliminar_rol_asignado_a_un_usuario(): void
    {
        $this->actuar()->post(route('admin.roles.store'), ['name' => 'Vendedor'])->assertSessionHasNoErrors();
        $rol = $this->buscarRol('vendedor');

        $otro = User::factory()->create(['is_admin' => false]);
        DB::table('seg_usuarios_roles')->insert([
            'rol_id' => $rol->id,
            'model_type' => User::class,
            'model_id' => $otro->id,
            'compania_id' => $this->compania->id,
        ]);

        $this->actuar()->delete(route('admin.roles.destroy', $rol))->assertSessionHasErrors('rol');

        $this->assertNotNull($this->buscarRol('vendedor'), 'No debe eliminarse un rol en uso.');
    }

    public function test_se_puede_eliminar_rol_no_usado(): void
    {
        $this->actuar()->post(route('admin.roles.store'), ['name' => 'Temporal'])->assertSessionHasNoErrors();
        $rol = $this->buscarRol('temporal');

        $this->actuar()->delete(route('admin.roles.destroy', $rol))->assertSessionHasNoErrors();

        $this->assertNull($this->buscarRol('temporal'));
    }

    public function test_rol_nuevo_es_asignable_a_un_usuario_y_resuelve_permisos(): void
    {
        // 1) super_admin crea el rol. La pantalla de creación ya NO tiene matriz
        // de permisos (solo nombre/descripción, ver RoleController::store); los
        // permisos se asignan aparte, en la pantalla dedicada del rol.
        $this->actuar()->post(route('admin.roles.store'), [
            'name' => 'Solo Lectura',
        ])->assertSessionHasNoErrors();

        $rol = $this->buscarRol('solo_lectura');

        // Permisos POR OPCIÓN (modelo nuevo, fuente de verdad) que dan "ver" en
        // cxc/ventas: cualquier opción de cada módulo sirve, porque el Gate
        // traduce el permiso viejo .ver a un OR sobre TODAS las opciones del
        // módulo (PermisoLegacy::candidatos).
        $verCxc = DB::table('seg_permisos')->where('name', 'like', 'cxc.%.acceder')->value('name');
        $verVentas = DB::table('seg_permisos')->where('name', 'like', 'ventas.%.acceder')->value('name');
        $this->assertNotNull($verCxc, 'Debe existir al menos una opción de cxc en el catálogo.');
        $this->assertNotNull($verVentas, 'Debe existir al menos una opción de ventas en el catálogo.');

        $this->actuar()->put(route('admin.roles.permisos.update', $rol), [
            'permisos' => [$verCxc, $verVentas],
        ])->assertSessionHasNoErrors();

        // 2) se asigna a un usuario nuevo en la compañía activa
        $this->actuar()->post(route('admin.usuarios-compania.store'), [
            'email' => 'lector@prueba.com',
            'name' => 'Lector Prueba',
            'password' => 'secret123',
            'rol' => 'solo_lectura',
        ])->assertSessionHasNoErrors();

        $lector = User::where('email', 'lector@prueba.com')->firstOrFail();

        $asignado = DB::table('seg_usuarios_roles')
            ->join('seg_roles', 'seg_roles.id', '=', 'seg_usuarios_roles.rol_id')
            ->where('seg_usuarios_roles.model_id', $lector->id)
            ->where('seg_usuarios_roles.compania_id', $this->compania->id)
            ->value('seg_roles.name');
        $this->assertSame('solo_lectura', $asignado);

        // 3) en el contexto de esa compañía, el usuario resuelve los permisos del rol
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->compania->id);
        $lector = $lector->fresh();
        $this->assertTrue($lector->can('cxc.ver'));
        $this->assertTrue($lector->can('ventas.ver'));
        $this->assertFalse($lector->can('cxc.gestionar'));
    }

    public function test_usuario_no_super_admin_no_accede_al_catalogo_de_roles(): void
    {
        $normal = User::factory()->create(['is_admin' => false]);

        $this->actingAs($normal)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    }
}
