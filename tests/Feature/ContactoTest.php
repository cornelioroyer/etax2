<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\Contacto;
use App\Models\TipoContacto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private int $tipoClienteId;

    private int $tipoProveedorId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);

        $this->tipoClienteId = TipoContacto::firstOrCreate(['codigo' => 'CLIENTE'], ['nombre' => 'Cliente'])->id;
        $this->tipoProveedorId = TipoContacto::firstOrCreate(['codigo' => 'PROVEEDOR'], ['nombre' => 'Proveedor'])->id;
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function datosBase(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Contacto de prueba',
            'tipo_persona' => 'JURIDICA',
            'activo' => '1',
        ], $extra);
    }

    public function test_cliente_no_requiere_campos_dgi_de_proveedor(): void
    {
        $this->actuar()->post(route('admin.contactos.store'), $this->datosBase([
            'tipos' => [$this->tipoClienteId],
        ]))->assertSessionHasNoErrors();

        $contacto = Contacto::where('nombre', 'Contacto de prueba')->firstOrFail();
        $this->assertNull($contacto->concepto);
        $this->assertNull($contacto->tipo_compra);
        $this->assertNull($contacto->otros_costos_gastos_id);
        $this->assertNull($contacto->cuenta_gasto_id);
    }

    public function test_proveedor_exige_campos_dgi(): void
    {
        $this->actuar()->post(route('admin.contactos.store'), $this->datosBase([
            'tipos' => [$this->tipoProveedorId],
        ]))->assertSessionHasErrors(['concepto', 'tipo_compra', 'otros_costos_gastos_id']);

        $this->assertSame(0, Contacto::count());
    }

    public function test_proveedor_con_campos_dgi_los_guarda(): void
    {
        $this->actuar()->post(route('admin.contactos.store'), $this->datosBase([
            'tipos' => [$this->tipoProveedorId],
            'concepto' => '3',
            'tipo_compra' => '2',
            'otros_costos_gastos_id' => 60,
        ]))->assertSessionHasNoErrors();

        $contacto = Contacto::where('nombre', 'Contacto de prueba')->firstOrFail();
        $this->assertSame('3', $contacto->concepto);
        $this->assertSame('2', $contacto->tipo_compra);
        $this->assertSame(60, $contacto->otros_costos_gastos_id);
    }

    public function test_quitar_tipo_proveedor_limpia_campos_dgi_al_editar(): void
    {
        $contacto = Contacto::create([
            'compania_id' => $this->compania->id,
            'nombre' => 'Mixto SA',
            'tipo_persona' => 'JURIDICA',
            'activo' => true,
            'concepto' => '4',
            'tipo_compra' => '2',
            'otros_costos_gastos_id' => 60,
        ]);
        $contacto->tipos()->sync([$this->tipoClienteId, $this->tipoProveedorId]);

        // El <select> oculto seguiría mandando su valor aunque el usuario haya
        // desmarcado "Proveedor" en el formulario; el server debe limpiarlo igual.
        $this->actuar()->put(route('admin.contactos.update', $contacto), $this->datosBase([
            'tipos' => [$this->tipoClienteId],
            'concepto' => '4',
            'tipo_compra' => '2',
            'otros_costos_gastos_id' => 60,
        ]))->assertSessionHasNoErrors();

        $contacto->refresh();
        $this->assertNull($contacto->concepto);
        $this->assertNull($contacto->tipo_compra);
        $this->assertNull($contacto->otros_costos_gastos_id);
    }
}
