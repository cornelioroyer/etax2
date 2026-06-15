<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\AsientoDetalle;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CuadreAuxiliaresTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private CuentaContable $cxc;

    private CuentaContable $cxp;

    private CuentaContable $inventario;

    private CuentaContable $contraparte;

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
            'activa' => true,
        ]);

        $this->cxc = $crear('10103', 'Cuentas por Cobrar', 'DEBITO');
        $this->cxp = $crear('20101', 'Cuentas por Pagar', 'CREDITO');
        $this->inventario = $crear('10401', 'Inventario', 'DEBITO');
        $this->contraparte = $crear('30101', 'Capital', 'CREDITO');

        CuentaDefault::create(['compania_id' => $this->compania->id, 'clave' => 'CXC', 'cuenta_id' => $this->cxc->id]);
        CuentaDefault::create(['compania_id' => $this->compania->id, 'clave' => 'CXP', 'cuenta_id' => $this->cxp->id]);
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    /** Crea un asiento POSTEADO con sus líneas, sin disparar observers. */
    private function postear(array $lineas): void
    {
        Asiento::withoutEvents(function () use ($lineas) {
            $asiento = Asiento::create([
                'compania_id' => $this->compania->id,
                'numero' => 'AS-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
                'fecha' => '2026-06-01',
                'descripcion' => 'Prueba',
                'estado' => Asiento::ESTADO_POSTEADO,
                'origen_modulo' => 'CGL',
                'total_debito' => collect($lineas)->sum('debito'),
                'total_credito' => collect($lineas)->sum('credito'),
                'created_by' => 'tester@etax2.com',
            ]);

            foreach (array_values($lineas) as $i => $l) {
                AsientoDetalle::create([
                    'asiento_id' => $asiento->id,
                    'linea' => $i + 1,
                    'cuenta_id' => $l['cuenta_id'],
                    'debito' => $l['debito'],
                    'credito' => $l['credito'],
                    'tasa_cambio' => 1,
                    'debito_local' => $l['debito'],
                    'credito_local' => $l['credito'],
                ]);
            }
        });
    }

    private function docCxc(float $saldo, string $estado = 'PENDIENTE'): void
    {
        DB::table('cxc_documentos')->insert([
            'compania_id' => $this->compania->id,
            'cliente_id' => 1,
            'tipo_documento' => 'FACTURA',
            'numero' => 'FC-'.random_int(1, 999999),
            'fecha' => '2026-06-01',
            'total' => $saldo,
            'saldo' => $saldo,
            'estado' => $estado,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function docCxp(float $saldo, string $estado = 'PENDIENTE'): void
    {
        DB::table('cxp_documentos')->insert([
            'compania_id' => $this->compania->id,
            'proveedor_id' => 1,
            'tipo_documento' => 'FACTURA',
            'numero' => 'FP-'.random_int(1, 999999),
            'fecha' => '2026-06-01',
            'total' => $saldo,
            'saldo' => $saldo,
            'estado' => $estado,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function existencia(int $cuentaInventarioId, float $cantidad, float $costo): void
    {
        $itemId = DB::table('item_productos_servicios')->insertGetId([
            'compania_id' => $this->compania->id,
            'codigo' => 'IT-'.random_int(1, 999999),
            'nombre' => 'Producto',
            'tipo' => 'PRODUCTO',
            'cuenta_inventario_id' => $cuentaInventarioId,
            'extra' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $almacenId = DB::table('inv_almacenes')->insertGetId([
            'compania_id' => $this->compania->id,
            'codigo' => 'ALM-'.random_int(1, 999999),
            'nombre' => 'Almacén',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('inv_existencias')->insert([
            'compania_id' => $this->compania->id,
            'item_id' => $itemId,
            'almacen_id' => $almacenId,
            'cantidad' => $cantidad,
            'costo_promedio' => $costo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_los_tres_auxiliares_cuadran(): void
    {
        // CxC: documento saldo 100 ↔ asiento DR CXC 100 / CR capital 100.
        $this->docCxc(100);
        $this->postear([
            ['cuenta_id' => $this->cxc->id, 'debito' => 100, 'credito' => 0],
            ['cuenta_id' => $this->contraparte->id, 'debito' => 0, 'credito' => 100],
        ]);

        // CxP: documento saldo 60 (+ uno BORRADOR que NO cuenta) ↔ CR CXP 60.
        $this->docCxp(60);
        $this->docCxp(999, 'BORRADOR');
        $this->postear([
            ['cuenta_id' => $this->contraparte->id, 'debito' => 60, 'credito' => 0],
            ['cuenta_id' => $this->cxp->id, 'debito' => 0, 'credito' => 60],
        ]);

        // Inventario: existencia 10 × 5 = 50 ↔ DR inventario 50.
        $this->existencia($this->inventario->id, 10, 5);
        $this->postear([
            ['cuenta_id' => $this->inventario->id, 'debito' => 50, 'credito' => 0],
            ['cuenta_id' => $this->contraparte->id, 'debito' => 0, 'credito' => 50],
        ]);

        $response = $this->actuar()->get(route('admin.reportes.cuadre-auxiliares'))->assertOk();

        $secciones = collect($response->viewData('secciones'))->keyBy('titulo');

        $this->assertEqualsWithDelta(100, $secciones['Cuentas por Cobrar']['auxiliar'], 0.001);
        $this->assertEqualsWithDelta(100, $secciones['Cuentas por Cobrar']['mayor'], 0.001);
        $this->assertTrue($secciones['Cuentas por Cobrar']['cuadra']);

        // CxP excluye el BORRADOR de 999.
        $this->assertEqualsWithDelta(60, $secciones['Cuentas por Pagar']['auxiliar'], 0.001);
        $this->assertEqualsWithDelta(60, $secciones['Cuentas por Pagar']['mayor'], 0.001);
        $this->assertTrue($secciones['Cuentas por Pagar']['cuadra']);

        $this->assertEqualsWithDelta(50, $secciones['Inventario']['auxiliar'], 0.001);
        $this->assertEqualsWithDelta(50, $secciones['Inventario']['mayor'], 0.001);
        $this->assertTrue($secciones['Inventario']['cuadra']);
    }

    public function test_detecta_descuadre_por_asiento_manual(): void
    {
        // Auxiliar CxC = 100, pero el mayor tiene 125 (asiento manual de 25 extra).
        $this->docCxc(100);
        $this->postear([
            ['cuenta_id' => $this->cxc->id, 'debito' => 100, 'credito' => 0],
            ['cuenta_id' => $this->contraparte->id, 'debito' => 0, 'credito' => 100],
        ]);
        $this->postear([
            ['cuenta_id' => $this->cxc->id, 'debito' => 25, 'credito' => 0],
            ['cuenta_id' => $this->contraparte->id, 'debito' => 0, 'credito' => 25],
        ]);

        $response = $this->actuar()->get(route('admin.reportes.cuadre-auxiliares'))->assertOk();
        $cxc = collect($response->viewData('secciones'))->firstWhere('titulo', 'Cuentas por Cobrar');

        $this->assertEqualsWithDelta(100, $cxc['auxiliar'], 0.001);
        $this->assertEqualsWithDelta(125, $cxc['mayor'], 0.001);
        $this->assertEqualsWithDelta(-25, $cxc['diferencia'], 0.001);
        $this->assertFalse($cxc['cuadra']);
    }
}
