<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExistenciasConsolidadoTest extends TestCase
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

    private function almacen(string $codigo, ?int $companiaId = null): int
    {
        return DB::table('inv_almacenes')->insertGetId([
            'compania_id' => $companiaId ?? $this->compania->id,
            'codigo'      => $codigo,
            'nombre'      => 'Almacén '.$codigo,
            'activo'      => true,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function item(string $codigo, ?int $companiaId = null): int
    {
        return DB::table('item_productos_servicios')->insertGetId([
            'compania_id' => $companiaId ?? $this->compania->id,
            'codigo'      => $codigo,
            'nombre'      => 'Producto '.$codigo,
            'tipo'        => 'PRODUCTO',
            'extra'       => '{}',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function existencia(int $itemId, int $almacenId, float $cantidad, float $costo, ?int $companiaId = null): void
    {
        DB::table('inv_existencias')->insert([
            'compania_id'    => $companiaId ?? $this->compania->id,
            'item_id'        => $itemId,
            'almacen_id'     => $almacenId,
            'cantidad'       => $cantidad,
            'costo_promedio' => $costo,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    public function test_consolida_un_item_en_varios_almacenes_con_promedio_ponderado(): void
    {
        $a1 = $this->almacen('A1');
        $a2 = $this->almacen('A2');
        $it = $this->item('LAP-001');

        // 11 @ 622 en A1 y 2 @ 620 en A2 → total 13, valor 8.082, prom. ponderado 621.6923...
        $this->existencia($it, $a1, 11, 622);
        $this->existencia($it, $a2, 2, 620);

        $response = $this->actuar()->get(route('admin.inventario.existencias.consolidado'))->assertOk();

        $filas = $response->viewData('filas');
        $this->assertCount(1, $filas);

        $f = $filas[0];
        $this->assertSame('LAP-001', $f['codigo']);
        $this->assertEqualsWithDelta(11, $f['porAlmacen'][$a1], 0.0001);
        $this->assertEqualsWithDelta(2, $f['porAlmacen'][$a2], 0.0001);
        $this->assertEqualsWithDelta(13, $f['totalCantidad'], 0.0001);
        $this->assertEqualsWithDelta(8082.0, $f['valor'], 0.01);          // 11*622 + 2*620
        $this->assertEqualsWithDelta(621.6923, $f['costoProm'], 0.0001);  // 8082 / 13

        $this->assertEqualsWithDelta(8082.0, $response->viewData('totalValor'), 0.01);
        // Dos columnas (dos almacenes de la compañía).
        $this->assertCount(2, $response->viewData('columnas'));
    }

    public function test_excluye_items_en_cero_salvo_que_se_pida_incluirlos(): void
    {
        $a1 = $this->almacen('A1');
        $conSaldo = $this->item('CON-001');
        $enCero = $this->item('CERO-001');

        $this->existencia($conSaldo, $a1, 5, 10);
        $this->existencia($enCero, $a1, 0, 10);

        // Por defecto: solo el ítem con saldo.
        $filas = $this->actuar()->get(route('admin.inventario.existencias.consolidado'))->viewData('filas');
        $this->assertCount(1, $filas);
        $this->assertSame('CON-001', $filas[0]['codigo']);

        // Con incluir_ceros: aparecen ambos.
        $filas = $this->actuar()
            ->get(route('admin.inventario.existencias.consolidado', ['incluir_ceros' => 1]))
            ->viewData('filas');
        $this->assertCount(2, $filas);
    }

    public function test_no_muestra_existencias_de_otra_compania(): void
    {
        $otra = Compania::create(['nombre' => 'OTRA', 'activa' => true]);
        $almOtra = $this->almacen('X1', $otra->id);
        $itOtra = $this->item('AJENO-001', $otra->id);
        $this->existencia($itOtra, $almOtra, 99, 100, $otra->id);

        $a1 = $this->almacen('A1');
        $mio = $this->item('MIO-001');
        $this->existencia($mio, $a1, 3, 50);

        $response = $this->actuar()->get(route('admin.inventario.existencias.consolidado'))->assertOk();
        $filas = $response->viewData('filas');

        $this->assertCount(1, $filas);
        $this->assertSame('MIO-001', $filas[0]['codigo']);
        $this->assertEqualsWithDelta(150.0, $response->viewData('totalValor'), 0.01);
        // Solo las columnas (almacenes) de mi compañía.
        $this->assertCount(1, $response->viewData('columnas'));
    }

    public function test_filtra_por_almacen(): void
    {
        $a1 = $this->almacen('A1');
        $a2 = $this->almacen('A2');
        $soloA1 = $this->item('UNO-001');
        $soloA2 = $this->item('DOS-001');

        $this->existencia($soloA1, $a1, 4, 10);
        $this->existencia($soloA2, $a2, 7, 10);

        $response = $this->actuar()
            ->get(route('admin.inventario.existencias.consolidado', ['almacen_id' => $a1]))
            ->assertOk();

        $filas = $response->viewData('filas');
        $this->assertCount(1, $filas);
        $this->assertSame('UNO-001', $filas[0]['codigo']);
        // Al filtrar por un almacén, solo esa columna.
        $this->assertCount(1, $response->viewData('columnas'));
        $this->assertSame($a1, $response->viewData('columnas')->first()->id);
    }
}
