<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxcDocumento;
use App\Models\TaxImpuesto;
use App\Models\TipoContacto;
use App\Models\User;
use App\Models\VentaCotizacion;
use App\Models\VentaFactura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VentaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private Contacto $cliente;

    private CuentaContable $cxc;

    private CuentaContable $ventas;

    private CuentaContable $itbms;

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

        foreach (['CXC' => $this->cxc, 'VENTAS' => $this->ventas, 'ITBMS_POR_PAGAR' => $this->itbms] as $clave => $cuenta) {
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

    private function impuestoId(string $codigo): int
    {
        return (int) TaxImpuesto::where('codigo', $codigo)->value('id');
    }

    private function crearCotizacion(float $precio = 100, string $tasaCodigo = 'ITBMS_7'): VentaCotizacion
    {
        $this->actuar()->post(route('admin.ventas.cotizaciones.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-12',
            'fecha_validez' => '2026-07-12',
            'lineas' => [
                ['descripcion' => 'Servicio de prueba', 'cantidad' => 1, 'precio_unitario' => $precio, 'impuesto_id' => $this->impuestoId($tasaCodigo)],
            ],
        ])->assertSessionHasNoErrors();

        return VentaCotizacion::latest('id')->firstOrFail();
    }

    private function facturar(VentaCotizacion $cotizacion): VentaFactura
    {
        $this->actuar()->post(route('admin.ventas.cotizaciones.facturar', $cotizacion), [
            'fecha' => '2026-06-13',
            'fecha_vencimiento' => '2026-07-13',
        ])->assertSessionHasNoErrors();

        return VentaFactura::where('cotizacion_id', $cotizacion->id)->firstOrFail();
    }

    public function test_listado_de_cotizaciones_se_muestra(): void
    {
        $this->actuar()->get(route('admin.ventas.cotizaciones.index'))
            ->assertOk()
            ->assertSee('Cotizaciones');
    }

    public function test_crear_cotizacion_calcula_totales_y_no_postea_asiento(): void
    {
        $cot = $this->crearCotizacion(100, 'ITBMS_7');

        $this->assertSame('COT-000001', $cot->numero);
        $this->assertSame('100.00', (string) $cot->subtotal);
        $this->assertSame('7.00', (string) $cot->itbms);
        $this->assertSame('107.00', (string) $cot->total);
        $this->assertSame('BORRADOR', $cot->estado);

        // Una cotización es un borrador: no debe existir ningún asiento aún.
        $this->assertSame(0, Asiento::count());
        $this->assertCount(1, $cot->detalle);
        $this->assertSame('107.00', (string) $cot->detalle[0]->total_linea);
    }

    public function test_facturar_cotizacion_genera_factura_cxc_y_asiento(): void
    {
        $cot = $this->crearCotizacion(100, 'ITBMS_7');
        $factura = $this->facturar($cot);

        // Cotización pasa a FACTURADA
        $this->assertSame('FACTURADA', $cot->fresh()->estado);

        // Factura
        $this->assertSame('FC-000001', $factura->numero);
        $this->assertSame('107.00', (string) $factura->total);
        $this->assertSame('107.00', (string) $factura->saldo);
        $this->assertSame('EMITIDA', $factura->estado);

        // Documento CxC asociado (para cobros/antigüedad)
        $cxc = CxcDocumento::where('tipo_documento', 'FACTURA')->firstOrFail();
        $this->assertSame('FC-000001', $cxc->numero);
        $this->assertSame('107.00', (string) $cxc->saldo);
        $this->assertSame($cxc->id, $factura->cxc_documento_id);

        // Asiento posteado y cuadrado
        $asiento = $factura->asiento;
        $this->assertNotNull($asiento);
        $this->assertSame('POSTEADO', $asiento->estado);
        $this->assertSame('CXC', $asiento->origen_modulo);
        $this->assertSame('107.00', (string) $asiento->total_debito);
        $this->assertSame('107.00', (string) $asiento->total_credito);

        $lineas = $asiento->detalle;
        $this->assertCount(3, $lineas);
        $this->assertSame($this->cxc->id, $lineas[0]->cuenta_id);
        $this->assertSame('107.00', (string) $lineas[0]->debito);
        $this->assertSame($this->ventas->id, $lineas[1]->cuenta_id);
        $this->assertSame('100.00', (string) $lineas[1]->credito);
        $this->assertSame($this->itbms->id, $lineas[2]->cuenta_id);
        $this->assertSame('7.00', (string) $lineas[2]->credito);
    }

    public function test_cotizacion_exenta_no_genera_linea_itbms(): void
    {
        $cot = $this->crearCotizacion(50, 'ITBMS_0');
        $this->assertSame('0.00', (string) $cot->itbms);

        $factura = $this->facturar($cot);
        $this->assertSame('50.00', (string) $factura->total);
        $this->assertCount(2, $factura->asiento->detalle);
    }

    public function test_no_se_puede_facturar_dos_veces(): void
    {
        $cot = $this->crearCotizacion(100, 'ITBMS_7');
        $this->facturar($cot);

        $this->actuar()->post(route('admin.ventas.cotizaciones.facturar', $cot), [
            'fecha' => '2026-06-14',
        ])->assertSessionHasErrors('cotizacion');

        $this->assertSame(1, VentaFactura::count());
    }

    public function test_transicion_de_estado_borrador_a_enviada(): void
    {
        $cot = $this->crearCotizacion(100, 'ITBMS_7');

        $this->actuar()->post(route('admin.ventas.cotizaciones.estado', $cot), [
            'estado' => 'ENVIADA',
        ])->assertSessionHasNoErrors();

        $this->assertSame('ENVIADA', $cot->fresh()->estado);
    }

    public function test_transicion_invalida_es_rechazada(): void
    {
        $cot = $this->crearCotizacion(100, 'ITBMS_7');

        // Desde BORRADOR no se puede saltar directo a ACEPTADA
        $this->actuar()->post(route('admin.ventas.cotizaciones.estado', $cot), [
            'estado' => 'ACEPTADA',
        ])->assertSessionHasErrors('estado');

        $this->assertSame('BORRADOR', $cot->fresh()->estado);
    }

    public function test_anular_cotizacion_no_facturada(): void
    {
        $cot = $this->crearCotizacion(100, 'ITBMS_7');

        $this->actuar()->post(route('admin.ventas.cotizaciones.anular', $cot))
            ->assertSessionHasNoErrors();

        $this->assertSame('ANULADA', $cot->fresh()->estado);
    }

    public function test_no_se_puede_anular_cotizacion_facturada(): void
    {
        $cot = $this->crearCotizacion(100, 'ITBMS_7');
        $this->facturar($cot);

        $this->actuar()->post(route('admin.ventas.cotizaciones.anular', $cot))
            ->assertSessionHasErrors('cotizacion');

        $this->assertSame('FACTURADA', $cot->fresh()->estado);
    }

    public function test_anular_factura_revierte_asiento_cxc_y_cotizacion(): void
    {
        $cot = $this->crearCotizacion(100, 'ITBMS_7');
        $factura = $this->facturar($cot);

        $this->actuar()->post(route('admin.ventas.facturas.anular', $factura))
            ->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertSame('ANULADA', $factura->estado);
        $this->assertSame('0.00', (string) $factura->saldo);
        $this->assertSame('ANULADO', $factura->asiento->estado);
        $this->assertSame('ANULADO', $factura->cxcDocumento->estado);

        // La cotización vuelve a ACEPTADA para poder re-facturar
        $this->assertSame('ACEPTADA', $cot->fresh()->estado);
    }

    public function test_facturar_sin_cuenta_default_cxc_es_rechazado(): void
    {
        CuentaDefault::where('compania_id', $this->compania->id)->where('clave', 'CXC')->delete();

        $cot = $this->crearCotizacion(100, 'ITBMS_7');

        $this->actuar()->post(route('admin.ventas.cotizaciones.facturar', $cot), [
            'fecha' => '2026-06-13',
        ])->assertSessionHasErrors('cotizacion');

        $this->assertSame(0, VentaFactura::count());
        $this->assertSame(0, Asiento::count());
        $this->assertSame('BORRADOR', $cot->fresh()->estado);
    }
}
