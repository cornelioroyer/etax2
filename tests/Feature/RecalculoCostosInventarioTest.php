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

        // Con la información disponible hasta ahora, la venta costea a 199.0909.
        $this->assertEqualsWithDelta(199.0909, $this->costoSalida(1562), 0.0001);

        // La compra back-dated dispara la AUTO-RECONCILIACIÓN (ReconciliadorCostosInventario,
        // enganchada en InventarioCompras::registrarEntrada — ver [[etax2-inventario-reconcile-backdated]]):
        // antes de que termine esta llamada, ya recosteó la venta 1562 a 207.50 y posteó un
        // asiento de ajuste propio (sin tocar el original, que queda intacto para auditoría).
        $this->entrada('2026-04-03', 1, 300, 700); // back-dated

        $this->assertEqualsWithDelta(207.50, $this->costoSalida(1562), 0.0001);
        $exist = InvExistencia::where('item_id', $this->item->id)->firstOrFail();
        $this->assertEqualsWithDelta(207.50, (float) $exist->costo_promedio, 0.0001);
        $this->assertEqualsWithDelta(11.0, (float) $exist->cantidad, 0.0001);

        $ajuste = Asiento::where('origen_tabla', 'inv_recalculo_costos')->sole();
        $this->assertSame('POSTEADO', $ajuste->estado);
        $this->assertEqualsWithDelta((float) $ajuste->total_debito, (float) $ajuste->total_credito, 0.001);
        $this->assertEqualsWithDelta(8.41, (float) $ajuste->detalle->where('cuenta_id', $this->costo->id)->sum('debito'), 0.001);
        $this->assertEqualsWithDelta(8.41, (float) $ajuste->detalle->where('cuenta_id', $this->inv->id)->sum('credito'), 0.001);

        // La venta original (199.09) queda intacta: el ajuste es un asiento aparte.
        $original = Asiento::where('origen_tabla', 'ventas_facturas')->where('origen_id', 1562)->sole();
        $this->assertSame('POSTEADO', $original->estado);
        $this->assertEqualsWithDelta(199.09, (float) $original->total_debito, 0.001);

        // La segunda venta ya nace bien costeada (ya no hay nada "buggy" que arrastrar).
        $this->venta('2026-06-28', 1, 1563);
        $this->assertEqualsWithDelta(207.50, $this->costoSalida(1563), 0.0001);

        // Existencia final: 10 @ 207.50.
        $exist->refresh();
        $this->assertEqualsWithDelta(207.50, (float) $exist->costo_promedio, 0.0001);
        $this->assertEqualsWithDelta(10.0, (float) $exist->cantidad, 0.0001);

        // El recalculador MANUAL, corrido después, confirma que no quedó nada pendiente:
        // la auto-reconciliación ya dejó el costeo convergido (sigue existiendo para
        // corregir datos LEGACY previos al auto-reconcile; ver el comando
        // inventario:recalcular-costos y el botón del Kardex).
        $recalc = app(RecalculadorCostosInventario::class);
        $plan = $recalc->analizar($this->compania->id, $this->item->id, $this->almacen->id);
        $this->assertTrue($plan['sinCambios']);
        $this->assertCount(0, $plan['cambios']);

        // GL: Costo de Ventas total = 199.09 (original) + 8.41 (ajuste) + 207.50 (venta 2) = 415.00.
        $costoDr = (float) DB::table('cgl_asientos_detalle as ad')->join('cgl_asientos as a', 'a.id', '=', 'ad.asiento_id')
            ->where('a.estado', Asiento::ESTADO_POSTEADO)->where('ad.cuenta_id', $this->costo->id)->sum('ad.debito');
        $invCr = (float) DB::table('cgl_asientos_detalle as ad')->join('cgl_asientos as a', 'a.id', '=', 'ad.asiento_id')
            ->where('a.estado', Asiento::ESTADO_POSTEADO)->where('ad.cuenta_id', $this->inv->id)->sum('ad.credito');
        $this->assertEqualsWithDelta(415.00, $costoDr, 0.001);
        $this->assertEqualsWithDelta(415.00, $invCr, 0.001);

        // Asientos ORIGINALES intactos (auditoría): siguen posteados, ninguno anulado.
        $this->assertSame(0, Asiento::where('estado', Asiento::ESTADO_ANULADO)->count());
    }

    /**
     * Regresión: una existencia con saldo NUNCA registrado como movimiento
     * (saldo inicial sembrado/migrado directo en inv_existencias, sin su
     * inv_movimientos — patrón de los loaders Peachtree/Sage y de los tests que
     * siembran existencia con InvExistencia::create()) NO debe perderse cuando
     * algo dispara una reconciliación. Antes de la salvaguarda, analizar()
     * arrancaba el replay en 0/0 y pisaba la existencia con el resultado
     * incompleto (ver caso real: anular una Nota de Crédito de devolución,
     * tests/Feature/VentaTest::test_anular_nota_credito_con_devolucion_revierte_la_entrada).
     */
    public function test_existencia_sin_movimiento_de_respaldo_no_se_sobreescribe_al_reconciliar(): void
    {
        // Saldo inicial sembrado directo, sin inv_movimientos que lo respalde.
        InvExistencia::create([
            'compania_id' => $this->compania->id, 'almacen_id' => $this->almacen->id, 'item_id' => $this->item->id,
            'cantidad' => 10, 'costo_promedio' => 5,
        ]);

        // Una salida REAL sí queda en el kárdex y deja la existencia en 6@5
        // (actualización incremental, correcta).
        $this->venta('2026-06-28', 4, 9001);

        $exist = InvExistencia::where('item_id', $this->item->id)->where('almacen_id', $this->almacen->id)->firstOrFail();
        $this->assertEqualsWithDelta(6.0, (float) $exist->cantidad, 0.0001);
        $this->assertEqualsWithDelta(5.0, (float) $exist->costo_promedio, 0.0001);

        // Forzar el recálculo (como dispara cualquier reversa con fechaOperacion
        // null): el replay solo ve la salida de 4 y arranca en 0/0 → calcularía
        // -4@0 si no fuera por la salvaguarda.
        $recalc = app(RecalculadorCostosInventario::class);
        $plan = $recalc->analizar($this->compania->id, $this->item->id, $this->almacen->id);

        $this->assertNotEmpty($plan['noReconciliables']);
        $this->assertSame($this->item->id, $plan['noReconciliables'][0]['item_id']);
        $this->assertEqualsWithDelta(-4.0, $plan['noReconciliables'][0]['cantidad_calculada'], 0.0001);
        $this->assertCount(0, $plan['cambios']); // la salida NO se "corrige" con un baseline no confiable
        $this->assertArrayNotHasKey($this->item->id.'|'.$this->almacen->id, $plan['existencias']);
        $this->assertTrue($plan['sinCambios']);

        DB::transaction(fn () => $recalc->aplicar($this->compania->id, $plan, now()->toDateString(), $this->admin));

        // La existencia real queda INTACTA: sigue en 6@5, no en -4@0.
        $exist->refresh();
        $this->assertEqualsWithDelta(6.0, (float) $exist->cantidad, 0.0001);
        $this->assertEqualsWithDelta(5.0, (float) $exist->costo_promedio, 0.0001);

        // El costo de la salida tampoco se tocó (ya era correcto).
        $this->assertEqualsWithDelta(5.0, $this->costoSalida(9001), 0.0001);

        // No se posteó ningún asiento de ajuste fantasma por la diferencia.
        $this->assertSame(0, Asiento::where('origen_tabla', 'inv_recalculo_costos')->count());
    }

    /**
     * Un AJUSTE (conteo físico) SIGUE anclando el replay aunque la existencia
     * almacenada no cuadre: la salvaguarda de "no reconciliable" solo aplica
     * cuando NO hay un ajuste que respalde el reinicio. Fija el límite exacto de
     * la salvaguarda nueva para que no se vuelva demasiado conservadora.
     */
    public function test_ajuste_sigue_permitiendo_corregir_la_cantidad_aunque_no_cuadre(): void
    {
        DB::transaction(function () {
            $mov = InvMovimiento::create([
                'compania_id' => $this->compania->id, 'almacen_id' => $this->almacen->id, 'fecha' => '2026-06-20',
                'tipo_movimiento' => 'AJUSTE', 'estado' => 'CONFIRMADO', 'created_by' => $this->admin->email,
            ]);
            InvMovimientoDetalle::create([
                'movimiento_id' => $mov->id, 'item_id' => $this->item->id,
                'cantidad' => 10, 'costo_unitario' => 5, 'total' => 50, 'created_by' => $this->admin->email,
            ]);
        });

        // La existencia quedó desincronizada (simula cualquier drift previo al
        // ajuste): el replay (AJUSTE → 10@5) NO cuadra con esto, pero como SÍ hay
        // un ajuste, debe reconciliarse igual en vez de excluirse.
        InvExistencia::create([
            'compania_id' => $this->compania->id, 'almacen_id' => $this->almacen->id, 'item_id' => $this->item->id,
            'cantidad' => 999, 'costo_promedio' => 1,
        ]);

        $recalc = app(RecalculadorCostosInventario::class);
        $plan = $recalc->analizar($this->compania->id, $this->item->id, $this->almacen->id);

        $this->assertEmpty($plan['noReconciliables']);
        $key = $this->item->id.'|'.$this->almacen->id;
        $this->assertArrayHasKey($key, $plan['existencias']);
        $this->assertEqualsWithDelta(10.0, $plan['existencias'][$key]['cantidad'], 0.0001);
        $this->assertEqualsWithDelta(5.0, $plan['existencias'][$key]['costo_promedio'], 0.0001);
    }
}
