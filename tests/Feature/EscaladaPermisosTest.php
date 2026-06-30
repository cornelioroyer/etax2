<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Database\Seeders\PermisosPorOpcionSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fix de seguridad (auditoría 2026-06-29 #2): un otorgante (admin_compania) no
 * puede CONCEDER a otro usuario un permiso que él mismo no posee (escalada
 * intra-tenant). actualizarPermisos acota los permisos extra a la intersección
 * con los permisos efectivos del otorgante. El super_admin queda exento.
 *
 * Usa el stack completo de permisos (roles + menú + modelo por opción) porque
 * el enforcement real resuelve los permisos por opción y traduce los viejos
 * leyendo core_menu_items.
 */
class EscaladaPermisosTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $otorgante;

    private User $objetivo;

    private Compania $companiaA;

    private int $permiteId;   // permiso por opción que el otorgante SÍ posee

    private int $noPermiteId; // permiso por opción DENEGADO al otorgante

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);
        $this->seed(MenuItemsSeeder::class);
        $this->seed(PermisosPorOpcionSeeder::class);

        $this->superAdmin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        Compania::create(['nombre' => 'SISTEMA', 'activa' => true]); // id 1 (sistema)
        $this->companiaA = Compania::create(['nombre' => 'COMPANIA A', 'activa' => true]);

        // Otorgante: admin de la compañía A.
        $this->comoSuperAdmin()->post(route('admin.usuarios-compania.store'), [
            'email' => 'otorgante@prueba.com', 'name' => 'Otorgante',
            'password' => 'secret123', 'rol' => 'admin_compania',
        ])->assertSessionHasNoErrors();
        $this->otorgante = User::where('email', 'otorgante@prueba.com')->firstOrFail();

        // Objetivo: usuario común de la compañía A.
        $this->comoSuperAdmin()->post(route('admin.usuarios-compania.store'), [
            'email' => 'objetivo@prueba.com', 'name' => 'Objetivo',
            'password' => 'secret123', 'rol' => 'usuario',
        ])->assertSessionHasNoErrors();
        $this->objetivo = User::where('email', 'objetivo@prueba.com')->firstOrFail();

        // Dos permisos POR OPCIÓN que el rol admin_compania posee, de un módulo
        // distinto a usuarios_compania (para no afectar el middleware al denegar).
        $candidatos = DB::table('seg_roles_permisos')
            ->join('seg_roles', 'seg_roles.id', '=', 'seg_roles_permisos.rol_id')
            ->join('seg_permisos', 'seg_permisos.id', '=', 'seg_roles_permisos.permiso_id')
            ->where('seg_roles.name', 'admin_compania')
            ->whereNull('seg_roles.compania_id')
            ->where('seg_permisos.name', 'like', 'ventas.%.insertar')
            ->orderBy('seg_permisos.name')
            ->pluck('seg_permisos.id', 'seg_permisos.name');

        $this->assertGreaterThanOrEqual(2, $candidatos->count(), 'Se necesitan 2 permisos por opción de ventas.');
        [$this->noPermiteId, $this->permiteId] = array_slice($candidatos->values()->all(), 0, 2);

        // Se DENIEGA noPermiteId al otorgante en la compañía A → su can() da false.
        DB::table('seg_usuarios_permisos_denegados')->insert([
            'permiso_id' => $this->noPermiteId,
            'model_type' => User::class,
            'model_id' => $this->otorgante->id,
            'compania_id' => $this->companiaA->id,
        ]);
    }

    private function comoSuperAdmin()
    {
        return $this->actingAs($this->superAdmin)
            ->withSession(['compania_activa_id' => $this->companiaA->id]);
    }

    private function comoOtorgante()
    {
        return $this->actingAs($this->otorgante)
            ->withSession(['compania_activa_id' => $this->companiaA->id]);
    }

    public function test_otorgante_no_puede_conceder_un_permiso_que_no_posee(): void
    {
        $this->comoOtorgante()->put(route('admin.usuarios-compania.permisos.update', $this->objetivo), [
            'permisos' => [$this->permiteId, $this->noPermiteId],
        ])->assertRedirect(route('admin.usuarios-compania.index'))->assertSessionHasNoErrors();

        // El permiso que el otorgante SÍ posee se concede.
        $this->assertDatabaseHas('seg_usuarios_permisos', [
            'permiso_id' => $this->permiteId,
            'model_id' => $this->objetivo->id,
            'compania_id' => $this->companiaA->id,
        ]);

        // El permiso que el otorgante NO posee (denegado) se descarta.
        $this->assertDatabaseMissing('seg_usuarios_permisos', [
            'permiso_id' => $this->noPermiteId,
            'model_id' => $this->objetivo->id,
            'compania_id' => $this->companiaA->id,
        ]);
    }

    public function test_super_admin_si_puede_conceder_cualquier_permiso(): void
    {
        // Control: el super_admin está exento del filtro de intersección.
        $this->comoSuperAdmin()->put(route('admin.usuarios-compania.permisos.update', $this->objetivo), [
            'permisos' => [$this->noPermiteId],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('seg_usuarios_permisos', [
            'permiso_id' => $this->noPermiteId,
            'model_id' => $this->objetivo->id,
            'compania_id' => $this->companiaA->id,
        ]);
    }
}
