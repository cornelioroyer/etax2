<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxcDocumento;
use App\Models\TipoContacto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CxcTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private Contacto $cliente;

    private CuentaContable $cxc;

    private CuentaContable $ventas;

    private CuentaContable $itbms;

    private CuentaContable $banco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);

        $crear = fn (string $codigo, string $nombre, string $naturaleza) => CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'nivel' => 3,
            'naturaleza' => $naturaleza,
            'permite_movimiento' => true,
            'conciliable' => false,
            'activa' => true,
        ]);

        $this->cxc = $crear('10103', 'Cuentas por Cobrar Clientes', 'DEBITO');
        $this->ventas = $crear('40101', 'Ventas', 'CREDITO');
        $this->itbms = $crear('20107', 'ITBMS por Pagar', 'CREDITO');
        $this->banco = $crear('10102', 'Bancos', 'DEBITO');

        foreach (['CXC' => $this->cxc, 'VENTAS' => $this->ventas, 'ITBMS_POR_PAGAR' => $this->itbms, 'BANCO_DEFAULT' => $this->banco] as $clave => $cuenta) {
            CuentaDefault::create([
                'compania_id' => $this->compania->id,
                'clave' => $clave,
                'cuenta_id' => $cuenta->id,
            ]);
        }

        $tipoCliente = TipoContacto::firstOrCreate(['codigo' => 'CLIENTE'], ['nombre' => 'Cliente']);

        $this->cliente = Contacto::create([
            'compania_id' => $this->compania->id,
            'codigo' => 'CLI-001',
            'nombre' => 'CLIENTE PRUEBA',
            'activo' => true,
        ]);
        $this->cliente->tipos()->attach($tipoCliente->id);
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function crearFactura(float $precio = 100, int $tasa = 7): CxcDocumento
    {
        $this->actuar()->post(route('admin.cxc.facturas.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-12',
            'fecha_vencimiento' => '2026-07-12',
            'lineas' => [
                ['descripcion' => 'Servicio de prueba', 'cantidad' => 1, 'precio_unitario' => $precio, 'tasa_itbms' => $tasa, 'cuenta_id' => $this->ventas->id],
            ],
        ])->assertSessionHasNoErrors();

        return CxcDocumento::where('tipo_documento', 'FACTURA')->latest('id')->firstOrFail();
    }

    public function test_listado_de_facturas_se_muestra(): void
    {
        $this->actuar()->get(route('admin.cxc.facturas.index'))
            ->assertOk()
            ->assertSee('Facturas por cobrar');
    }

    public function test_crear_factura_genera_asiento_posteado(): void
    {
        $factura = $this->crearFactura(100, 7);

        $this->assertSame('FC-000001', $factura->numero);
        $this->assertSame('100.00', (string) $factura->subtotal);
        $this->assertSame('7.00', (string) $factura->impuesto);
        $this->assertSame('107.00', (string) $factura->total);
        $this->assertSame('107.00', (string) $factura->saldo);
        $this->assertSame('PENDIENTE', $factura->estado);

        $asiento = $factura->asiento;
        $this->assertNotNull($asiento);
        $this->assertSame('POSTEADO', $asiento->estado);
        $this->assertSame('CXC', $asiento->origen_modulo);
        $this->assertSame('107.00', (string) $asiento->total_debito);

        $lineas = $asiento->detalle;
        $this->assertCount(3, $lineas);
        $this->assertSame($this->cxc->id, $lineas[0]->cuenta_id);
        $this->assertSame('107.00', (string) $lineas[0]->debito);
        $this->assertSame($this->ventas->id, $lineas[1]->cuenta_id);
        $this->assertSame('100.00', (string) $lineas[1]->credito);
        $this->assertSame($this->itbms->id, $lineas[2]->cuenta_id);
        $this->assertSame('7.00', (string) $lineas[2]->credito);
    }

    public function test_factura_exenta_no_genera_linea_itbms(): void
    {
        $factura = $this->crearFactura(50, 0);

        $this->assertSame('0.00', (string) $factura->impuesto);
        $this->assertCount(2, $factura->asiento->detalle);
    }

    public function test_cobro_total_marca_factura_pagada(): void
    {
        $factura = $this->crearFactura(100, 7);

        $this->actuar()->post(route('admin.cxc.cobros.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-13',
            'cuenta_cobro_id' => $this->banco->id,
            'aplicaciones' => [
                ['documento_id' => $factura->id, 'monto' => 107],
            ],
        ])->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertSame('PAGADO', $factura->estado);
        $this->assertSame('0.00', (string) $factura->saldo);

        $cobro = CxcDocumento::where('tipo_documento', 'PAGO')->firstOrFail();
        $this->assertSame('RC-000001', $cobro->numero);
        $this->assertSame('107.00', (string) $cobro->total);

        $asiento = $cobro->asiento;
        $this->assertSame('POSTEADO', $asiento->estado);
        $this->assertSame($this->banco->id, $asiento->detalle[0]->cuenta_id);
        $this->assertSame('107.00', (string) $asiento->detalle[0]->debito);
        $this->assertSame($this->cxc->id, $asiento->detalle[1]->cuenta_id);
        $this->assertSame('107.00', (string) $asiento->detalle[1]->credito);
    }

    public function test_cobro_parcial_marca_factura_parcial(): void
    {
        $factura = $this->crearFactura(100, 7);

        $this->actuar()->post(route('admin.cxc.cobros.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-13',
            'cuenta_cobro_id' => $this->banco->id,
            'aplicaciones' => [
                ['documento_id' => $factura->id, 'monto' => 50],
            ],
        ])->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertSame('PARCIAL', $factura->estado);
        $this->assertSame('57.00', (string) $factura->saldo);
    }

    public function test_cobro_no_puede_exceder_saldo(): void
    {
        $factura = $this->crearFactura(100, 7);

        $this->actuar()->post(route('admin.cxc.cobros.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-13',
            'cuenta_cobro_id' => $this->banco->id,
            'aplicaciones' => [
                ['documento_id' => $factura->id, 'monto' => 200],
            ],
        ])->assertSessionHasErrors('aplicaciones');

        $this->assertSame('PENDIENTE', $factura->fresh()->estado);
        $this->assertSame(0, CxcDocumento::where('tipo_documento', 'PAGO')->count());
    }

    public function test_anular_cobro_restaura_saldo(): void
    {
        $factura = $this->crearFactura(100, 7);

        $this->actuar()->post(route('admin.cxc.cobros.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-13',
            'cuenta_cobro_id' => $this->banco->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
        ]);

        $cobro = CxcDocumento::where('tipo_documento', 'PAGO')->firstOrFail();

        $this->actuar()->post(route('admin.cxc.cobros.anular', $cobro))
            ->assertSessionHasNoErrors();

        $this->assertSame('ANULADO', $cobro->fresh()->estado);
        $this->assertSame('ANULADO', $cobro->fresh()->asiento->estado);

        $factura->refresh();
        $this->assertSame('PENDIENTE', $factura->estado);
        $this->assertSame('107.00', (string) $factura->saldo);
    }

    public function test_anular_factura_sin_cobros(): void
    {
        $factura = $this->crearFactura();

        $this->actuar()->post(route('admin.cxc.facturas.anular', $factura))
            ->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertSame('ANULADO', $factura->estado);
        $this->assertSame('0.00', (string) $factura->saldo);
        $this->assertSame('ANULADO', $factura->asiento->estado);
    }

    public function test_no_se_puede_anular_factura_con_cobros(): void
    {
        $factura = $this->crearFactura(100, 7);

        $this->actuar()->post(route('admin.cxc.cobros.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-13',
            'cuenta_cobro_id' => $this->banco->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
        ]);

        $this->actuar()->post(route('admin.cxc.facturas.anular', $factura))
            ->assertSessionHasErrors('documento');

        $this->assertSame('PAGADO', $factura->fresh()->estado);
    }

    public function test_sin_cuenta_default_cxc_es_rechazado(): void
    {
        CuentaDefault::where('compania_id', $this->compania->id)->where('clave', 'CXC')->delete();

        $this->actuar()->post(route('admin.cxc.facturas.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-12',
            'lineas' => [
                ['descripcion' => 'X', 'cantidad' => 1, 'precio_unitario' => 10, 'tasa_itbms' => 0, 'cuenta_id' => $this->ventas->id],
            ],
        ])->assertSessionHasErrors('cliente_id');

        $this->assertSame(0, CxcDocumento::count());
        $this->assertSame(0, Asiento::count());
    }

    public function test_antiguedad_de_saldos_se_muestra(): void
    {
        $this->crearFactura(100, 7);

        $this->actuar()->get(route('admin.cxc.antiguedad'))
            ->assertOk()
            ->assertSee('Antigüedad de saldos')
            ->assertSee('CLIENTE PRUEBA');
    }
}
