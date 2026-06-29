<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\InvAlmacen;
use App\Models\InvExistencia;
use App\Models\InvMovimiento;
use App\Models\InvMovimientoDetalle;
use App\Models\ItemProducto;
use App\Models\User;
use App\Services\AsientoAutomatico;
use App\Services\InventarioCompras;
use App\Services\InventarioVentas;
use App\Services\RecalculadorCostosInventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecalculoCostosInventarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private CuentaContable $inv;

    private CuentaContable $costo;

    private ItemProducto $item;

    private InvAlmacen $almacen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);

        $crear = fn (string $codigo, string $nombre) => CuentaContable::create([
            'compania_id' => $this->compania->id, 'codigo' => $codigo, 'nombre' => $nombre,
            'nivel' => 3, 'naturaleza' => 'DEBITO', 'permite_movimiento' => true, 'conciliable' => false, 'activa' => true,
        ]);

        $this->inv = $crear('10112', 'Inventario');
        $this->costo = $crear('50103', 'Costo de Ventas');
        CuentaDefault::create(['compania_id' => $this->compania->id, 'clave' => 'INVENTARIO', 'cuenta_id' => $this->inv->id]);
        CuentaDefault::create(['compania_id' => $this->compania->id, 'clave' => 'COSTO_VENTAS', 'cuenta_id' => $this->costo->id]);

        $this->almacen = InvAlmacen::create(['compania_id' => $this->compania->id, 'codigo' => 'ALM-01', 'nombre' => 'Principal', 'activo' => true]);
        $this->item = ItemProducto::create([
            'compania_id' => $this->compania->id, 'codigo' => 'PROD-007', 'nombre' => 'Telefono S25',
            'tipo' => ItemProducto::TIPO_PRODUCTO, 'precio_venta' => 685, 'costo' => 200,
            'cuenta_inventario_id' => $this->inv->id, 'cuenta_costo_venta_id' => $this->costo->id, 'activo' => true,
        ]);
    }

    /** Entrada de compra (sin asiento, igual que el flujo invoice-driven). */
    private function entrada(string $fecha, float $cant, float $costo, int $docId): void
    {
        app(InventarioCompras::class)->registrarEntrada(
            $this->compania->id, $this->almacen->id, $fecha,
            [['item_id' => $this->item->id, 'cantidad' => $cant, 'costo_unitario' => $costo]],
            null, 'cxp_documentos', $docId, $this->admin,
        );
    }

    /** Venta: postea el asiento de costo (Dr Costo / Cr Inventario) y descuenta stock. */
    private function venta(string $fecha, float $cant, int $docId): void
    {
        $ventas = app(InventarioVentas::class);
        $calc = $ventas->calcular($this->compania->id, $this->almacen->id, [['item_id' => $this->item->id, 'cantidad' => $cant]]);
        $asiento = app(AsientoAutomatico::class)->postear(
            $this->compania->id, $fecha, 'Costo venta', null, $calc['lineasAsiento'],
            'CXC', 'ventas_facturas', $docId, $this->admin,
        );
        $ventas->registrar($this->compania->id, $this->almacen->id, $fecha, $calc['detalle'], $asiento->id, 'ventas_facturas', $docId, $this->admin);
    }

    private function costoSalida(int $docId): float
    {
        $mov = InvMovimiento::where('documento_origen', 'ventas_facturas')->where('documento_id', $docId)->firstOrFail();

        return (float) InvMovimientoDetalle::where('movimiento_id', $mov->id)->value('costo_unitario');
    }

    public function test_recalculo_corrige_costo_de_salida_por_compra_backdated(): void
    {
        // Escenario real (cía 8 WIN SOFT, PROD-007): dos compras de junio, una venta,
        // luego una COMPRA con fecha de ABRIL ingresada después (back-dated), y otra venta.
        $this->entrada('2026-06-28', 1, 200, 698);
        $this->entrada('2026-06-28', 10, 199, 699);
        $this->venta('2026-06-28', 1, 1562);
        $this->entrada('2026-04-03', 1, 300, 700); // back-dated
        $this->venta('2026-06-28', 1, 1563);

        // Estado BUGGY (orden de inserción): costos de salida 199.0909 y 208.2645.
        $this->assertEqualsWithDelta(199.0909, $this->costoSalida(1562), 0.0001);
        $this->assertEqualsWithDelta(208.2645, $this->costoSalida(1563), 0.0001);
        $exist = InvExistencia::where('item_id', $this->item->id)->firstOrFail();
        $this->assertEqualsWithDelta(208.2645, (float) $exist->costo_promedio, 0.0001);
        $this->assertEqualsWithDelta(10.0, (float) $exist->cantidad, 0.0001);

        // Recalcular por fecha.
        $recalc = app(RecalculadorCostosInventario::class);
        $plan = $recalc->analizar($this->compania->id, $this->item->id, $this->almacen->id);
        $this->assertFalse($plan['sinCambios']);
        $this->assertCount(2, $plan['cambios']);

        $asiento = DB::transaction(fn () => $recalc->aplicar($this->compania->id, $plan, '2026-06-28', $this->admin));

        // Ambas salidas quedan a 207.50.
        $this->assertEqualsWithDelta(207.50, $this->costoSalida(1562), 0.0001);
        $this->assertEqualsWithDelta(207.50, $this->costoSalida(1563), 0.0001);

        // Existencia final: 10 @ 207.50.
        $exist->refresh();
        $this->assertEqualsWithDelta(207.50, (float) $exist->costo_promedio, 0.0001);
        $this->assertEqualsWithDelta(10.0, (float) $exist->cantidad, 0.0001);

        // Asiento de ajuste: Dr Costo 7.65 / Cr Inventario 7.65, cuadrado.
        $this->assertNotNull($asiento);
        $this->assertEqualsWithDelta((float) $asiento->total_debito, (float) $asiento->total_credito, 0.001);
        $this->assertEqualsWithDelta(7.65, (float) $asiento->detalle->where('cuenta_id', $this->costo->id)->sum('debito'), 0.001);
        $this->assertEqualsWithDelta(7.65, (float) $asiento->detalle->where('cuenta_id', $this->inv->id)->sum('credito'), 0.001);

        // GL: Costo de Ventas total = 2 × 207.50 = 415.00; Inventario acreditado = 415.00.
        $costoDr = (float) DB::table('cgl_asientos_detalle as ad')->join('cgl_asientos as a', 'a.id', '=', 'ad.asiento_id')
            ->where('a.estado', Asiento::ESTADO_POSTEADO)->where('ad.cuenta_id', $this->costo->id)->sum('ad.debito');
        $invCr = (float) DB::table('cgl_asientos_detalle as ad')->join('cgl_asientos as a', 'a.id', '=', 'ad.asiento_id')
            ->where('a.estado', Asiento::ESTADO_POSTEADO)->where('ad.cuenta_id', $this->inv->id)->sum('ad.credito');
        $this->assertEqualsWithDelta(415.00, $costoDr, 0.001);
        $this->assertEqualsWithDelta(415.00, $invCr, 0.001);

        // Asientos ORIGINALES intactos (auditoría): siguen posteados, no anulados.
        $this->assertSame(0, Asiento::where('estado', Asiento::ESTADO_ANULADO)->count());

        // Idempotente: re-analizar no encuentra diferencias.
        $this->assertTrue($recalc->analizar($this->compania->id, $this->item->id, $this->almacen->id)['sinCambios']);
    }
}
