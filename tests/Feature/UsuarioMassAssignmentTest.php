<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix de seguridad (auditoría 2026-06-29 #3): is_admin (super_admin) e
 * is_active NO deben ser asignables en masa. Antes estaban en $fillable y
 * ProfileController::update hace fill($request->validated()) → cualquier
 * request futuro que validara esos campos abriría una escalada a super_admin.
 * Ahora están guardados; los flujos legítimos los fijan con forceFill().
 */
class UsuarioMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_admin_e_is_active_no_son_fillable(): void
    {
        $user = new User();

        $this->assertFalse($user->isFillable('is_admin'));
        $this->assertFalse($user->isFillable('is_active'));
        // Los campos legítimos siguen siendo asignables en masa.
        $this->assertTrue($user->isFillable('name'));
        $this->assertTrue($user->isFillable('email'));
    }

    public function test_fill_ignora_is_admin_e_is_active(): void
    {
        $user = (new User())->forceFill(['is_admin' => false, 'is_active' => true]);

        // Intento de elevar por mass-assignment: debe ignorarse.
        $user->fill([
            'name' => 'Nombre',
            'email' => 'nombre@prueba.com',
            'is_admin' => true,
            'is_active' => false,
        ]);

        $this->assertSame('Nombre', $user->name);
        $this->assertFalse($user->is_admin, 'is_admin no debe poder elevarse por fill().');
        $this->assertTrue($user->is_active, 'is_active no debe poder cambiarse por fill().');
    }

    public function test_actualizar_perfil_no_puede_volver_super_admin(): void
    {
        // Vector real: un usuario común edita su perfil e intenta colar is_admin.
        $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => 'Nuevo Nombre',
                'email' => 'nuevo@prueba.com',
                'is_admin' => 1,
                'is_active' => 0,
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Nuevo Nombre', $user->name);
        $this->assertFalse($user->is_admin, 'El perfil propio nunca debe poder otorgarse super_admin.');
        $this->assertTrue($user->is_active, 'El perfil propio nunca debe poder cambiar is_active.');
    }
}
