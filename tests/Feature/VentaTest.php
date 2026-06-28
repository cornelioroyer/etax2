<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxcDocumento;
use App\Models\InvAlmacen;
use App\Models\InvExistencia;
use App\Models\InvMovimiento;
use App\Models\ItemProducto;
use App\Models\TaxImpuesto;
use App\Models\TipoContacto;
use App\Models\User;
use App\Models\VentaCotizacion;
use App\Models\VentaFactura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_importar_ventas_generico_emite_y_contabiliza(): void
    {
        // Excel "propio" (no DGI): cliente nuevo (se crea por RUC), 2 líneas del
        // mismo documento (se agrupan), itbms por monto y por tasa%.
        $csv = implode("\n", [
            'cliente,ruc,numero,fecha,concepto,cuenta,subtotal,itbms,tasa,vencimiento',
            'NUEVO CLIENTE SA,9-999-9999,VX-100,15/06/2026,Mercancia,40101,100,7,,15/07/2026',
            'NUEVO CLIENTE SA,9-999-9999,VX-100,15/06/2026,Flete,40101,50,,7,',
        ]);

        $archivo = UploadedFile::fake()->createWithContent('ventas.csv', $csv);

        $this->actuar()->post(route('admin.ventas.facturas.importar-generico'), ['archivo' => $archivo])
            ->assertRedirect(route('admin.ventas.facturas.index'))
            ->assertSessionHas('status');

        // Cliente creado automáticamente por RUC.
        $cliente = Contacto::where('compania_id', $this->compania->id)
            ->where('identificacion', '9-999-9999')->first();
        $this->assertNotNull($cliente);

        // Factura EMITIDA con 2 líneas agrupadas: subtotal 150, itbms 10.50, total 160.50.
        $factura = VentaFactura::where('numero', 'VX-100')->first();
        $this->assertNotNull($factura);
        $this->assertSame('EMITIDA', $factura->estado);
        $this->assertSame('150.00', (string) $factura->subtotal);
        $this->assertSame('10.50', (string) $factura->itbms);
        $this->assertSame('160.50', (string) $factura->total);
        $this->assertCount(2, $factura->detalle);

        // Se generó el documento CxC pendiente y el asiento cuadrado.
        $cxc = CxcDocumento::where('numero', 'VX-100')->where('tipo_documento', 'FACTURA')->first();
        $this->assertNotNull($cxc);
        $this->assertSame('PENDIENTE', $cxc->estado);
        $this->assertNotNull($factura->asiento_id);

        $asiento = Asiento::find($factura->asiento_id);
        $this->assertNotNull($asiento);
        $this->assertEqualsWithDelta(
            (float) $asiento->detalle->sum('debito'),
            (float) $asiento->detalle->sum('credito'),
            0.001,
        );
        $this->assertEqualsWithDelta(160.50, (float) $asiento->detalle->sum('debito'), 0.001);
    }

    public function test_importar_ventas_generico_es_idempotente(): void
    {
        $csv = "cliente,ruc,numero,fecha,concepto,cuenta,subtotal,itbms\n"
             ."CLIENTE PRUEBA,,DUP-1,15/06/2026,Algo,40101,100,7";

        $this->actuar()->post(route('admin.ventas.facturas.importar-generico'), [
            'archivo' => UploadedFile::fake()->createWithContent('v1.csv', $csv),
        ]);
        $this->actuar()->post(route('admin.ventas.facturas.importar-generico'), [
            'archivo' => UploadedFile::fake()->createWithContent('v2.csv', $csv),
        ]);

        // El segundo import omite el documento ya existente (no duplica).
        $this->assertSame(1, VentaFactura::where('numero', 'DUP-1')->count());
        $this->assertSame(1, CxcDocumento::where('numero', 'DUP-1')->where('tipo_documento', 'FACTURA')->count());
    }

    /** Cuenta de banco/caja para depositar el cobro (Dr en el asiento). */
    private function cuentaBanco(string $codigo = '10201'): CuentaContable
    {
        $cuenta = CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => $codigo,
            'nombre' => 'Banco General',
            'nivel' => 3,
            'naturaleza' => 'DEBITO',
            'permite_movimiento' => true,
            'conciliable' => true,
            'activa' => true,
        ]);

        CuentaDefault::create([
            'compania_id' => $this->compania->id,
            'clave' => 'BANCO_DEFAULT',
            'cuenta_id' => $cuenta->id,
        ]);

        return $cuenta;
    }

    public function test_importar_cobros_aplica_a_factura_y_postea_cuadrado(): void
    {
        $this->cuentaBanco();
        $factura = $this->facturar($this->crearCotizacion(100)); // total 107 (100 + 7% ITBMS)

        $this->assertSame('EMITIDA', $factura->estado);
        $this->assertSame('107.00', (string) $factura->saldo);

        // Cobro de B/. 107 contra la factura, por depósito en banco 10201.
        $csv = implode("\n", [
            'cliente,ruc,numero,fecha,monto,cuenta,referencia',
            "CLIENTE PRUEBA,,{$factura->numero},20/06/2026,107,10201,DEP-001",
        ]);

        $this->actuar()->post(route('admin.ventas.recibos.importar'), [
            'archivo' => UploadedFile::fake()->createWithContent('cobros.csv', $csv),
        ])->assertRedirect(route('admin.ventas.recibos.index'))->assertSessionHas('status');

        // La factura quedó pagada y sin saldo.
        $factura->refresh();
        $this->assertSame('PAGADA', $factura->estado);
        $this->assertSame('0.00', (string) $factura->saldo);

        // Se creó el recibo aplicado.
        $recibo = \App\Models\VentaRecibo::where('compania_id', $this->compania->id)->latest('id')->first();
        $this->assertNotNull($recibo);
        $this->assertSame('APLICADO', $recibo->estado);
        $this->assertSame('107.00', (string) $recibo->total);

        // Documento CxC de cobro (PAGO) con la referencia del depósito.
        $cobroDoc = CxcDocumento::where('compania_id', $this->compania->id)
            ->where('tipo_documento', 'PAGO')->latest('id')->first();
        $this->assertNotNull($cobroDoc);
        $this->assertSame('DEP-001', $cobroDoc->referencia);

        // El CxC de la factura quedó pagado.
        $cxcFactura = CxcDocumento::where('numero', $factura->numero)
            ->where('tipo_documento', 'FACTURA')->first();
        $this->assertSame('PAGADO', $cxcFactura->estado);
        $this->assertSame('0.00', (string) $cxcFactura->saldo);

        // Asiento cuadrado: Dr banco 107 / Cr CxC 107.
        $asiento = Asiento::find($recibo->asiento_id);
        $this->assertNotNull($asiento);
        $this->assertEqualsWithDelta(
            (float) $asiento->detalle->sum('debito'),
            (float) $asiento->detalle->sum('credito'),
            0.001,
        );
        $this->assertEqualsWithDelta(107.0, (float) $asiento->detalle->sum('debito'), 0.001);
    }

    public function test_importar_cobros_es_idempotente_por_referencia(): void
    {
        $this->cuentaBanco();
        $factura = $this->facturar($this->crearCotizacion(100));

        $csv = implode("\n", [
            'cliente,ruc,numero,fecha,monto,cuenta,referencia',
            "CLIENTE PRUEBA,,{$factura->numero},20/06/2026,50,10201,DEP-DUP",
        ]);

        $this->actuar()->post(route('admin.ventas.recibos.importar'), [
            'archivo' => UploadedFile::fake()->createWithContent('c1.csv', $csv),
        ]);
        $this->actuar()->post(route('admin.ventas.recibos.importar'), [
            'archivo' => UploadedFile::fake()->createWithContent('c2.csv', $csv),
        ]);

        // El segundo cobro con la misma referencia+fecha se omite (no duplica).
        $this->assertSame(1, CxcDocumento::where('compania_id', $this->compania->id)
            ->where('tipo_documento', 'PAGO')->where('referencia', 'DEP-DUP')->count());

        $factura->refresh();
        // Factura total 107 (100 + 7% ITBMS); solo se aplicó un cobro de 50 → saldo 57.
        $this->assertSame('57.00', (string) $factura->saldo);
    }

    public function test_importar_cobros_no_crea_cliente_ni_aplica_a_factura_inexistente(): void
    {
        $this->cuentaBanco();

        // Cliente que no existe y factura inexistente: nada se registra.
        $csv = implode("\n", [
            'cliente,ruc,numero,fecha,monto,cuenta,referencia',
            'CLIENTE FANTASMA,8-NT-NOEXISTE,F-NOEXISTE,20/06/2026,99,10201,X-1',
        ]);

        $this->actuar()->post(route('admin.ventas.recibos.importar'), [
            'archivo' => UploadedFile::fake()->createWithContent('c.csv', $csv),
        ])->assertRedirect(route('admin.ventas.recibos.index'));

        $this->assertSame(0, \App\Models\VentaRecibo::where('compania_id', $this->compania->id)->count());
        $this->assertSame(0, Contacto::where('identificacion', '8-NT-NOEXISTE')->count());
    }

    public function test_emitir_factura_con_producto_descuenta_inventario_y_postea_costo(): void
    {
        // Cuentas de inventario y costo de ventas + sus defaults.
        $inv = CuentaContable::create([
            'compania_id' => $this->compania->id, 'codigo' => '10105', 'nombre' => 'Inventario',
            'nivel' => 3, 'naturaleza' => 'DEBITO', 'permite_movimiento' => true, 'conciliable' => false, 'activa' => true,
        ]);
        $costo = CuentaContable::create([
            'compania_id' => $this->compania->id, 'codigo' => '50101', 'nombre' => 'Costo de ventas',
            'nivel' => 3, 'naturaleza' => 'DEBITO', 'permite_movimiento' => true, 'conciliable' => false, 'activa' => true,
        ]);
        CuentaDefault::create(['compania_id' => $this->compania->id, 'clave' => 'INVENTARIO', 'cuenta_id' => $inv->id]);
        CuentaDefault::create(['compania_id' => $this->compania->id, 'clave' => 'COSTO_VENTAS', 'cuenta_id' => $costo->id]);

        // Producto inventariable con existencia (10 unidades a costo 5).
        $almacen = InvAlmacen::create(['compania_id' => $this->compania->id, 'codigo' => 'ALM-01', 'nombre' => 'Principal', 'activo' => true]);
        $item = ItemProducto::create([
            'compania_id' => $this->compania->id, 'codigo' => 'PROD-001', 'nombre' => 'Producto X',
            'tipo' => ItemProducto::TIPO_PRODUCTO, 'precio_venta' => 50, 'costo' => 5,
            'cuenta_inventario_id' => $inv->id, 'cuenta_costo_venta_id' => $costo->id, 'activo' => true,
        ]);
        InvExistencia::create([
            'compania_id' => $this->compania->id, 'almacen_id' => $almacen->id, 'item_id' => $item->id,
            'cantidad' => 10, 'costo_promedio' => 5,
        ]);

        // Emitir factura: 2 unidades a 50 (ITBMS 7%) → subtotal 100, ITBMS 7, total 107.
        $this->actuar()->post(route('admin.ventas.facturas.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-13',
            'accion' => 'emitir',
            'lineas' => [
                ['item_id' => $item->id, 'descripcion' => 'Producto X', 'cantidad' => 2, 'precio_unitario' => 50, 'impuesto_id' => $this->impuestoId('ITBMS_7')],
            ],
        ])->assertSessionHasNoErrors();

        $factura = VentaFactura::where('compania_id', $this->compania->id)->latest('id')->firstOrFail();
        $this->assertSame(VentaFactura::ESTADO_EMITIDA, $factura->estado);

        // Asiento cuadrado con costo de ventas: Dr CxC 107 + Dr Costo 10 / Cr Ventas 100 + Cr ITBMS 7 + Cr Inventario 10.
        $asiento = Asiento::find($factura->asiento_id);
        $this->assertEqualsWithDelta((float) $asiento->detalle->sum('debito'), (float) $asiento->detalle->sum('credito'), 0.001);
        $this->assertEqualsWithDelta(117.0, (float) $asiento->detalle->sum('debito'), 0.001);
        $this->assertEqualsWithDelta(10.0, (float) $asiento->detalle->where('cuenta_id', $costo->id)->sum('debito'), 0.001);
        $this->assertEqualsWithDelta(10.0, (float) $asiento->detalle->where('cuenta_id', $inv->id)->sum('credito'), 0.001);

        // Existencia descontada: 10 - 2 = 8, costo promedio sin cambio.
        $exist = InvExistencia::where('almacen_id', $almacen->id)->where('item_id', $item->id)->firstOrFail();
        $this->assertEqualsWithDelta(8.0, (float) $exist->cantidad, 0.001);
        $this->assertEqualsWithDelta(5.0, (float) $exist->costo_promedio, 0.001);

        // Movimiento de SALIDA registrado y enlazado al documento.
        $mov = InvMovimiento::where('documento_origen', 'ventas_facturas')
            ->where('documento_id', $factura->id)
            ->where('tipo_movimiento', InvMovimiento::TIPO_SALIDA)
            ->firstOrFail();
        $this->assertSame('CONFIRMADO', $mov->estado);
        $this->assertSame($factura->asiento_id, $mov->asiento_id);

        // Anular repone el stock y marca el movimiento ANULADO.
        $this->actuar()->post(route('admin.ventas.facturas.anular', $factura))->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(10.0, (float) $exist->fresh()->cantidad, 0.001);
        $this->assertSame('ANULADO', $mov->fresh()->estado);
    }

    public function test_sobreventa_deja_existencia_negativa_consistente_con_el_mayor(): void
    {
        // Política "inventario negativo consistente": vender más de lo disponible
        // NO debe pisar la existencia en 0. El asiento acredita Inventario por la
        // cantidad COMPLETA al costo promedio, así que la existencia debe bajar por
        // esa misma cantidad para que kárdex (cantidad × costo) cuadre con el mayor.
        $inv = CuentaContable::create([
            'compania_id' => $this->compania->id, 'codigo' => '10105', 'nombre' => 'Inventario',
            'nivel' => 3, 'naturaleza' => 'DEBITO', 'permite_movimiento' => true, 'conciliable' => false, 'activa' => true,
        ]);
        $costo = CuentaContable::create([
            'compania_id' => $this->compania->id, 'codigo' => '50101', 'nombre' => 'Costo de ventas',
            'nivel' => 3, 'naturaleza' => 'DEBITO', 'permite_movimiento' => true, 'conciliable' => false, 'activa' => true,
        ]);
        CuentaDefault::create(['compania_id' => $this->compania->id, 'clave' => 'INVENTARIO', 'cuenta_id' => $inv->id]);
        CuentaDefault::create(['compania_id' => $this->compania->id, 'clave' => 'COSTO_VENTAS', 'cuenta_id' => $costo->id]);

        // Solo 3 unidades a costo 5 en existencia.
        $almacen = InvAlmacen::create(['compania_id' => $this->compania->id, 'codigo' => 'ALM-01', 'nombre' => 'Principal', 'activo' => true]);
        $item = ItemProducto::create([
            'compania_id' => $this->compania->id, 'codigo' => 'PROD-001', 'nombre' => 'Producto X',
            'tipo' => ItemProducto::TIPO_PRODUCTO, 'precio_venta' => 50, 'costo' => 5,
            'cuenta_inventario_id' => $inv->id, 'cuenta_costo_venta_id' => $costo->id, 'activo' => true,
        ]);
        InvExistencia::create([
            'compania_id' => $this->compania->id, 'almacen_id' => $almacen->id, 'item_id' => $item->id,
            'cantidad' => 3, 'costo_promedio' => 5,
        ]);

        // Vender 5 (más que las 3 disponibles).
        $this->actuar()->post(route('admin.ventas.facturas.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-13',
            'accion' => 'emitir',
            'lineas' => [
                ['item_id' => $item->id, 'descripcion' => 'Producto X', 'cantidad' => 5, 'precio_unitario' => 50, 'impuesto_id' => $this->impuestoId('ITBMS_7')],
            ],
        ])->assertSessionHasNoErrors();

        $factura = VentaFactura::where('compania_id', $this->compania->id)->latest('id')->firstOrFail();
        $asiento = Asiento::find($factura->asiento_id);

        // COGS por la cantidad COMPLETA al costo promedio: 5 u × 5 = 25. Asiento cuadra.
        $this->assertEqualsWithDelta(25.0, (float) $asiento->detalle->where('cuenta_id', $costo->id)->sum('debito'), 0.001);
        $this->assertEqualsWithDelta(25.0, (float) $asiento->detalle->where('cuenta_id', $inv->id)->sum('credito'), 0.001);
        $this->assertEqualsWithDelta((float) $asiento->detalle->sum('debito'), (float) $asiento->detalle->sum('credito'), 0.001);

        // INVARIANTE: existencia NEGATIVA (3 - 5 = -2), sin pisar en 0; costo promedio intacto.
        $exist = InvExistencia::where('almacen_id', $almacen->id)->where('item_id', $item->id)->firstOrFail();
        $this->assertEqualsWithDelta(-2.0, (float) $exist->cantidad, 0.001);
        $this->assertEqualsWithDelta(5.0, (float) $exist->costo_promedio, 0.001);

        // El movimiento descontó la cantidad completa (Δvalor kárdex = -25 = crédito a Inventario).
        $mov = InvMovimiento::where('documento_origen', 'ventas_facturas')
            ->where('documento_id', $factura->id)
            ->where('tipo_movimiento', InvMovimiento::TIPO_SALIDA)
            ->firstOrFail();
        $this->assertEqualsWithDelta(5.0, (float) $mov->detalle->sum('cantidad'), 0.001);

        // Anular repone el negativo: -2 + 5 = 3 (estado original), costo promedio 5.
        $this->actuar()->post(route('admin.ventas.facturas.anular', $factura))->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(3.0, (float) $exist->fresh()->cantidad, 0.001);
        $this->assertEqualsWithDelta(5.0, (float) $exist->fresh()->costo_promedio, 0.001);
    }

    public function test_emitir_factura_servicio_no_mueve_inventario(): void
    {
        // Sin item_id (servicio/libre): no debe crear movimiento de inventario ni costo.
        $this->actuar()->post(route('admin.ventas.facturas.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-13',
            'accion' => 'emitir',
            'lineas' => [
                ['descripcion' => 'Servicio', 'cantidad' => 1, 'precio_unitario' => 100, 'impuesto_id' => $this->impuestoId('ITBMS_7')],
            ],
        ])->assertSessionHasNoErrors();

        $factura = VentaFactura::where('compania_id', $this->compania->id)->latest('id')->firstOrFail();
        $asiento = Asiento::find($factura->asiento_id);
        // Solo 3 líneas: Dr CxC / Cr Ventas / Cr ITBMS (sin costo).
        $this->assertCount(3, $asiento->detalle);
        $this->assertSame(0, InvMovimiento::where('documento_origen', 'ventas_facturas')->where('documento_id', $factura->id)->count());
    }

    public function test_cobro_total_por_cxc_propaga_saldo_a_la_factura_de_venta(): void
    {
        // Factura de venta emitida (107) → crea su espejo en cxc_documentos.
        $factura = $this->facturar($this->crearCotizacion(100, 'ITBMS_7'));
        $cxc = $factura->cxcDocumento;
        $banco = $this->cuentaBanco();

        // Cobro TOTAL por el lado de CxC (no Ventas→Recibos).
        $this->actuar()->post(route('admin.cxc.cobros.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-20',
            'cuenta_cobro_id' => $banco->id,
            'aplicaciones' => [
                ['documento_id' => $cxc->id, 'monto' => 107],
            ],
        ])->assertSessionHasNoErrors();

        // El espejo CxC y la factura de venta quedan ambos en cero/pagado.
        $this->assertSame('0.00', (string) $cxc->fresh()->saldo);
        $this->assertSame(CxcDocumento::ESTADO_PAGADO, $cxc->fresh()->estado);

        $factura->refresh();
        $this->assertSame('0.00', (string) $factura->saldo);
        $this->assertSame(VentaFactura::ESTADO_PAGADA, $factura->estado);
    }

    public function test_cobro_parcial_por_cxc_deja_la_factura_de_venta_parcial(): void
    {
        $factura = $this->facturar($this->crearCotizacion(100, 'ITBMS_7')); // total 107
        $cxc = $factura->cxcDocumento;
        $banco = $this->cuentaBanco();

        $this->actuar()->post(route('admin.cxc.cobros.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-20',
            'cuenta_cobro_id' => $banco->id,
            'aplicaciones' => [
                ['documento_id' => $cxc->id, 'monto' => 40],
            ],
        ])->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertSame('67.00', (string) $factura->saldo);
        $this->assertSame(VentaFactura::ESTADO_PARCIAL, $factura->estado);
    }

    public function test_anular_cobro_cxc_restaura_el_saldo_de_la_factura_de_venta(): void
    {
        $factura = $this->facturar($this->crearCotizacion(100, 'ITBMS_7')); // total 107
        $cxc = $factura->cxcDocumento;
        $banco = $this->cuentaBanco();

        $this->actuar()->post(route('admin.cxc.cobros.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-20',
            'cuenta_cobro_id' => $banco->id,
            'aplicaciones' => [['documento_id' => $cxc->id, 'monto' => 107]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(VentaFactura::ESTADO_PAGADA, $factura->fresh()->estado);

        // El cobro es el último CxcDocumento de tipo PAGO.
        $cobro = CxcDocumento::where('tipo_documento', CxcDocumento::TIPO_PAGO)->latest('id')->firstOrFail();
        $this->actuar()->post(route('admin.cxc.cobros.anular', $cobro))->assertSessionHasNoErrors();

        // La factura de venta vuelve a EMITIDA con su saldo completo.
        $factura->refresh();
        $this->assertSame('107.00', (string) $factura->saldo);
        $this->assertSame(VentaFactura::ESTADO_EMITIDA, $factura->estado);
    }

    public function test_nota_credito_cxc_propaga_saldo_a_la_factura_de_venta(): void
    {
        $factura = $this->facturar($this->crearCotizacion(100, 'ITBMS_7')); // total 107
        $cxc = $factura->cxcDocumento;
        $devoluciones = CuentaContable::create([
            'compania_id' => $this->compania->id, 'codigo' => '40102', 'nombre' => 'Devoluciones en ventas',
            'nivel' => 3, 'naturaleza' => 'DEBITO', 'permite_movimiento' => true, 'conciliable' => false, 'activa' => true,
        ]);

        // NC de 40 (exenta) aplicada a la factura por el lado de CxC.
        $this->actuar()->post(route('admin.cxc.notas.store', ['tipo' => 'credito']), [
            'tipo' => 'credito',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-20',
            'concepto' => 'Descuento posterior',
            'cuenta_id' => $devoluciones->id,
            'monto' => 40,
            'tasa_itbms' => 0,
            'factura_id' => $cxc->id,
        ])->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertSame('67.00', (string) $factura->saldo);
        $this->assertSame(VentaFactura::ESTADO_PARCIAL, $factura->estado);
    }

    public function test_recibo_de_ventas_rechaza_cuenta_de_cobro_de_otra_compania(): void
    {
        // ALTO (aislamiento): el cobro por Ventas→Recibos validaba cuenta_cobro_id
        // sin filtrar por compañía → podía debitar una cuenta de OTRA compañía.
        $factura = $this->facturar($this->crearCotizacion(100, 'ITBMS_7')); // total 107

        $otra = Compania::create(['nombre' => 'OTRA COMPANIA', 'activa' => true]);
        $cuentaAjena = CuentaContable::create([
            'compania_id' => $otra->id, 'codigo' => '10201', 'nombre' => 'Banco ajeno',
            'nivel' => 3, 'naturaleza' => 'DEBITO', 'permite_movimiento' => true, 'conciliable' => false, 'activa' => true,
        ]);

        $this->actuar()->post(route('admin.ventas.recibos.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-20',
            'cuenta_cobro_id' => $cuentaAjena->id,
            'facturas' => [['id' => $factura->id, 'monto' => 107]],
        ])->assertSessionHasErrors('cuenta_cobro_id');

        // La factura sigue intacta (no se cobró nada).
        $factura->refresh();
        $this->assertSame('107.00', (string) $factura->saldo);
        $this->assertSame(VentaFactura::ESTADO_EMITIDA, $factura->estado);
    }

    public function test_anular_recibo_ventas_restaura_estado_cxc_a_pendiente(): void
    {
        // MEDIO: al anular el recibo, el cxc_documento espejo quedaba fijado en
        // PARCIAL aunque el saldo volviera al total. Debe quedar PENDIENTE.
        $factura = $this->facturar($this->crearCotizacion(100, 'ITBMS_7')); // total 107
        $cxc = $factura->cxcDocumento;
        $banco = $this->cuentaBanco();

        // Cobro TOTAL por Ventas→Recibos.
        $this->actuar()->post(route('admin.ventas.recibos.store'), [
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-20',
            'cuenta_cobro_id' => $banco->id,
            'facturas' => [['id' => $factura->id, 'monto' => 107]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(CxcDocumento::ESTADO_PAGADO, $cxc->fresh()->estado);

        $recibo = \App\Models\VentaRecibo::latest('id')->firstOrFail();
        $this->actuar()->post(route('admin.ventas.recibos.anular', $recibo))->assertSessionHasNoErrors();

        // Saldo restaurado al total → el espejo CxC debe ser PENDIENTE, no PARCIAL.
        $cxc->refresh();
        $this->assertSame('107.00', (string) $cxc->saldo);
        $this->assertSame(CxcDocumento::ESTADO_PENDIENTE, $cxc->estado);

        $factura->refresh();
        $this->assertSame('107.00', (string) $factura->saldo);
        $this->assertSame(VentaFactura::ESTADO_EMITIDA, $factura->estado);
    }

    public function test_nota_credito_sobre_factura_exenta_rechaza_itbms(): void
    {
        // MEDIO: una NC no puede revertir ITBMS que la factura nunca causó.
        $factura = $this->facturar($this->crearCotizacion(100, 'ITBMS_0')); // exenta, total 100
        $cxc = $factura->cxcDocumento;
        $devoluciones = CuentaContable::create([
            'compania_id' => $this->compania->id, 'codigo' => '40102', 'nombre' => 'Devoluciones en ventas',
            'nivel' => 3, 'naturaleza' => 'DEBITO', 'permite_movimiento' => true, 'conciliable' => false, 'activa' => true,
        ]);

        $this->actuar()->post(route('admin.cxc.notas.store', ['tipo' => 'credito']), [
            'tipo' => 'credito',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-20',
            'concepto' => 'Devolución con ITBMS indebido',
            'cuenta_id' => $devoluciones->id,
            'monto' => 50,
            'tasa_itbms' => 7,
            'factura_id' => $cxc->id,
        ])->assertSessionHasErrors('tasa_itbms');

        // No se aplicó nada a la factura exenta.
        $this->assertSame('100.00', (string) $cxc->fresh()->saldo);
    }

    public function test_nota_credito_no_puede_exceder_el_itbms_de_la_factura(): void
    {
        // MEDIO (cota): el ITBMS de la NC no puede superar el de la factura origen.
        $factura = $this->facturar($this->crearCotizacion(100, 'ITBMS_7')); // base 100, ITBMS 7, total 107
        $cxc = $factura->cxcDocumento;
        $devoluciones = CuentaContable::create([
            'compania_id' => $this->compania->id, 'codigo' => '40102', 'nombre' => 'Devoluciones en ventas',
            'nivel' => 3, 'naturaleza' => 'DEBITO', 'permite_movimiento' => true, 'conciliable' => false, 'activa' => true,
        ]);

        // NC base 80 al 10% → ITBMS 8 > 7 (de la factura). Total 88 ≤ saldo 107.
        $this->actuar()->post(route('admin.cxc.notas.store', ['tipo' => 'credito']), [
            'tipo' => 'credito',
            'cliente_id' => $this->cliente->id,
            'fecha' => '2026-06-20',
            'concepto' => 'Crédito con ITBMS excesivo',
            'cuenta_id' => $devoluciones->id,
            'monto' => 80,
            'tasa_itbms' => 10,
            'factura_id' => $cxc->id,
        ])->assertSessionHasErrors('tasa_itbms');

        $this->assertSame('107.00', (string) $cxc->fresh()->saldo);
    }
}
