<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\AsientoDetalle;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\InvAlmacen;
use App\Models\InvExistencia;
use App\Models\InvMovimiento;
use App\Models\ItemProducto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reverso de movimientos manuales de inventario por TRANSACCIÓN de compensación
 * (no por cambio de estado a ANULADO): original y reverso quedan en el historial.
 */
class InventarioReversoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private InvAlmacen $almacen;

    private ItemProducto $item;

    private CuentaContable $inventario;

    private CuentaContable $contrapartida;

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

        $this->inventario    = $crear('10120', 'Inventario', 'DEBITO');
        $costoVentas         = $crear('51010', 'Costo de Ventas', 'DEBITO');
        $gasto               = $crear('60101', 'Gastos Generales', 'DEBITO');
        $this->contrapartida = $crear('30101', 'Capital', 'CREDITO');

        foreach (['INVENTARIO' => $this->inventario, 'COSTO_VENTAS' => $costoVentas, 'GASTO_DEFAULT' => $gasto] as $clave => $cuenta) {
            CuentaDefault::create(['compania_id' => $this->compania->id, 'clave' => $clave, 'cuenta_id' => $cuenta->id]);
        }

        $this->almacen = InvAlmacen::create(['compania_id' => $this->compania->id, 'codigo' => 'ALM-01', 'nombre' => 'Principal', 'activo' => true]);
        $this->item = ItemProducto::create([
            'compania_id' => $this->compania->id, 'codigo' => 'PROD-001', 'nombre' => 'Producto X',
            'tipo' => ItemProducto::TIPO_PRODUCTO, 'precio_venta' => 10, 'costo' => 5, 'activo' => true,
        ]);
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function entrada(float $cantidad, float $costo, string $fecha = '2026-06-20'): InvMovimiento
    {
        $this->actuar()->post(route('admin.inventario.movimientos.store'), [
            'almacen_id' => $this->almacen->id, 'fecha' => $fecha, 'tipo_movimiento' => 'ENTRADA',
            'cuenta_contrapartida_id' => $this->contrapartida->id,
            'lineas' => [['item_id' => $this->item->id, 'cantidad' => $cantidad, 'costo_unitario' => $costo]],
        ])->assertSessionHasNoErrors();

        return InvMovimiento::latest('id')->firstOrFail();
    }

    private function salida(float $cantidad, float $costo, string $fecha = '2026-06-21'): InvMovimiento
    {
        $this->actuar()->post(route('admin.inventario.movimientos.store'), [
            'almacen_id' => $this->almacen->id, 'fecha' => $fecha, 'tipo_movimiento' => 'SALIDA',
            'lineas' => [['item_id' => $this->item->id, 'cantidad' => $cantidad, 'costo_unitario' => $costo]],
        ])->assertSessionHasNoErrors();

        return InvMovimiento::latest('id')->firstOrFail();
    }

    private function ajuste(float $cantidad, float $costo, string $fecha = '2026-06-22'): InvMovimiento
    {
        $this->actuar()->post(route('admin.inventario.movimientos.store'), [
            'almacen_id' => $this->almacen->id, 'fecha' => $fecha, 'tipo_movimiento' => 'AJUSTE',
            'lineas' => [['item_id' => $this->item->id, 'cantidad' => $cantidad, 'costo_unitario' => $costo]],
        ])->assertSessionHasNoErrors();

        return InvMovimiento::latest('id')->firstOrFail();
    }

    private function existencia(): InvExistencia
    {
        return InvExistencia::where('almacen_id', $this->almacen->id)->where('item_id', $this->item->id)->firstOrFail();
    }

    public function test_reversar_entrada_deshace_stock_y_postea_asiento_inverso(): void
    {
        $mov = $this->entrada(5, 500); // existencia 5 @ 500
        $this->assertEqualsWithDelta(5.0, (float) $this->existencia()->cantidad, 0.001);

        $this->actuar()->post(route('admin.inventario.movimientos.reversar', $mov))->assertSessionHasNoErrors();

        // Existencia vuelve a 0; el reverso es un movimiento ENTRADA con cantidad -5.
        $this->assertEqualsWithDelta(0.0, (float) $this->existencia()->cantidad, 0.001);
        $rev = InvMovimiento::where('reversa_de_id', $mov->id)->firstOrFail();
        $this->assertSame('ENTRADA', $rev->tipo_movimiento);
        $this->assertSame('CONFIRMADO', $rev->estado);
        $this->assertEqualsWithDelta(-5.0, (float) $rev->detalle()->first()->cantidad, 0.001);

        // Ambos movimientos siguen vigentes (no ANULADO) → pista completa.
        $this->assertSame('CONFIRMADO', $mov->fresh()->estado);

        // Asiento inverso: la cuenta de inventario, debitada en el original, va al CRÉDITO.
        $debInvOrig = (float) AsientoDetalle::where('asiento_id', $mov->asiento_id)->where('cuenta_id', $this->inventario->id)->value('debito');
        $crInvRev   = (float) AsientoDetalle::where('asiento_id', $rev->asiento_id)->where('cuenta_id', $this->inventario->id)->value('credito');
        $this->assertEqualsWithDelta($debInvOrig, $crInvRev, 0.001);
        $this->assertEqualsWithDelta(2500.0, $crInvRev, 0.001);
        $this->assertSame(Asiento::ESTADO_POSTEADO, Asiento::find($rev->asiento_id)->estado);
    }

    public function test_reversar_salida_repone_stock(): void
    {
        $this->entrada(10, 500);          // existencia 10 @ 500
        $sal = $this->salida(3, 500);     // existencia 7 @ 500
        $this->assertEqualsWithDelta(7.0, (float) $this->existencia()->cantidad, 0.001);

        $this->actuar()->post(route('admin.inventario.movimientos.reversar', $sal))->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(10.0, (float) $this->existencia()->cantidad, 0.001);
        $rev = InvMovimiento::where('reversa_de_id', $sal->id)->firstOrFail();
        $this->assertSame('ENTRADA', $rev->tipo_movimiento);
        $this->assertEqualsWithDelta(3.0, (float) $rev->detalle()->first()->cantidad, 0.001);
    }

    public function test_reversar_ajuste_restaura_snapshot_previo(): void
    {
        $this->entrada(5, 500);            // existencia 5 @ 500
        $aj = $this->ajuste(8, 520);       // existencia 8 @ 520 (snapshot previo 5 @ 500)
        $this->assertEqualsWithDelta(8.0, (float) $this->existencia()->cantidad, 0.001);

        $this->actuar()->post(route('admin.inventario.movimientos.reversar', $aj))->assertSessionHasNoErrors();

        $ex = $this->existencia();
        $this->assertEqualsWithDelta(5.0, (float) $ex->cantidad, 0.001);
        $this->assertEqualsWithDelta(500.0, (float) $ex->costo_promedio, 0.001);
        $rev = InvMovimiento::where('reversa_de_id', $aj->id)->firstOrFail();
        $this->assertSame('AJUSTE', $rev->tipo_movimiento);
    }

    public function test_reversar_ajuste_con_movimientos_posteriores_se_bloquea(): void
    {
        $this->entrada(5, 500);
        $aj = $this->ajuste(8, 520, '2026-06-22');
        $this->entrada(1, 500, '2026-06-23'); // movimiento POSTERIOR al ajuste

        $this->actuar()->post(route('admin.inventario.movimientos.reversar', $aj))
            ->assertSessionHasErrors('movimiento');

        $this->assertSame(0, InvMovimiento::where('reversa_de_id', $aj->id)->count());
    }

    public function test_no_se_puede_reversar_dos_veces(): void
    {
        $mov = $this->entrada(5, 500);
        $this->actuar()->post(route('admin.inventario.movimientos.reversar', $mov))->assertSessionHasNoErrors();

        $this->actuar()->post(route('admin.inventario.movimientos.reversar', $mov))
            ->assertSessionHasErrors('movimiento');

        $this->assertSame(1, InvMovimiento::where('reversa_de_id', $mov->id)->count());
    }

    public function test_no_se_puede_reversar_un_reverso(): void
    {
        $mov = $this->entrada(5, 500);
        $this->actuar()->post(route('admin.inventario.movimientos.reversar', $mov))->assertSessionHasNoErrors();
        $rev = InvMovimiento::where('reversa_de_id', $mov->id)->firstOrFail();

        $this->actuar()->post(route('admin.inventario.movimientos.reversar', $rev))
            ->assertSessionHasErrors('movimiento');
    }

    public function test_reversar_entrada_con_stock_ya_consumido_se_bloquea(): void
    {
        $mov = $this->entrada(5, 500);   // existencia 5
        $this->salida(4, 500);           // existencia 1 (se consumieron 4)

        // Reversar la entrada llevaría la existencia a 1 - 5 = -4 → bloqueado.
        $this->actuar()->post(route('admin.inventario.movimientos.reversar', $mov))
            ->assertSessionHasErrors('movimiento');

        $this->assertEqualsWithDelta(1.0, (float) $this->existencia()->cantidad, 0.001);
        $this->assertSame(0, InvMovimiento::where('reversa_de_id', $mov->id)->count());
    }
}
