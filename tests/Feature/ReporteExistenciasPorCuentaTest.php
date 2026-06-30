<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReporteExistenciasPorCuentaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function cuenta(string $codigo, string $nombre): CuentaContable
    {
        return CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'nivel' => 3,
            'naturaleza' => 'DEBITO',
            'permite_movimiento' => true,
            'activa' => true,
        ]);
    }

    private function item(string $codigo, string $nombre, ?int $cuentaInventarioId, ?int $companiaId = null): int
    {
        return DB::table('item_productos_servicios')->insertGetId([
            'compania_id' => $companiaId ?? $this->compania->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo' => 'PRODUCTO',
            'cuenta_inventario_id' => $cuentaInventarioId,
            'extra' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function almacen(string $codigo, ?int $companiaId = null): int
    {
        return DB::table('inv_almacenes')->insertGetId([
            'compania_id' => $companiaId ?? $this->compania->id,
            'codigo' => $codigo,
            'nombre' => 'Almacén '.$codigo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function existencia(int $itemId, int $almacenId, float $cantidad, float $costo, ?int $companiaId = null): void
    {
        DB::table('inv_existencias')->insert([
            'compania_id' => $companiaId ?? $this->compania->id,
            'item_id' => $itemId,
            'almacen_id' => $almacenId,
            'cantidad' => $cantidad,
            'costo_promedio' => $costo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_agrupa_por_cuenta_y_consolida_promedio_ponderado_entre_almacenes(): void
    {
        $merc = $this->cuenta('10401', 'Mercadería');
        $a1 = $this->almacen('A1');
        $a2 = $this->almacen('A2');
        $it = $this->item('LAP-001', 'Laptop', $merc->id);

        // 11 @ 622 en A1 y 2 @ 620 en A2 → total 13, valor 8.082.
        $this->existencia($it, $a1, 11, 622);
        $this->existencia($it, $a2, 2, 620);

        $response = $this->actuar()->get(route('admin.reportes.existencias-por-cuenta'))->assertOk();

        $grupos = $response->viewData('grupos');
        $this->assertCount(1, $grupos);
        $this->assertSame($merc->id, $grupos[0]['cuenta_id']);
        $this->assertCount(1, $grupos[0]['lineas']);

        $linea = $grupos[0]['lineas'][0];
        $this->assertEqualsWithDelta(13, $linea['cantidad'], 0.0001);
        $this->assertEqualsWithDelta(8082.0, $linea['costo'], 0.01);
        $this->assertEqualsWithDelta(621.6923, $linea['costo_unitario'], 0.0001);

        $this->assertEqualsWithDelta(13, $grupos[0]['totalCantidad'], 0.0001);
        $this->assertEqualsWithDelta(8082.0, $grupos[0]['totalCosto'], 0.01);
        $this->assertEqualsWithDelta(8082.0, $response->viewData('totalCosto'), 0.01);
    }

    public function test_separa_grupos_por_cuenta_y_ordena_por_codigo(): void
    {
        $mercB = $this->cuenta('10402', 'Repuestos');
        $mercA = $this->cuenta('10401', 'Mercadería');
        $a1 = $this->almacen('A1');

        $itA = $this->item('AAA-001', 'Producto A', $mercA->id);
        $itB = $this->item('BBB-001', 'Producto B', $mercB->id);
        $this->existencia($itA, $a1, 5, 10);
        $this->existencia($itB, $a1, 3, 20);

        $grupos = $this->actuar()->get(route('admin.reportes.existencias-por-cuenta'))->viewData('grupos');

        $this->assertCount(2, $grupos);
        $this->assertSame('10401', $grupos[0]['cuenta_codigo']);
        $this->assertSame('10402', $grupos[1]['cuenta_codigo']);
    }

    public function test_items_sin_cuenta_de_inventario_van_al_grupo_sin_cuenta_al_final(): void
    {
        $merc = $this->cuenta('10401', 'Mercadería');
        $a1 = $this->almacen('A1');

        $conCuenta = $this->item('CON-001', 'Con cuenta', $merc->id);
        $sinCuenta = $this->item('SIN-001', 'Sin cuenta', null);
        $this->existencia($conCuenta, $a1, 5, 10);
        $this->existencia($sinCuenta, $a1, 2, 15);

        $grupos = $this->actuar()->get(route('admin.reportes.existencias-por-cuenta'))->viewData('grupos');

        $this->assertCount(2, $grupos);
        $this->assertNull($grupos[1]['cuenta_id']);
        $this->assertSame('Sin cuenta de inventario asignada', $grupos[1]['cuenta_nombre']);
        $this->assertSame('SIN-001', $grupos[1]['lineas'][0]['codigo']);
    }

    public function test_excluye_items_en_cero_salvo_que_se_pida_incluirlos(): void
    {
        $merc = $this->cuenta('10401', 'Mercadería');
        $a1 = $this->almacen('A1');

        $conSaldo = $this->item('CON-001', 'Con saldo', $merc->id);
        $enCero = $this->item('CERO-001', 'En cero', $merc->id);
        $this->existencia($conSaldo, $a1, 5, 10);
        $this->existencia($enCero, $a1, 0, 10);

        $grupos = $this->actuar()->get(route('admin.reportes.existencias-por-cuenta'))->viewData('grupos');
        $this->assertCount(1, $grupos[0]['lineas']);
        $this->assertSame('CON-001', $grupos[0]['lineas'][0]['codigo']);

        $grupos = $this->actuar()
            ->get(route('admin.reportes.existencias-por-cuenta', ['incluir_ceros' => 1]))
            ->viewData('grupos');
        $this->assertCount(2, $grupos[0]['lineas']);
    }

    public function test_filtra_por_busqueda_de_codigo_o_nombre(): void
    {
        $merc = $this->cuenta('10401', 'Mercadería');
        $a1 = $this->almacen('A1');

        $lap = $this->item('LAP-001', 'Laptop Dell', $merc->id);
        $mouse = $this->item('MOU-001', 'Mouse inalámbrico', $merc->id);
        $this->existencia($lap, $a1, 5, 100);
        $this->existencia($mouse, $a1, 10, 5);

        $grupos = $this->actuar()
            ->get(route('admin.reportes.existencias-por-cuenta', ['q' => 'laptop']))
            ->viewData('grupos');

        $this->assertCount(1, $grupos);
        $this->assertCount(1, $grupos[0]['lineas']);
        $this->assertSame('LAP-001', $grupos[0]['lineas'][0]['codigo']);
    }

    public function test_no_muestra_existencias_de_otra_compania(): void
    {
        $otra = Compania::create(['nombre' => 'OTRA', 'activa' => true]);
        $merc = $this->cuenta('10401', 'Mercadería');
        $almOtra = $this->almacen('X1', $otra->id);
        $itOtra = $this->item('AJENO-001', 'Ajeno', null, $otra->id);
        $this->existencia($itOtra, $almOtra, 99, 100, $otra->id);

        $a1 = $this->almacen('A1');
        $mio = $this->item('MIO-001', 'Mío', $merc->id);
        $this->existencia($mio, $a1, 3, 50);

        $response = $this->actuar()->get(route('admin.reportes.existencias-por-cuenta'))->assertOk();
        $grupos = $response->viewData('grupos');

        $this->assertCount(1, $grupos);
        $this->assertCount(1, $grupos[0]['lineas']);
        $this->assertSame('MIO-001', $grupos[0]['lineas'][0]['codigo']);
        $this->assertEqualsWithDelta(150.0, $response->viewData('totalCosto'), 0.01);
    }

    public function test_cantidad_negativa_se_muestra_siempre_aunque_no_se_incluyan_ceros(): void
    {
        $merc = $this->cuenta('10401', 'Mercadería');
        $a1 = $this->almacen('A1');
        $it = $this->item('NEG-001', 'Sobrevendido', $merc->id);
        $this->existencia($it, $a1, -4, 25);

        $grupos = $this->actuar()->get(route('admin.reportes.existencias-por-cuenta'))->viewData('grupos');

        $this->assertCount(1, $grupos[0]['lineas']);
        $this->assertEqualsWithDelta(-4, $grupos[0]['lineas'][0]['cantidad'], 0.0001);
    }
}
