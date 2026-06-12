<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxpDocumento;
use App\Models\TipoContacto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CxpTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private Contacto $proveedor;

    private CuentaContable $cxp;

    private CuentaContable $gasto;

    private CuentaContable $itbmsCredito;

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

        $this->cxp = $crear('20101', 'Cuentas por Pagar Proveedores', 'CREDITO');
        $this->gasto = $crear('60101', 'Gastos Generales', 'DEBITO');
        $this->itbmsCredito = $crear('10113', 'ITBMS Credito Fiscal', 'DEBITO');
        $this->banco = $crear('10102', 'Bancos', 'DEBITO');

        foreach (['CXP' => $this->cxp, 'GASTO_DEFAULT' => $this->gasto, 'ITBMS_CREDITO' => $this->itbmsCredito, 'BANCO_DEFAULT' => $this->banco] as $clave => $cuenta) {
            CuentaDefault::create([
                'compania_id' => $this->compania->id,
                'clave' => $clave,
                'cuenta_id' => $cuenta->id,
            ]);
        }

        $tipoProveedor = TipoContacto::firstOrCreate(['codigo' => 'PROVEEDOR'], ['nombre' => 'Proveedor']);

        $this->proveedor = Contacto::create([
            'compania_id' => $this->compania->id,
            'codigo' => 'PRV-001',
            'nombre' => 'PROVEEDOR PRUEBA',
            'activo' => true,
        ]);
        $this->proveedor->tipos()->attach($tipoProveedor->id);
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function crearFactura(float $precio = 100, int $tasa = 7, string $numero = 'A-1001'): CxpDocumento
    {
        $this->actuar()->post(route('admin.cxp.facturas.store'), [
            'proveedor_id' => $this->proveedor->id,
            'numero' => $numero,
            'fecha' => '2026-06-12',
            'fecha_vencimiento' => '2026-07-12',
            'lineas' => [
                ['descripcion' => 'Compra de prueba', 'cantidad' => 1, 'precio_unitario' => $precio, 'tasa_itbms' => $tasa, 'cuenta_id' => $this->gasto->id],
            ],
        ])->assertSessionHasNoErrors();

        return CxpDocumento::where('tipo_documento', 'FACTURA')->latest('id')->firstOrFail();
    }

    public function test_listado_de_facturas_se_muestra(): void
    {
        $this->actuar()->get(route('admin.cxp.facturas.index'))
            ->assertOk()
            ->assertSee('Facturas por pagar');
    }

    public function test_crear_factura_genera_asiento_posteado(): void
    {
        $factura = $this->crearFactura(100, 7);

        $this->assertSame('A-1001', $factura->numero);
        $this->assertSame('107.00', (string) $factura->total);
        $this->assertSame('PENDIENTE', $factura->estado);

        $asiento = $factura->asiento;
        $this->assertNotNull($asiento);
        $this->assertSame('POSTEADO', $asiento->estado);
        $this->assertSame('CXP', $asiento->origen_modulo);

        $lineas = $asiento->detalle;
        $this->assertCount(3, $lineas);
        $this->assertSame($this->gasto->id, $lineas[0]->cuenta_id);
        $this->assertSame('100.00', (string) $lineas[0]->debito);
        $this->assertSame($this->itbmsCredito->id, $lineas[1]->cuenta_id);
        $this->assertSame('7.00', (string) $lineas[1]->debito);
        $this->assertSame($this->cxp->id, $lineas[2]->cuenta_id);
        $this->assertSame('107.00', (string) $lineas[2]->credito);
    }

    public function test_numero_duplicado_por_proveedor_es_rechazado(): void
    {
        $this->crearFactura(100, 7, 'A-1001');

        $this->actuar()->post(route('admin.cxp.facturas.store'), [
            'proveedor_id' => $this->proveedor->id,
            'numero' => 'A-1001',
            'fecha' => '2026-06-12',
            'lineas' => [
                ['descripcion' => 'Otra', 'cantidad' => 1, 'precio_unitario' => 10, 'tasa_itbms' => 0, 'cuenta_id' => $this->gasto->id],
            ],
        ])->assertSessionHasErrors('numero');

        $this->assertSame(1, CxpDocumento::where('tipo_documento', 'FACTURA')->count());
    }

    public function test_pago_total_marca_factura_pagada(): void
    {
        $factura = $this->crearFactura(100, 7);

        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-13',
            'cuenta_pago_id' => $this->banco->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
        ])->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertSame('PAGADO', $factura->estado);
        $this->assertSame('0.00', (string) $factura->saldo);

        $pago = CxpDocumento::where('tipo_documento', 'PAGO')->firstOrFail();
        $this->assertSame('PG-000001', $pago->numero);

        $asiento = $pago->asiento;
        $this->assertSame('POSTEADO', $asiento->estado);
        $this->assertSame($this->cxp->id, $asiento->detalle[0]->cuenta_id);
        $this->assertSame('107.00', (string) $asiento->detalle[0]->debito);
        $this->assertSame($this->banco->id, $asiento->detalle[1]->cuenta_id);
        $this->assertSame('107.00', (string) $asiento->detalle[1]->credito);
    }

    public function test_pago_parcial_y_anulacion_restauran_saldo(): void
    {
        $factura = $this->crearFactura(100, 7);

        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-13',
            'cuenta_pago_id' => $this->banco->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 40]],
        ])->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertSame('PARCIAL', $factura->estado);
        $this->assertSame('67.00', (string) $factura->saldo);

        $pago = CxpDocumento::where('tipo_documento', 'PAGO')->firstOrFail();

        $this->actuar()->post(route('admin.cxp.pagos.anular', $pago))
            ->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertSame('PENDIENTE', $factura->estado);
        $this->assertSame('107.00', (string) $factura->saldo);
        $this->assertSame('ANULADO', $pago->fresh()->estado);
    }

    public function test_pago_no_puede_exceder_saldo(): void
    {
        $factura = $this->crearFactura(100, 7);

        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-13',
            'cuenta_pago_id' => $this->banco->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 500]],
        ])->assertSessionHasErrors('aplicaciones');

        $this->assertSame(0, CxpDocumento::where('tipo_documento', 'PAGO')->count());
    }

    public function test_anular_factura_sin_pagos(): void
    {
        $factura = $this->crearFactura();

        $this->actuar()->post(route('admin.cxp.facturas.anular', $factura))
            ->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertSame('ANULADO', $factura->estado);
        $this->assertSame('ANULADO', $factura->asiento->estado);
    }

    public function test_antiguedad_de_saldos_se_muestra(): void
    {
        $this->crearFactura(100, 7);

        $this->actuar()->get(route('admin.cxp.antiguedad'))
            ->assertOk()
            ->assertSee('Antigüedad de saldos')
            ->assertSee('PROVEEDOR PRUEBA');
    }
}
