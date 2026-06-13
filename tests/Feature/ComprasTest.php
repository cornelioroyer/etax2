<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\Compania;
use App\Models\CompraOrden;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxpDocumento;
use App\Models\TaxImpuesto;
use App\Models\TipoContacto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComprasTest extends TestCase
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

    private function impuestoId(string $codigo): int
    {
        return (int) TaxImpuesto::where('codigo', $codigo)->value('id');
    }

    private function crearOrden(float $precio = 100, float $cantidad = 1, string $tasa = 'ITBMS_7'): CompraOrden
    {
        $this->actuar()->post(route('admin.compras.ordenes.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-12',
            'lineas' => [
                ['descripcion' => 'Mercancia de prueba', 'cantidad' => $cantidad, 'precio_unitario' => $precio, 'impuesto_id' => $this->impuestoId($tasa)],
            ],
        ])->assertSessionHasNoErrors();

        return CompraOrden::latest('id')->firstOrFail();
    }

    private function aprobar(CompraOrden $orden): void
    {
        $this->actuar()->post(route('admin.compras.ordenes.aprobar', $orden))->assertSessionHasNoErrors();
    }

    private function recibir(CompraOrden $orden, float $cantidad): \Illuminate\Testing\TestResponse
    {
        $linea = $orden->detalle()->first();

        return $this->actuar()->post(route('admin.compras.ordenes.recepciones.store', $orden), [
            'fecha' => '2026-06-13',
            'lineas' => [
                ['orden_detalle_id' => $linea->id, 'cantidad' => $cantidad],
            ],
        ]);
    }

    private function facturar(CompraOrden $orden, string $numero = 'FACT-001'): \Illuminate\Testing\TestResponse
    {
        return $this->actuar()->post(route('admin.compras.ordenes.facturar', $orden), [
            'numero' => $numero,
            'fecha' => '2026-06-14',
            'fecha_vencimiento' => '2026-07-14',
        ]);
    }

    public function test_listado_se_muestra(): void
    {
        $this->actuar()->get(route('admin.compras.ordenes.index'))
            ->assertOk()
            ->assertSee('Órdenes de compra');
    }

    public function test_crear_orden_calcula_totales_y_no_postea_asiento(): void
    {
        $orden = $this->crearOrden(100, 2, 'ITBMS_7');

        $this->assertSame('OC-000001', $orden->numero);
        $this->assertSame('200.00', (string) $orden->subtotal);
        $this->assertSame('14.00', (string) $orden->itbms);
        $this->assertSame('214.00', (string) $orden->total);
        $this->assertSame('BORRADOR', $orden->estado);
        $this->assertSame(0, Asiento::count());
        $this->assertCount(1, $orden->detalle);
    }

    public function test_aprobar_orden(): void
    {
        $orden = $this->crearOrden();
        $this->aprobar($orden);
        $this->assertSame('APROBADA', $orden->fresh()->estado);
    }

    public function test_no_se_puede_facturar_en_borrador(): void
    {
        $orden = $this->crearOrden();
        $this->facturar($orden)->assertSessionHasErrors('orden');
        $this->assertSame(0, CxpDocumento::count());
    }

    public function test_recepcion_parcial_marca_recibida_parcial(): void
    {
        $orden = $this->crearOrden(50, 10, 'ITBMS_7');
        $this->aprobar($orden);

        $this->recibir($orden, 4)->assertSessionHasNoErrors();

        $this->assertSame('RECIBIDA_PARCIAL', $orden->fresh()->estado);
    }

    public function test_recepcion_total_marca_recibida(): void
    {
        $orden = $this->crearOrden(50, 10, 'ITBMS_7');
        $this->aprobar($orden);

        $this->recibir($orden, 10)->assertSessionHasNoErrors();

        $this->assertSame('RECIBIDA', $orden->fresh()->estado);
    }

    public function test_recepcion_en_dos_partes_completa_la_orden(): void
    {
        $orden = $this->crearOrden(50, 10, 'ITBMS_7');
        $this->aprobar($orden);

        $this->recibir($orden, 6)->assertSessionHasNoErrors();
        $this->assertSame('RECIBIDA_PARCIAL', $orden->fresh()->estado);

        $this->recibir($orden, 4)->assertSessionHasNoErrors();
        $this->assertSame('RECIBIDA', $orden->fresh()->estado);
    }

    public function test_recepcion_no_puede_exceder_lo_pendiente(): void
    {
        $orden = $this->crearOrden(50, 10, 'ITBMS_7');
        $this->aprobar($orden);

        $this->recibir($orden, 11)->assertSessionHasErrors('lineas');

        $this->assertSame('APROBADA', $orden->fresh()->estado);
    }

    public function test_facturar_genera_cxp_y_asiento_cuadrado(): void
    {
        $orden = $this->crearOrden(100, 1, 'ITBMS_7');
        $this->aprobar($orden);

        $this->facturar($orden, 'FACT-100')->assertSessionHasNoErrors();

        $orden->refresh();
        $this->assertSame('FACTURADA', $orden->estado);
        $this->assertNotNull($orden->cxp_documento_id);

        $factura = CxpDocumento::where('tipo_documento', 'FACTURA')->firstOrFail();
        $this->assertSame('FACT-100', $factura->numero);
        $this->assertSame('107.00', (string) $factura->total);
        $this->assertSame('107.00', (string) $factura->saldo);
        $this->assertSame('PENDIENTE', $factura->estado);

        $asiento = $factura->asiento;
        $this->assertNotNull($asiento);
        $this->assertSame('POSTEADO', $asiento->estado);
        $this->assertSame('CXP', $asiento->origen_modulo);
        $this->assertSame('107.00', (string) $asiento->total_debito);
        $this->assertSame('107.00', (string) $asiento->total_credito);

        // Débito gasto 100, débito ITBMS 7, crédito CXP 107
        $lineas = $asiento->detalle;
        $this->assertCount(3, $lineas);
        $this->assertSame($this->gasto->id, $lineas[0]->cuenta_id);
        $this->assertSame('100.00', (string) $lineas[0]->debito);
        $this->assertSame($this->itbmsCredito->id, $lineas[1]->cuenta_id);
        $this->assertSame('7.00', (string) $lineas[1]->debito);
        $this->assertSame($this->cxp->id, $lineas[2]->cuenta_id);
        $this->assertSame('107.00', (string) $lineas[2]->credito);
    }

    public function test_facturar_orden_exenta_sin_linea_itbms(): void
    {
        $orden = $this->crearOrden(80, 1, 'ITBMS_0');
        $this->aprobar($orden);

        $this->facturar($orden, 'FACT-EX')->assertSessionHasNoErrors();

        $factura = CxpDocumento::where('tipo_documento', 'FACTURA')->firstOrFail();
        $this->assertSame('80.00', (string) $factura->total);
        $this->assertCount(2, $factura->asiento->detalle);
    }

    public function test_no_se_puede_facturar_dos_veces(): void
    {
        $orden = $this->crearOrden();
        $this->aprobar($orden);
        $this->facturar($orden, 'FACT-A')->assertSessionHasNoErrors();

        $this->facturar($orden, 'FACT-B')->assertSessionHasErrors('orden');

        $this->assertSame(1, CxpDocumento::where('tipo_documento', 'FACTURA')->count());
    }

    public function test_facturar_se_puede_desde_recibida(): void
    {
        $orden = $this->crearOrden(100, 1, 'ITBMS_7');
        $this->aprobar($orden);
        $this->recibir($orden, 1)->assertSessionHasNoErrors();
        $this->assertSame('RECIBIDA', $orden->fresh()->estado);

        $this->facturar($orden, 'FACT-R')->assertSessionHasNoErrors();
        $this->assertSame('FACTURADA', $orden->fresh()->estado);
    }

    public function test_anular_orden_en_borrador(): void
    {
        $orden = $this->crearOrden();
        $this->actuar()->post(route('admin.compras.ordenes.anular', $orden))->assertSessionHasNoErrors();
        $this->assertSame('ANULADA', $orden->fresh()->estado);
    }

    public function test_no_se_puede_anular_orden_facturada(): void
    {
        $orden = $this->crearOrden();
        $this->aprobar($orden);
        $this->facturar($orden)->assertSessionHasNoErrors();

        $this->actuar()->post(route('admin.compras.ordenes.anular', $orden))->assertSessionHasErrors('orden');
        $this->assertSame('FACTURADA', $orden->fresh()->estado);
    }

    public function test_flujo_completo_orden_recepcion_factura_pago(): void
    {
        // 1. Orden de compra
        $orden = $this->crearOrden(100, 1, 'ITBMS_7');
        $this->assertSame('BORRADOR', $orden->estado);

        // 2. Aprobar
        $this->aprobar($orden);
        $this->assertSame('APROBADA', $orden->fresh()->estado);

        // 3. Recepción total
        $this->recibir($orden, 1)->assertSessionHasNoErrors();
        $this->assertSame('RECIBIDA', $orden->fresh()->estado);

        // 4. Facturar -> CxpDocumento + asiento
        $this->facturar($orden, 'FACT-FULL')->assertSessionHasNoErrors();
        $orden->refresh();
        $this->assertSame('FACTURADA', $orden->estado);

        $factura = CxpDocumento::where('tipo_documento', 'FACTURA')->firstOrFail();
        $this->assertSame('107.00', (string) $factura->total);
        $this->assertSame('107.00', (string) $factura->saldo);
        $this->assertSame('PENDIENTE', $factura->estado);

        // 5. Pago total de la factura por banco (modulo CxP)
        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-15',
            'cuenta_pago_id' => $this->banco->id,
            'aplicaciones' => [
                ['documento_id' => $factura->id, 'monto' => 107],
            ],
        ])->assertSessionHasNoErrors();

        // Factura saldada
        $factura->refresh();
        $this->assertSame('0.00', (string) $factura->saldo);
        $this->assertSame('PAGADO', $factura->estado);

        // Pago contabilizado: debito CXP, credito banco
        $pago = CxpDocumento::where('tipo_documento', 'PAGO')->firstOrFail();
        $this->assertSame('107.00', (string) $pago->total);
        $asientoPago = $pago->asiento;
        $this->assertSame('POSTEADO', $asientoPago->estado);
        $this->assertSame('107.00', (string) $asientoPago->total_debito);
        $this->assertSame('107.00', (string) $asientoPago->total_credito);

        // El banco quedo acreditado por 107 (salida de efectivo)
        $lineaBanco = $asientoPago->detalle->firstWhere('cuenta_id', $this->banco->id);
        $this->assertNotNull($lineaBanco);
        $this->assertSame('107.00', (string) $lineaBanco->credito);
    }

    public function test_facturar_sin_cuenta_default_cxp_es_rechazado(): void
    {
        CuentaDefault::where('compania_id', $this->compania->id)->where('clave', 'CXP')->delete();

        $orden = $this->crearOrden();
        $this->aprobar($orden);

        $this->facturar($orden)->assertSessionHasErrors('orden');

        $this->assertSame(0, CxpDocumento::count());
        $this->assertSame(0, Asiento::count());
        $this->assertSame('APROBADA', $orden->fresh()->estado);
    }
}
