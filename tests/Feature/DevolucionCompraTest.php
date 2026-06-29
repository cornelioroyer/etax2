<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxpDocumento;
use App\Models\InvAlmacen;
use App\Models\InvExistencia;
use App\Models\ItemProducto;
use App\Models\TipoContacto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Devolución de compra: NC de CxP que reduce el saldo por pagar y descuenta el
 * inventario devuelto (Dr CxP / Cr Inventario al promedio / Cr ITBMS / variación).
 */
class DevolucionCompraTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private Contacto $proveedor;

    private InvAlmacen $almacen;

    private ItemProducto $item;

    private CuentaContable $inventario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);

        $crear = fn (string $codigo, string $nombre, string $naturaleza) => CuentaContable::create([
            'compania_id' => $this->compania->id, 'codigo' => $codigo, 'nombre' => $nombre,
            'nivel' => 3, 'naturaleza' => $naturaleza, 'permite_movimiento' => true,
            'conciliable' => false, 'activa' => true,
        ]);

        $cxp        = $crear('20101', 'Cuentas por Pagar', 'CREDITO');
        $gasto      = $crear('60101', 'Gastos Generales', 'DEBITO');
        $itbms      = $crear('10113', 'ITBMS Credito Fiscal', 'DEBITO');
        $banco      = $crear('10102', 'Bancos', 'DEBITO');
        $this->inventario = $crear('10120', 'Inventario', 'DEBITO');

        foreach (['CXP' => $cxp, 'GASTO_DEFAULT' => $gasto, 'ITBMS_CREDITO' => $itbms, 'BANCO_DEFAULT' => $banco, 'INVENTARIO' => $this->inventario] as $clave => $cuenta) {
            CuentaDefault::create(['compania_id' => $this->compania->id, 'clave' => $clave, 'cuenta_id' => $cuenta->id]);
        }

        $tipoProveedor = TipoContacto::firstOrCreate(['codigo' => 'PROVEEDOR'], ['nombre' => 'Proveedor']);
        $this->proveedor = Contacto::create([
            'compania_id' => $this->compania->id, 'codigo' => 'PRV-001', 'nombre' => 'PROVEEDOR PRUEBA', 'activo' => true,
        ]);
        $this->proveedor->tipos()->attach($tipoProveedor->id);

        $this->almacen = InvAlmacen::create(['compania_id' => $this->compania->id, 'codigo' => 'ALM-01', 'nombre' => 'Principal', 'activo' => true]);
        $this->item = ItemProducto::create([
            'compania_id' => $this->compania->id, 'codigo' => 'PROD-001', 'nombre' => 'Laptop',
            'tipo' => ItemProducto::TIPO_PRODUCTO, 'precio_venta' => 80, 'costo' => 50, 'activo' => true,
            'cuenta_inventario_id' => $this->inventario->id,
        ]);
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    /** Crea y contabiliza una factura con una línea de producto (entra a inventario). */
    private function comprar(float $cantidad, float $precio = 50, int $tasa = 7): CxpDocumento
    {
        $this->actuar()->post(route('admin.cxp.facturas.store'), [
            'proveedor_id' => $this->proveedor->id,
            'numero' => 'A-'.fake()->unique()->numerify('#####'),
            'fecha' => '2026-06-12',
            'fecha_vencimiento' => '2026-07-12',
            'lineas' => [
                ['descripcion' => 'Compra', 'item_id' => $this->item->id, 'cantidad' => $cantidad, 'precio_unitario' => $precio, 'tasa_itbms' => $tasa, 'cuenta_id' => $this->inventario->id],
            ],
        ])->assertSessionHasNoErrors();

        $factura = CxpDocumento::where('tipo_documento', 'FACTURA')->latest('id')->firstOrFail();
        $this->actuar()->post(route('admin.cxp.facturas.contabilizar', $factura))->assertSessionHasNoErrors();

        return $factura->fresh();
    }

    private function existencia(): InvExistencia
    {
        return InvExistencia::where('almacen_id', $this->almacen->id)->where('item_id', $this->item->id)->firstOrFail();
    }

    private function detalleProductoId(CxpDocumento $factura): int
    {
        return (int) $factura->detalle()->whereNotNull('item_id')->value('id');
    }

    public function test_devolucion_parcial_reduce_saldo_y_existencia(): void
    {
        $factura = $this->comprar(10, 50, 7);     // total 535, stock 10 @ 50
        $this->assertEqualsWithDelta(10.0, (float) $this->existencia()->cantidad, 0.001);

        $this->actuar()->post(route('admin.cxp.facturas.devolucion.store', $factura), [
            'fecha' => '2026-06-15',
            'lineas' => [['detalle_id' => $this->detalleProductoId($factura), 'cantidad' => 4]],
        ])->assertSessionHasNoErrors();

        // Existencia: 10 - 4 = 6.
        $this->assertEqualsWithDelta(6.0, (float) $this->existencia()->cantidad, 0.001);

        // NC de devolución: base 200, ITBMS 14, total 214.
        $nc = CxpDocumento::where('tipo_documento', CxpDocumento::TIPO_NOTA_CREDITO)->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(200.0, (float) $nc->subtotal, 0.01);
        $this->assertEqualsWithDelta(14.0, (float) $nc->impuesto, 0.01);
        $this->assertEqualsWithDelta(214.0, (float) $nc->total, 0.01);

        // Asiento cuadrado y aplicado: saldo factura 535 - 214 = 321 (PARCIAL).
        $asiento = $nc->asiento;
        $this->assertSame('POSTEADO', $asiento->estado);
        $this->assertEqualsWithDelta((float) $asiento->total_debito, (float) $asiento->total_credito, 0.01);
        $this->assertTrue($asiento->detalle->contains(fn ($l) => $l->cuenta_id === $this->inventario->id && (float) $l->credito === 200.0));

        $factura->refresh();
        $this->assertEqualsWithDelta(321.0, (float) $factura->saldo, 0.01);
        $this->assertSame('PARCIAL', $factura->estado);
    }

    public function test_anular_devolucion_repone_stock_y_saldo(): void
    {
        $factura = $this->comprar(10, 50, 7);
        $this->actuar()->post(route('admin.cxp.facturas.devolucion.store', $factura), [
            'fecha' => '2026-06-15',
            'lineas' => [['detalle_id' => $this->detalleProductoId($factura), 'cantidad' => 4]],
        ])->assertSessionHasNoErrors();

        $nc = CxpDocumento::where('tipo_documento', CxpDocumento::TIPO_NOTA_CREDITO)->latest('id')->firstOrFail();

        $this->actuar()->post(route('admin.cxp.notas.anular', $nc))->assertSessionHasNoErrors();

        // Stock repuesto a 10 y saldo de la factura de vuelta a 535.
        $this->assertEqualsWithDelta(10.0, (float) $this->existencia()->cantidad, 0.001);
        $factura->refresh();
        $this->assertEqualsWithDelta(535.0, (float) $factura->saldo, 0.01);
        $this->assertSame('ANULADO', $nc->fresh()->estado);
    }

    public function test_no_se_puede_devolver_mas_que_la_existencia(): void
    {
        $factura = $this->comprar(5, 50, 7);          // stock 5
        $this->existencia()->update(['cantidad' => 2]); // se consumieron 3 → quedan 2

        $this->actuar()->post(route('admin.cxp.facturas.devolucion.store', $factura), [
            'fecha' => '2026-06-15',
            'lineas' => [['detalle_id' => $this->detalleProductoId($factura), 'cantidad' => 4]],
        ])->assertSessionHasErrors('lineas');

        $this->assertSame(0, CxpDocumento::where('tipo_documento', CxpDocumento::TIPO_NOTA_CREDITO)->count());
        $this->assertEqualsWithDelta(2.0, (float) $this->existencia()->cantidad, 0.001);
    }
}
