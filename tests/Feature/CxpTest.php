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
use Illuminate\Http\UploadedFile;
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

    private CuentaContable $anticipo;

    private CuentaContable $retencion;

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
        $this->anticipo = $crear('10115', 'Anticipos a Proveedores', 'DEBITO');
        $this->retencion = $crear('20105', 'Retenciones por Pagar', 'CREDITO');

        foreach (['CXP' => $this->cxp, 'GASTO_DEFAULT' => $this->gasto, 'ITBMS_CREDITO' => $this->itbmsCredito, 'BANCO_DEFAULT' => $this->banco, 'ANTICIPO_PROVEEDOR' => $this->anticipo] as $clave => $cuenta) {
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

    private function crearBorrador(float $precio = 100, int $tasa = 7, string $numero = 'A-1001'): CxpDocumento
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

    private function crearFactura(float $precio = 100, int $tasa = 7, string $numero = 'A-1001'): CxpDocumento
    {
        $borrador = $this->crearBorrador($precio, $tasa, $numero);

        $this->actuar()->post(route('admin.cxp.facturas.contabilizar', $borrador))
            ->assertSessionHasNoErrors();

        return $borrador->fresh();
    }

    public function test_listado_de_facturas_se_muestra(): void
    {
        $this->actuar()->get(route('admin.cxp.facturas.index'))
            ->assertOk()
            ->assertSee('Facturas de Compras');
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

    public function test_compra_al_contado_postea_directo_a_banco(): void
    {
        $this->actuar()->post(route('admin.cxp.facturas.store'), [
            'proveedor_id' => $this->proveedor->id,
            'numero' => 'C-2001',
            'fecha' => '2026-06-12',
            'forma_pago' => 'CONTADO',
            'cuenta_pago_id' => $this->banco->id,
            'lineas' => [
                ['descripcion' => 'Compra contado', 'cantidad' => 1, 'precio_unitario' => 100, 'tasa_itbms' => 7, 'cuenta_id' => $this->gasto->id],
            ],
        ])->assertSessionHasNoErrors();

        $factura = CxpDocumento::where('tipo_documento', 'FACTURA')->latest('id')->firstOrFail();

        $this->assertSame('PAGADO', $factura->estado);
        $this->assertSame('0.00', (string) $factura->saldo);
        $this->assertSame('107.00', (string) $factura->total);

        $asiento = $factura->asiento;
        $this->assertNotNull($asiento);
        $this->assertSame('POSTEADO', $asiento->estado);

        // Gasto al débito, ITBMS crédito fiscal al débito, Banco al crédito (sin tocar CXP)
        $lineas = $asiento->detalle;
        $this->assertCount(3, $lineas);
        $this->assertSame($this->gasto->id, $lineas[0]->cuenta_id);
        $this->assertSame('100.00', (string) $lineas[0]->debito);
        $this->assertSame($this->itbmsCredito->id, $lineas[1]->cuenta_id);
        $this->assertSame('7.00', (string) $lineas[1]->debito);
        $this->assertSame($this->banco->id, $lineas[2]->cuenta_id);
        $this->assertSame('107.00', (string) $lineas[2]->credito);
        $this->assertFalse($asiento->detalle->contains('cuenta_id', $this->cxp->id));
    }

    public function test_importar_compras_generico_crea_borradores(): void
    {
        // Excel "propio" (no DGI): proveedor nuevo (se crea), 2 líneas del mismo
        // documento (se agrupan), itbms por monto y por tasa%, y una NC aparte.
        $csv = implode("\n", [
            'proveedor,ruc,numero,fecha,tipo,concepto,cuenta,subtotal,itbms,tasa,vencimiento',
            'NUEVO PROVEEDOR SA,9-999-9999,FX-100,15/06/2026,FACTURA,Mercancia,60101,100,7,,15/07/2026',
            'NUEVO PROVEEDOR SA,9-999-9999,FX-100,15/06/2026,FACTURA,Flete,60101,50,,7,',
            'NUEVO PROVEEDOR SA,9-999-9999,NC-9,16/06/2026,NC,Devolucion,60101,20,1.40,,',
        ]);

        $archivo = UploadedFile::fake()->createWithContent('compras.csv', $csv);

        $this->actuar()->post(route('admin.cxp.facturas.importar-generico'), ['archivo' => $archivo])
            ->assertRedirect(route('admin.cxp.facturas.index'))
            ->assertSessionHas('status');

        // Proveedor creado automáticamente por RUC.
        $proveedor = Contacto::where('compania_id', $this->compania->id)
            ->where('identificacion', '9-999-9999')->first();
        $this->assertNotNull($proveedor);

        // Factura con 2 líneas agrupadas: subtotal 150, itbms 7+3.50, total 160.50.
        $factura = CxpDocumento::where('tipo_documento', 'FACTURA')->where('numero', 'FX-100')->first();
        $this->assertNotNull($factura);
        $this->assertSame('BORRADOR', $factura->estado);
        $this->assertSame('150.00', (string) $factura->subtotal);
        $this->assertSame('10.50', (string) $factura->impuesto);
        $this->assertSame('160.50', (string) $factura->total);
        $this->assertCount(2, $factura->detalle);
        $this->assertSame($this->gasto->id, $factura->detalle->first()->cuenta_id);

        // Nota de crédito registrada como tipo NOTA_CREDITO.
        $nc = CxpDocumento::where('tipo_documento', 'NOTA_CREDITO')->where('numero', 'NC-9')->first();
        $this->assertNotNull($nc);
        $this->assertSame('21.40', (string) $nc->total);
    }

    public function test_importar_compras_generico_es_idempotente(): void
    {
        $csv = "proveedor,ruc,numero,fecha,tipo,concepto,cuenta,subtotal,itbms\n"
             ."PROVEEDOR PRUEBA,,DUP-1,15/06/2026,FACTURA,Algo,60101,100,7";

        $this->actuar()->post(route('admin.cxp.facturas.importar-generico'), [
            'archivo' => UploadedFile::fake()->createWithContent('c1.csv', $csv),
        ]);
        $this->actuar()->post(route('admin.cxp.facturas.importar-generico'), [
            'archivo' => UploadedFile::fake()->createWithContent('c2.csv', $csv),
        ]);

        // El segundo import omite el documento ya existente.
        $this->assertSame(1, CxpDocumento::where('tipo_documento', 'FACTURA')->where('numero', 'DUP-1')->count());
    }

    public function test_importar_saldos_iniciales_abre_documentos_contabilizados(): void
    {
        // Cuenta de apertura (patrimonio) que el usuario elige como contrapartida.
        $apertura = CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => '30199',
            'nombre' => 'Saldos de Apertura',
            'nivel' => 3,
            'naturaleza' => 'CREDITO',
            'permite_movimiento' => true,
            'conciliable' => false,
            'activa' => true,
        ]);

        // Una factura pendiente y una NC a favor; el monto es el saldo, sin ITBMS.
        $csv = implode("\n", [
            'proveedor,ruc,numero,fecha,vencimiento,monto,tipo,concepto',
            'PROVEEDOR PRUEBA,,SI-001,15/05/2026,15/06/2026,1200,FACTURA,Saldo pendiente',
            'PROVEEDOR PRUEBA,,SI-NC-1,20/05/2026,,80,NC,Credito a favor',
        ]);

        $this->actuar()->post(route('admin.cxp.facturas.importar-saldos'), [
            'archivo' => UploadedFile::fake()->createWithContent('saldos.csv', $csv),
            'fecha_corte' => '2026-06-30',
            'cuenta_apertura_id' => $apertura->id,
        ])->assertRedirect(route('admin.cxp.facturas.index'))->assertSessionHas('status');

        // Factura: PENDIENTE, total = saldo = monto, sin ITBMS, fechas originales.
        $factura = CxpDocumento::where('tipo_documento', 'FACTURA')->where('numero', 'SI-001')->first();
        $this->assertNotNull($factura);
        $this->assertSame('PENDIENTE', $factura->estado);
        $this->assertSame('1200.00', (string) $factura->total);
        $this->assertSame('1200.00', (string) $factura->saldo);
        $this->assertSame('0.00', (string) $factura->impuesto);
        $this->assertSame('2026-05-15', $factura->fecha->toDateString());
        $this->assertSame('2026-06-15', $factura->fecha_vencimiento->toDateString());

        // Asiento de apertura: posteado a la FECHA DE CORTE, Dr apertura / Cr CxP.
        $asiento = $factura->asiento;
        $this->assertNotNull($asiento);
        $this->assertSame('POSTEADO', $asiento->estado);
        $this->assertSame('CXP', $asiento->origen_modulo);
        $this->assertSame('2026-06-30', $asiento->fecha->toDateString());

        $lineas = $asiento->detalle->sortBy('linea')->values();
        $this->assertSame($apertura->id, $lineas[0]->cuenta_id);
        $this->assertSame('1200.00', (string) $lineas[0]->debito);
        $this->assertSame($this->cxp->id, $lineas[1]->cuenta_id);
        $this->assertSame('1200.00', (string) $lineas[1]->credito);
        $this->assertSame($this->proveedor->id, $lineas[1]->contacto_id);

        // No re-crea el gasto ni el ITBMS de crédito fiscal.
        $this->assertFalse($asiento->detalle->contains('cuenta_id', $this->gasto->id));
        $this->assertFalse($asiento->detalle->contains('cuenta_id', $this->itbmsCredito->id));

        // NC a favor: asiento invertido Dr CxP / Cr apertura.
        $nc = CxpDocumento::where('tipo_documento', 'NOTA_CREDITO')->where('numero', 'SI-NC-1')->first();
        $this->assertNotNull($nc);
        $this->assertSame('80.00', (string) $nc->total);
        $lineasNc = $nc->asiento->detalle->sortBy('linea')->values();
        $this->assertSame($this->cxp->id, $lineasNc[0]->cuenta_id);
        $this->assertSame('80.00', (string) $lineasNc[0]->debito);
        $this->assertSame($apertura->id, $lineasNc[1]->cuenta_id);
        $this->assertSame('80.00', (string) $lineasNc[1]->credito);
    }

    public function test_importar_saldos_iniciales_es_idempotente(): void
    {
        $apertura = CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => '30199',
            'nombre' => 'Saldos de Apertura',
            'nivel' => 3,
            'naturaleza' => 'CREDITO',
            'permite_movimiento' => true,
            'conciliable' => false,
            'activa' => true,
        ]);

        $csv = "proveedor,ruc,numero,fecha,vencimiento,monto\nPROVEEDOR PRUEBA,,SI-DUP,15/05/2026,15/06/2026,500";
        $payload = fn () => [
            'archivo' => UploadedFile::fake()->createWithContent('s.csv', $csv),
            'fecha_corte' => '2026-06-30',
            'cuenta_apertura_id' => $apertura->id,
        ];

        $this->actuar()->post(route('admin.cxp.facturas.importar-saldos'), $payload());
        $this->actuar()->post(route('admin.cxp.facturas.importar-saldos'), $payload());

        $this->assertSame(1, CxpDocumento::where('tipo_documento', 'FACTURA')->where('numero', 'SI-DUP')->count());
    }

    public function test_importar_saldos_iniciales_requiere_cuenta_y_fecha(): void
    {
        $csv = "proveedor,ruc,numero,fecha,monto\nPROVEEDOR PRUEBA,,SI-X,15/05/2026,100";

        $this->actuar()->post(route('admin.cxp.facturas.importar-saldos'), [
            'archivo' => UploadedFile::fake()->createWithContent('s.csv', $csv),
        ])->assertSessionHasErrors(['fecha_corte', 'cuenta_apertura_id']);

        $this->assertSame(0, CxpDocumento::where('numero', 'SI-X')->count());
    }

    public function test_compra_al_contado_requiere_cuenta_de_pago(): void
    {
        $this->actuar()->post(route('admin.cxp.facturas.store'), [
            'proveedor_id' => $this->proveedor->id,
            'numero' => 'C-2002',
            'fecha' => '2026-06-12',
            'forma_pago' => 'CONTADO',
            'lineas' => [
                ['descripcion' => 'Compra contado', 'cantidad' => 1, 'precio_unitario' => 100, 'tasa_itbms' => 7, 'cuenta_id' => $this->gasto->id],
            ],
        ])->assertSessionHasErrors('cuenta_pago_id');

        $this->assertSame(0, CxpDocumento::where('tipo_documento', 'FACTURA')->count());
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

    public function test_store_crea_borrador_sin_asiento(): void
    {
        $borrador = $this->crearBorrador(100, 7);

        $this->assertSame('BORRADOR', $borrador->estado);
        $this->assertNull($borrador->asiento_id);
        $this->assertSame('107.00', (string) $borrador->total);
        $this->assertSame('107.00', (string) $borrador->saldo);
        $this->assertCount(1, $borrador->detalle);
    }

    public function test_editar_borrador_actualiza_lineas_y_totales(): void
    {
        $borrador = $this->crearBorrador(100, 7);

        $this->actuar()->put(route('admin.cxp.facturas.update', $borrador), [
            'proveedor_id' => $this->proveedor->id,
            'numero' => 'A-1001',
            'fecha' => '2026-06-12',
            'fecha_vencimiento' => '2026-07-12',
            'lineas' => [
                ['descripcion' => 'Linea corregida', 'cantidad' => 2, 'precio_unitario' => 50, 'tasa_itbms' => 10, 'cuenta_id' => $this->gasto->id],
            ],
        ])->assertSessionHasNoErrors();

        $borrador->refresh();
        $this->assertSame('BORRADOR', $borrador->estado);
        $this->assertSame('110.00', (string) $borrador->total);
        $this->assertCount(1, $borrador->detalle);
        $this->assertSame('Linea corregida', $borrador->detalle[0]->descripcion);
        $this->assertNull($borrador->asiento_id);
    }

    public function test_contabilizar_borrador_genera_asiento_y_bloquea_edicion(): void
    {
        $borrador = $this->crearBorrador(100, 7);

        $this->actuar()->post(route('admin.cxp.facturas.contabilizar', $borrador))
            ->assertSessionHasNoErrors();

        $borrador->refresh();
        $this->assertSame('PENDIENTE', $borrador->estado);
        $this->assertNotNull($borrador->asiento);
        $this->assertSame('POSTEADO', $borrador->asiento->estado);

        // Una factura ya contabilizada no puede editarse ni eliminarse.
        $this->actuar()->get(route('admin.cxp.facturas.edit', $borrador))
            ->assertRedirect(route('admin.cxp.facturas.show', $borrador));

        $this->actuar()->delete(route('admin.cxp.facturas.destroy', $borrador))
            ->assertSessionHasErrors('documento');

        $this->assertDatabaseHas('cxp_documentos', ['id' => $borrador->id]);
    }

    public function test_eliminar_borrador_lo_borra(): void
    {
        $borrador = $this->crearBorrador(100, 7);

        $this->actuar()->delete(route('admin.cxp.facturas.destroy', $borrador))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('cxp_documentos', ['id' => $borrador->id]);
        $this->assertDatabaseMissing('cxp_documentos_detalle', ['documento_id' => $borrador->id]);
    }

    /**
     * Reembolso e importación son cargos +1 cobrables: contabilizan igual que una
     * factura (Dr contrapartida + Dr ITBMS / Cr CXP) y dejan saldo pagable.
     */
    public function test_reembolso_e_importacion_se_contabilizan_como_factura(): void
    {
        foreach ([CxpDocumento::TIPO_REEMBOLSO, CxpDocumento::TIPO_IMPORTACION] as $i => $tipo) {
            $this->actuar()->post(route('admin.cxp.facturas.store'), [
                'tipo_documento' => $tipo,
                'proveedor_id' => $this->proveedor->id,
                'numero' => 'D-'.$i,
                'fecha' => '2026-06-12',
                'fecha_vencimiento' => '2026-07-12',
                'lineas' => [
                    ['descripcion' => 'Linea', 'cantidad' => 1, 'precio_unitario' => 100, 'tasa_itbms' => 7, 'cuenta_id' => $this->gasto->id],
                ],
            ])->assertSessionHasNoErrors();

            $doc = CxpDocumento::where('tipo_documento', $tipo)->latest('id')->firstOrFail();
            $this->assertSame('BORRADOR', $doc->estado);

            $this->actuar()->post(route('admin.cxp.facturas.contabilizar', $doc))
                ->assertSessionHasNoErrors();

            $doc->refresh();
            $this->assertSame('PENDIENTE', $doc->estado);
            $this->assertSame('107.00', (string) $doc->saldo);

            $asiento = $doc->asiento;
            $this->assertSame('POSTEADO', $asiento->estado);

            $lineas = $asiento->detalle;
            $this->assertCount(3, $lineas);
            $this->assertSame($this->gasto->id, $lineas[0]->cuenta_id);
            $this->assertSame('100.00', (string) $lineas[0]->debito);
            $this->assertSame($this->itbmsCredito->id, $lineas[1]->cuenta_id);
            $this->assertSame('7.00', (string) $lineas[1]->debito);
            $this->assertSame($this->cxp->id, $lineas[2]->cuenta_id);
            $this->assertSame('107.00', (string) $lineas[2]->credito);

            // Genera saldo pagable: aparece entre los tipos con saldo del submayor.
            $this->assertContains($tipo, CxpDocumento::tiposPagables());
        }
    }

    public function test_reembolso_al_contado_postea_directo_a_banco(): void
    {
        $this->actuar()->post(route('admin.cxp.facturas.store'), [
            'tipo_documento' => CxpDocumento::TIPO_REEMBOLSO,
            'proveedor_id' => $this->proveedor->id,
            'numero' => 'RE-CTDO',
            'fecha' => '2026-06-12',
            'forma_pago' => 'CONTADO',
            'cuenta_pago_id' => $this->banco->id,
            'lineas' => [
                ['descripcion' => 'Reembolso contado', 'cantidad' => 1, 'precio_unitario' => 100, 'tasa_itbms' => 7, 'cuenta_id' => $this->gasto->id],
            ],
        ])->assertSessionHasNoErrors();

        $doc = CxpDocumento::where('tipo_documento', CxpDocumento::TIPO_REEMBOLSO)->latest('id')->firstOrFail();

        $this->assertSame('PAGADO', $doc->estado);
        $this->assertSame('0.00', (string) $doc->saldo);
        // Pago directo: no toca CXP, sí toca banco.
        $this->assertFalse($doc->asiento->detalle->contains('cuenta_id', $this->cxp->id));
        $this->assertTrue($doc->asiento->detalle->contains('cuenta_id', $this->banco->id));
    }

    public function test_pago_con_retencion_acredita_banco_neto_y_retencion(): void
    {
        $factura = $this->crearFactura(100, 7); // total 107

        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-13',
            'cuenta_pago_id' => $this->banco->id,
            'retencion' => 7,
            'retencion_cuenta_id' => $this->retencion->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
        ])->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertSame('PAGADO', $factura->estado);
        $this->assertSame('0.00', (string) $factura->saldo);

        $pago = CxpDocumento::where('tipo_documento', 'PAGO')->firstOrFail();
        $this->assertSame('7.00', (string) $pago->retencion);
        $this->assertSame('107.00', (string) $pago->total);

        // Dr CXP 107; Cr Banco 100 (neto); Cr Retención 7.
        $det = $pago->asiento->detalle;
        $this->assertCount(3, $det);
        $this->assertSame($this->cxp->id, $det[0]->cuenta_id);
        $this->assertSame('107.00', (string) $det[0]->debito);
        $this->assertSame($this->banco->id, $det[1]->cuenta_id);
        $this->assertSame('100.00', (string) $det[1]->credito);
        $this->assertSame($this->retencion->id, $det[2]->cuenta_id);
        $this->assertSame('7.00', (string) $det[2]->credito);
    }

    public function test_retencion_no_puede_exceder_el_pago(): void
    {
        $factura = $this->crearFactura(100, 7);

        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-13',
            'cuenta_pago_id' => $this->banco->id,
            'retencion' => 200,
            'retencion_cuenta_id' => $this->retencion->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
        ])->assertSessionHasErrors('retencion');

        $this->assertSame(0, CxpDocumento::where('tipo_documento', 'PAGO')->count());
    }

    public function test_anticipo_registra_asiento_y_queda_disponible(): void
    {
        $this->actuar()->post(route('admin.cxp.anticipos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-12',
            'cuenta_pago_id' => $this->banco->id,
            'monto' => 500,
        ])->assertSessionHasNoErrors();

        $anticipo = CxpDocumento::where('tipo_documento', 'ANTICIPO')->firstOrFail();
        $this->assertSame('AN-000001', $anticipo->numero);
        $this->assertSame('PENDIENTE', $anticipo->estado);
        $this->assertSame('500.00', (string) $anticipo->saldo);

        // Dr Anticipos a proveedores 500; Cr Banco 500.
        $det = $anticipo->asiento->detalle;
        $this->assertSame('POSTEADO', $anticipo->asiento->estado);
        $this->assertCount(2, $det);
        $this->assertSame($this->anticipo->id, $det[0]->cuenta_id);
        $this->assertSame('500.00', (string) $det[0]->debito);
        $this->assertSame($this->banco->id, $det[1]->cuenta_id);
        $this->assertSame('500.00', (string) $det[1]->credito);
    }

    public function test_anticipo_se_aplica_a_factura_y_reduce_saldos(): void
    {
        $factura = $this->crearFactura(100, 7); // saldo 107
        $anticipo = $this->registrarAnticipo(500);

        $this->actuar()->post(route('admin.cxp.anticipos.aplicar', $anticipo), [
            'fecha' => '2026-06-13',
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
        ])->assertSessionHasNoErrors();

        $factura->refresh();
        $anticipo->refresh();

        $this->assertSame('PAGADO', $factura->estado);
        $this->assertSame('0.00', (string) $factura->saldo);
        $this->assertSame('PARCIAL', $anticipo->estado);
        $this->assertSame('393.00', (string) $anticipo->saldo);

        // La aplicación postea Dr CXP / Cr Anticipos por 107.
        $aplicacion = \App\Models\CxpAplicacion::where('documento_origen_id', $anticipo->id)->firstOrFail();
        $this->assertNotNull($aplicacion->asiento_id);
        $asiento = \App\Models\Asiento::find($aplicacion->asiento_id);
        $this->assertSame($this->cxp->id, $asiento->detalle[0]->cuenta_id);
        $this->assertSame('107.00', (string) $asiento->detalle[0]->debito);
        $this->assertSame($this->anticipo->id, $asiento->detalle[1]->cuenta_id);
        $this->assertSame('107.00', (string) $asiento->detalle[1]->credito);
    }

    public function test_anticipo_aplicado_no_excede_disponible(): void
    {
        $factura = $this->crearFactura(1000, 0); // saldo 1000
        $anticipo = $this->registrarAnticipo(300);

        $this->actuar()->post(route('admin.cxp.anticipos.aplicar', $anticipo), [
            'fecha' => '2026-06-13',
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 500]],
        ])->assertSessionHasErrors('aplicaciones');

        $this->assertSame('300.00', (string) $anticipo->fresh()->saldo);
        $this->assertSame('1000.00', (string) $factura->fresh()->saldo);
    }

    public function test_anular_anticipo_revierte_aplicaciones(): void
    {
        $factura = $this->crearFactura(100, 7);
        $anticipo = $this->registrarAnticipo(500);

        $this->actuar()->post(route('admin.cxp.anticipos.aplicar', $anticipo), [
            'fecha' => '2026-06-13',
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
        ])->assertSessionHasNoErrors();

        $this->actuar()->post(route('admin.cxp.anticipos.anular', $anticipo))
            ->assertSessionHasNoErrors();

        $factura->refresh();
        $anticipo->refresh();

        $this->assertSame('ANULADO', $anticipo->estado);
        $this->assertSame('PENDIENTE', $factura->estado);
        $this->assertSame('107.00', (string) $factura->saldo);
        $this->assertSame('ANULADO', $anticipo->asiento->fresh()->estado);
        $this->assertSame(0, \App\Models\CxpAplicacion::where('documento_origen_id', $anticipo->id)->count());
    }

    private function registrarAnticipo(float $monto): CxpDocumento
    {
        $this->actuar()->post(route('admin.cxp.anticipos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-12',
            'cuenta_pago_id' => $this->banco->id,
            'monto' => $monto,
        ])->assertSessionHasNoErrors();

        return CxpDocumento::where('tipo_documento', 'ANTICIPO')->latest('id')->firstOrFail();
    }

    public function test_pago_aplica_anticipo_y_reduce_efectivo_y_disponible(): void
    {
        $factura = $this->crearFactura(100, 7); // total 107
        $anticipo = $this->registrarAnticipo(500);

        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-13',
            'cuenta_pago_id' => $this->banco->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
            'creditos' => [['documento_id' => $anticipo->id, 'monto' => 50]],
        ])->assertSessionHasNoErrors();

        $factura->refresh();
        $anticipo->refresh();

        // La factura queda saldada (50 de anticipo + 57 de efectivo).
        $this->assertSame('PAGADO', $factura->estado);
        $this->assertSame('0.00', (string) $factura->saldo);

        // El anticipo consume 50 de su disponible.
        $this->assertSame('PARCIAL', $anticipo->estado);
        $this->assertSame('450.00', (string) $anticipo->saldo);

        // El pago solo cubre el remanente en efectivo (57).
        $pago = CxpDocumento::where('tipo_documento', 'PAGO')->firstOrFail();
        $this->assertSame('57.00', (string) $pago->total);
        $this->assertSame($this->cxp->id, $pago->asiento->detalle[0]->cuenta_id);
        $this->assertSame('57.00', (string) $pago->asiento->detalle[0]->debito);
        $this->assertSame($this->banco->id, $pago->asiento->detalle[1]->cuenta_id);
        $this->assertSame('57.00', (string) $pago->asiento->detalle[1]->credito);

        // La aplicación del anticipo postea Dr CXP / Cr Anticipos por 50.
        $aplAnticipo = \App\Models\CxpAplicacion::where('documento_origen_id', $anticipo->id)->firstOrFail();
        $this->assertSame('50.00', (string) $aplAnticipo->monto_aplicado);
        $this->assertNotNull($aplAnticipo->asiento_id);
        $asAnt = \App\Models\Asiento::find($aplAnticipo->asiento_id);
        $this->assertSame($this->cxp->id, $asAnt->detalle[0]->cuenta_id);
        $this->assertSame('50.00', (string) $asAnt->detalle[0]->debito);
        $this->assertSame($this->anticipo->id, $asAnt->detalle[1]->cuenta_id);
        $this->assertSame('50.00', (string) $asAnt->detalle[1]->credito);
    }

    public function test_pago_aplica_nota_credito_disponible_sin_asiento_nuevo(): void
    {
        $factura = $this->crearFactura(100, 7); // total 107

        // Nota de crédito contabilizada con saldo disponible (su Dr CXP ya ocurrió).
        $nc = CxpDocumento::create([
            'compania_id' => $this->compania->id,
            'proveedor_id' => $this->proveedor->id,
            'tipo_documento' => CxpDocumento::TIPO_NOTA_CREDITO,
            'numero' => 'NC-000001',
            'fecha' => '2026-06-12',
            'subtotal' => 30,
            'impuesto' => 0,
            'total' => 30,
            'saldo' => 30,
            'estado' => CxpDocumento::ESTADO_PENDIENTE,
            'created_by' => $this->admin->email,
        ]);

        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-13',
            'cuenta_pago_id' => $this->banco->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
            'creditos' => [['documento_id' => $nc->id, 'monto' => 30]],
        ])->assertSessionHasNoErrors();

        $factura->refresh();
        $nc->refresh();

        $this->assertSame('PAGADO', $factura->estado);
        $this->assertSame('0.00', (string) $factura->saldo);
        $this->assertSame('PAGADO', $nc->estado);
        $this->assertSame('0.00', (string) $nc->saldo);

        // El pago cubre el remanente (77) en efectivo.
        $pago = CxpDocumento::where('tipo_documento', 'PAGO')->firstOrFail();
        $this->assertSame('77.00', (string) $pago->total);

        // Aplicar la NC es solo submayor: la aplicación no genera asiento nuevo.
        $aplNc = \App\Models\CxpAplicacion::where('documento_origen_id', $nc->id)->firstOrFail();
        $this->assertSame('30.00', (string) $aplNc->monto_aplicado);
        $this->assertNull($aplNc->asiento_id);
    }

    public function test_creditos_no_pueden_exceder_total_a_liquidar(): void
    {
        $factura = $this->crearFactura(100, 7); // total 107
        $anticipo = $this->registrarAnticipo(500);

        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-13',
            'cuenta_pago_id' => $this->banco->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
            'creditos' => [['documento_id' => $anticipo->id, 'monto' => 200]],
        ])->assertSessionHasErrors('creditos');

        $this->assertSame(0, CxpDocumento::where('tipo_documento', 'PAGO')->count());
        $this->assertSame('500.00', (string) $anticipo->fresh()->saldo);
    }

    public function test_pago_con_descuento_pronto_pago_acredita_ingreso(): void
    {
        $factura = $this->crearFactura(100, 7); // total 107

        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-13',
            'cuenta_pago_id' => $this->banco->id,
            'descuento' => 7,
            'descuento_cuenta_id' => $this->gasto->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
        ])->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertSame('PAGADO', $factura->estado);

        $pago = CxpDocumento::where('tipo_documento', 'PAGO')->firstOrFail();
        $this->assertSame('107.00', (string) $pago->total);
        $this->assertSame('7.00', (string) $pago->descuento);

        // Dr CXP 107; Cr Banco 100; Cr Descuento (ingreso) 7.
        $det = $pago->asiento->detalle;
        $this->assertCount(3, $det);
        $this->assertSame($this->cxp->id, $det[0]->cuenta_id);
        $this->assertSame('107.00', (string) $det[0]->debito);
        $this->assertSame($this->banco->id, $det[1]->cuenta_id);
        $this->assertSame('100.00', (string) $det[1]->credito);
        $this->assertSame($this->gasto->id, $det[2]->cuenta_id);
        $this->assertSame('7.00', (string) $det[2]->credito);
    }

    public function test_pago_con_retencion_itbms_e_isr_separadas(): void
    {
        $factura = $this->crearFactura(100, 7); // total 107

        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-13',
            'cuenta_pago_id' => $this->banco->id,
            'retencion' => 5,
            'retencion_cuenta_id' => $this->retencion->id,
            'retencion_isr' => 2,
            'retencion_isr_cuenta_id' => $this->gasto->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
        ])->assertSessionHasNoErrors();

        $pago = CxpDocumento::where('tipo_documento', 'PAGO')->firstOrFail();
        $this->assertSame('7.00', (string) $pago->retencion);
        $this->assertSame('5.00', (string) $pago->retencion_itbms);
        $this->assertSame('2.00', (string) $pago->retencion_isr);

        // Dr CXP 107; Cr Banco 100; Cr Ret ITBMS 5; Cr Ret ISR 2.
        $det = $pago->asiento->detalle;
        $this->assertCount(4, $det);
        $this->assertSame($this->banco->id, $det[1]->cuenta_id);
        $this->assertSame('100.00', (string) $det[1]->credito);
        $this->assertSame($this->retencion->id, $det[2]->cuenta_id);
        $this->assertSame('5.00', (string) $det[2]->credito);
        $this->assertSame($this->gasto->id, $det[3]->cuenta_id);
        $this->assertSame('2.00', (string) $det[3]->credito);
    }

    public function test_pago_desde_cuenta_bancaria_genera_movimiento_y_anular_lo_elimina(): void
    {
        $cuentaBancaria = $this->crearCuentaBancaria();
        $factura = $this->crearFactura(100, 7);

        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-13',
            'cuenta_pago_id' => $this->banco->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
        ])->assertSessionHasNoErrors();

        $pago = CxpDocumento::where('tipo_documento', 'PAGO')->firstOrFail();

        // El movimiento lo refleja BancoSync automáticamente al postear el
        // asiento (documento_origen=cgl_asientos, ligado al asiento, no al
        // documento CxP): CxpPagoController ya no crea un segundo movimiento
        // manual (antes duplicaba el egreso; ver commit de este fix).
        $mov = \App\Models\BcoMovimiento::where('asiento_id', $pago->asiento_id)
            ->where('cuenta_bancaria_id', $cuentaBancaria->id)->first();
        $this->assertNotNull($mov);
        $this->assertSame('cgl_asientos', $mov->documento_origen);
        $this->assertSame('107.00', (string) $mov->debito);
        $this->assertSame('ASIENTO', $mov->tipo_movimiento);
        $this->assertFalse((bool) $mov->conciliado);

        // Exactamente un movimiento para este asiento+cuenta bancaria (nunca dos).
        $this->assertSame(1, \App\Models\BcoMovimiento::where('asiento_id', $pago->asiento_id)
            ->where('cuenta_bancaria_id', $cuentaBancaria->id)->count());

        // Al anular el pago se elimina el movimiento bancario.
        $this->actuar()->post(route('admin.cxp.pagos.anular', $pago))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, \App\Models\BcoMovimiento::where('asiento_id', $pago->asiento_id)
            ->where('cuenta_bancaria_id', $cuentaBancaria->id)->count());
    }

    public function test_no_se_puede_anular_pago_con_movimiento_conciliado(): void
    {
        $this->crearCuentaBancaria();
        $factura = $this->crearFactura(100, 7);

        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-13',
            'cuenta_pago_id' => $this->banco->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
        ])->assertSessionHasNoErrors();

        $pago = CxpDocumento::where('tipo_documento', 'PAGO')->firstOrFail();
        \App\Models\BcoMovimiento::where('asiento_id', $pago->asiento_id)->update(['conciliado' => true]);

        // El bloqueo ahora lo lanza BancoSync::revertir (vía AsientoObserver),
        // con clave de error 'asiento' en vez de 'documento'; las vistas
        // renderizan $errors->all() sin distinguir la clave.
        $this->actuar()->post(route('admin.cxp.pagos.anular', $pago))
            ->assertSessionHasErrors('asiento');

        $this->assertSame('PAGADO', $pago->fresh()->estado);
        $this->assertSame('0.00', (string) $factura->fresh()->saldo);
    }

    public function test_corregir_pago_lo_anula_y_reabre_prellenado(): void
    {
        $factura = $this->crearFactura(100, 7);

        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-13',
            'cuenta_pago_id' => $this->banco->id,
            'referencia' => 'CHQ-123',
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 40]],
        ])->assertSessionHasNoErrors();

        $pago = CxpDocumento::where('tipo_documento', 'PAGO')->firstOrFail();

        $this->actuar()->post(route('admin.cxp.pagos.corregir', $pago))
            ->assertRedirect(route('admin.cxp.pagos.create', ['proveedor_id' => $this->proveedor->id]))
            ->assertSessionHas('status')
            ->assertSessionHasInput('referencia', 'CHQ-123');

        $this->assertSame('ANULADO', $pago->fresh()->estado);

        $factura->refresh();
        $this->assertSame('PENDIENTE', $factura->estado);
        $this->assertSame('107.00', (string) $factura->saldo);
    }

    public function test_anular_pago_con_anticipo_restaura_el_anticipo(): void
    {
        $factura = $this->crearFactura(100, 7); // total 107
        $anticipo = $this->registrarAnticipo(500);

        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-13',
            'cuenta_pago_id' => $this->banco->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
            'creditos' => [['documento_id' => $anticipo->id, 'monto' => 50]],
        ])->assertSessionHasNoErrors();

        $pago = CxpDocumento::where('tipo_documento', 'PAGO')->firstOrFail();

        // Asiento de la aplicación del anticipo (distinto del de su registro).
        $asientoAplicId = \App\Models\CxpAplicacion::where('pago_id', $pago->id)
            ->where('documento_origen_id', $anticipo->id)
            ->value('asiento_id');
        $this->assertNotNull($asientoAplicId);

        // Anular revierte el pago completo: efectivo + crédito aplicado.
        $this->actuar()->post(route('admin.cxp.pagos.anular', $pago))
            ->assertSessionHasNoErrors();

        $factura->refresh();
        $anticipo->refresh();

        $this->assertSame('ANULADO', $pago->fresh()->estado);
        $this->assertSame('PENDIENTE', $factura->estado);
        $this->assertSame('107.00', (string) $factura->saldo);

        // El anticipo recupera todo su disponible y la aplicación se borra.
        $this->assertSame('PENDIENTE', $anticipo->estado);
        $this->assertSame('500.00', (string) $anticipo->saldo);
        $this->assertSame(0, \App\Models\CxpAplicacion::where('pago_id', $pago->id)->count());
        $this->assertSame(0, \App\Models\CxpAplicacion::where('documento_origen_id', $anticipo->id)->count());

        // El asiento de aplicación del anticipo queda anulado (el de registro no).
        $this->assertSame('ANULADO', \App\Models\Asiento::find($asientoAplicId)->estado);
    }

    public function test_corregir_pago_con_credito_reabre_con_credito(): void
    {
        $factura = $this->crearFactura(100, 7); // total 107
        $anticipo = $this->registrarAnticipo(500);

        $this->actuar()->post(route('admin.cxp.pagos.store'), [
            'proveedor_id' => $this->proveedor->id,
            'fecha' => '2026-06-13',
            'cuenta_pago_id' => $this->banco->id,
            'aplicaciones' => [['documento_id' => $factura->id, 'monto' => 107]],
            'creditos' => [['documento_id' => $anticipo->id, 'monto' => 50]],
        ])->assertSessionHasNoErrors();

        $pago = CxpDocumento::where('tipo_documento', 'PAGO')->firstOrFail();

        // Corregir reabre el formulario con la factura completa (107) y el
        // crédito aplicado (50) preseleccionados.
        $this->actuar()->post(route('admin.cxp.pagos.corregir', $pago))
            ->assertRedirect(route('admin.cxp.pagos.create', ['proveedor_id' => $this->proveedor->id]))
            ->assertSessionHasInput('aplicaciones.0.monto', '107.00')
            ->assertSessionHasInput('creditos.0.documento_id', $anticipo->id)
            ->assertSessionHasInput('creditos.0.monto', '50.00');

        // Todo revertido: factura y anticipo restaurados.
        $this->assertSame('ANULADO', $pago->fresh()->estado);
        $this->assertSame('107.00', (string) $factura->fresh()->saldo);
        $this->assertSame('500.00', (string) $anticipo->fresh()->saldo);
    }

    public function test_importar_pagos_aplica_a_facturas_y_postea(): void
    {
        // Dos facturas del mismo proveedor: el Excel paga ambas con la misma
        // referencia (cheque) → un solo pago con dos aplicaciones.
        $f1 = $this->crearFactura(100, 7, 'A-2001'); // total 107
        $f2 = $this->crearFactura(50, 7, 'A-2002');  // total 53.50

        $csv = implode("\n", [
            'proveedor,ruc,numero,fecha,monto,cuenta,referencia',
            'PROVEEDOR PRUEBA,,A-2001,25/06/2026,107,10102,CHQ-001',
            'PROVEEDOR PRUEBA,,A-2002,25/06/2026,53.50,10102,CHQ-001',
        ]);

        $this->actuar()->post(route('admin.cxp.pagos.importar'), [
            'archivo' => UploadedFile::fake()->createWithContent('pagos.csv', $csv),
        ])->assertRedirect(route('admin.cxp.pagos.index'))->assertSessionHas('status');

        // Un solo pago por 160.50, estado PAGADO.
        $pago = CxpDocumento::where('tipo_documento', CxpDocumento::TIPO_PAGO)->latest('id')->first();
        $this->assertNotNull($pago);
        $this->assertSame('160.50', (string) $pago->total);
        $this->assertSame('PAGADO', $pago->estado);
        $this->assertSame('CHQ-001', $pago->referencia);

        // Ambas facturas quedaron en cero.
        $this->assertSame('0.00', (string) $f1->fresh()->saldo);
        $this->assertSame('PAGADO', $f1->fresh()->estado);
        $this->assertSame('0.00', (string) $f2->fresh()->saldo);

        // Asiento cuadrado: Dr CXP 160.50 / Cr Banco 160.50.
        $asiento = $pago->asiento;
        $this->assertNotNull($asiento);
        $this->assertSame('POSTEADO', $asiento->estado);
        $lineas = $asiento->detalle;
        $this->assertCount(2, $lineas);
        $this->assertSame($this->cxp->id, $lineas[0]->cuenta_id);
        $this->assertSame('160.50', (string) $lineas[0]->debito);
        $this->assertSame($this->banco->id, $lineas[1]->cuenta_id);
        $this->assertSame('160.50', (string) $lineas[1]->credito);
    }

    public function test_importar_pagos_es_idempotente_por_referencia(): void
    {
        $this->crearFactura(100, 7, 'A-3001'); // total 107

        $csv = "proveedor,ruc,numero,fecha,monto,cuenta,referencia\n"
             .'PROVEEDOR PRUEBA,,A-3001,25/06/2026,50,10102,CHQ-DUP';

        $this->actuar()->post(route('admin.cxp.pagos.importar'), [
            'archivo' => UploadedFile::fake()->createWithContent('p1.csv', $csv),
        ]);
        $this->actuar()->post(route('admin.cxp.pagos.importar'), [
            'archivo' => UploadedFile::fake()->createWithContent('p2.csv', $csv),
        ]);

        // El segundo import omite el pago ya registrado (misma referencia+fecha).
        $this->assertSame(1, CxpDocumento::where('tipo_documento', CxpDocumento::TIPO_PAGO)
            ->where('referencia', 'CHQ-DUP')->count());
    }

    public function test_importar_pagos_no_crea_proveedor_inexistente(): void
    {
        $csv = "proveedor,ruc,numero,fecha,monto,cuenta,referencia\n"
             .'FANTASMA SA,9-000-0000,X-1,25/06/2026,50,10102,R1';

        $this->actuar()->post(route('admin.cxp.pagos.importar'), [
            'archivo' => UploadedFile::fake()->createWithContent('p.csv', $csv),
        ])->assertRedirect(route('admin.cxp.pagos.index'));

        // No se creó ni el proveedor ni el pago; el error queda como aviso.
        $this->assertNull(Contacto::where('compania_id', $this->compania->id)
            ->where('identificacion', '9-000-0000')->first());
        $this->assertSame(0, CxpDocumento::where('tipo_documento', CxpDocumento::TIPO_PAGO)->count());
    }

    private function crearCuentaBancaria(): \App\Models\BcoCuenta
    {
        $banco = \App\Models\BcoBanco::create(['codigo' => 'BG', 'nombre' => 'Banco General', 'activo' => true]);

        return \App\Models\BcoCuenta::create([
            'compania_id' => $this->compania->id,
            'banco_id' => $banco->id,
            'cuenta_contable_id' => $this->banco->id,
            'numero_cuenta' => '04-0001',
            'nombre' => 'Cuenta corriente',
            'tipo_cuenta' => 'CORRIENTE',
            'saldo_inicial' => 0,
            'activa' => true,
        ]);
    }
}
