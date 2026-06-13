<?php

namespace Tests\Feature;

use App\Models\AfiActivo;
use App\Models\AfiCategoria;
use App\Models\AfiDepreciacion;
use App\Models\AfiBaja;
use App\Models\AfiUbicacion;
use App\Models\Asiento;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\Diario;
use App\Models\PeriodoContable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivoFijoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Compania $compania;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'Test AFI SA', 'activa' => true]);
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function cid(): int
    {
        return $this->compania->id;
    }

    private function cuenta(string $codigo, string $nombre): CuentaContable
    {
        return CuentaContable::create([
            'compania_id'        => $this->cid(),
            'codigo'             => $codigo,
            'nombre'             => $nombre,
            'nivel'              => 3,
            'naturaleza'         => 'DEBITO',
            'permite_movimiento' => true,
            'conciliable'        => false,
            'activa'             => true,
        ]);
    }

    private function periodo(): PeriodoContable
    {
        return PeriodoContable::create([
            'compania_id' => $this->cid(),
            'anio'        => 2026,
            'mes'         => 1,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin'    => '2026-01-31',
            'estado'       => 'ABIERTO',
        ]);
    }

    // ───── Categorías ─────────────────────────────────────────────────────────

    public function test_crear_categoria(): void
    {
        $res = $this->actuar()->post(route('admin.activos.categorias.store'), [
            'codigo'                  => 'EQUIPO',
            'nombre'                  => 'Equipos de cómputo',
            'vida_util_meses_default' => 60,
        ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('afi_categorias', [
            'compania_id' => $this->cid(),
            'codigo'      => 'EQUIPO',
        ]);
    }

    public function test_categoria_codigo_duplicado_rechazado(): void
    {
        AfiCategoria::create([
            'compania_id' => $this->cid(),
            'codigo'      => 'MUEBLES',
            'nombre'      => 'Muebles',
        ]);

        $res = $this->actuar()->post(route('admin.activos.categorias.store'), [
            'codigo' => 'MUEBLES',
            'nombre' => 'Otro',
        ]);

        $res->assertSessionHasErrors('codigo');
    }

    // ───── Ubicaciones ────────────────────────────────────────────────────────

    public function test_crear_ubicacion(): void
    {
        $res = $this->actuar()->post(route('admin.activos.ubicaciones.store'), [
            'codigo' => 'OFICINA1',
            'nombre' => 'Oficina principal',
        ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('afi_ubicaciones', [
            'compania_id' => $this->cid(),
            'codigo'      => 'OFICINA1',
        ]);
    }

    // ───── Activos ────────────────────────────────────────────────────────────

    public function test_index_accesible(): void
    {
        $this->actuar()->get(route('admin.activos.activos.index'))->assertOk();
    }

    public function test_index_requiere_autenticacion(): void
    {
        $this->get(route('admin.activos.activos.index'))->assertRedirect(route('login'));
    }

    public function test_categorias_index_accesible(): void
    {
        $this->actuar()->get(route('admin.activos.categorias.index'))->assertOk();
    }

    public function test_ubicaciones_index_accesible(): void
    {
        $this->actuar()->get(route('admin.activos.ubicaciones.index'))->assertOk();
    }

    public function test_crear_activo_sin_cuenta_activo_no_genera_asiento(): void
    {
        // Sin cuenta de activo válida la validación falla (cuenta_contrapartida_id required)
        $res = $this->actuar()->post(route('admin.activos.activos.store'), [
            'descripcion'              => 'Laptop Dell',
            'fecha_compra'             => '2026-01-15',
            'fecha_inicio_depreciacion' => '2026-02-01',
            'valor_compra'             => '1200.00',
            'valor_residual'           => '0.00',
            'vida_util_meses'          => 36,
            // falta cuenta_contrapartida_id
        ]);

        $res->assertSessionHasErrors('cuenta_contrapartida_id');
    }

    public function test_flujo_completo_activo_depreciacion_baja(): void
    {
        $periodo    = $this->periodo();
        $ctaActivo  = $this->cuenta('1600', 'Maquinaria');
        $ctaAcum    = $this->cuenta('1601', 'Dep. Acum. Maquinaria');
        $ctaGasto   = $this->cuenta('7600', 'Gasto Depreciación');
        $ctaBanco   = $this->cuenta('1100', 'Banco');
        $ctaPerdida = $this->cuenta('8000', 'Pérdida Baja Activos');

        // 1. Crear activo
        $res = $this->actuar()->post(route('admin.activos.activos.store'), [
            'descripcion'                  => 'Torno industrial',
            'fecha_compra'                 => '2026-01-01',
            'fecha_inicio_depreciacion'    => '2026-01-01',
            'valor_compra'                 => '12000.00',
            'valor_residual'               => '0.00',
            'vida_util_meses'              => 12,
            'cuenta_activo_id'             => $ctaActivo->id,
            'cuenta_depreciacion_acum_id'  => $ctaAcum->id,
            'cuenta_gasto_depreciacion_id' => $ctaGasto->id,
            'cuenta_contrapartida_id'      => $ctaBanco->id,
        ]);

        $res->assertRedirect();
        $activo = AfiActivo::where('compania_id', $this->cid())->first();
        $this->assertNotNull($activo);
        $this->assertEquals(AfiActivo::ESTADO_ACTIVO, $activo->estado);
        $this->assertEquals(12000.0, $activo->valor_compra);
        $this->assertNotNull($activo->asiento_compra_id);

        $asientoCompra = Asiento::find($activo->asiento_compra_id);
        $this->assertEquals('POSTEADO', $asientoCompra->estado);

        // 2. Depreciar
        $res2 = $this->actuar()->post(route('admin.activos.activos.depreciar', $activo), [
            'fecha'      => '2026-01-31',
            'periodo_id' => $periodo->id,
        ]);

        $res2->assertRedirect();
        $dep = AfiDepreciacion::where('activo_id', $activo->id)->first();
        $this->assertNotNull($dep);
        $this->assertEquals(1000.0, $dep->monto);
        $this->assertEquals(1000.0, $dep->acumulado);
        $this->assertNotNull($dep->asiento_id);
        $this->assertEquals(11000.0, $activo->valorLibros());

        // 3. No duplicar depreciación en el mismo período
        $res3 = $this->actuar()->post(route('admin.activos.activos.depreciar', $activo), [
            'fecha'      => '2026-01-31',
            'periodo_id' => $periodo->id,
        ]);
        $res3->assertSessionHasErrors('depreciar');

        // 4. Baja
        $res4 = $this->actuar()->post(route('admin.activos.activos.baja', $activo), [
            'fecha'               => '2026-01-31',
            'motivo'              => 'Desincorporado',
            'cuenta_resultado_id' => $ctaPerdida->id,
        ]);

        $res4->assertRedirect();
        $activo->refresh();
        $this->assertEquals(AfiActivo::ESTADO_DADO_DE_BAJA, $activo->estado);
        $this->assertNotNull($activo->baja);

        $asientoBaja = $activo->baja->asiento;
        $this->assertNotNull($asientoBaja);
        $this->assertEquals('POSTEADO', $asientoBaja->estado);

        // 5. Segunda baja rechazada
        $res5 = $this->actuar()->post(route('admin.activos.activos.baja', $activo), [
            'fecha'               => '2026-02-01',
            'cuenta_resultado_id' => $ctaPerdida->id,
        ]);
        $res5->assertSessionHasErrors('baja');
    }

    public function test_activo_numeracion_secuencial(): void
    {
        AfiActivo::create([
            'compania_id'         => $this->cid(),
            'codigo'              => 'AF-000001',
            'descripcion'         => 'A',
            'valor_compra'        => 100,
            'valor_residual'      => 0,
            'vida_util_meses'     => 12,
            'metodo_depreciacion' => 'LINEA_RECTA',
            'estado'              => 'ACTIVO',
        ]);

        $this->assertEquals('AF-000002', AfiActivo::siguienteNumero($this->cid()));
    }

    public function test_valor_libros_calculado_correctamente(): void
    {
        $activo = AfiActivo::create([
            'compania_id'         => $this->cid(),
            'codigo'              => 'AF-TEST',
            'descripcion'         => 'Test',
            'valor_compra'        => 6000,
            'valor_residual'      => 0,
            'vida_util_meses'     => 60,
            'metodo_depreciacion' => 'LINEA_RECTA',
            'estado'              => 'ACTIVO',
        ]);

        AfiDepreciacion::create([
            'activo_id'  => $activo->id,
            'fecha'      => '2026-01-31',
            'monto'      => 100,
            'acumulado'  => 100,
            'estado'     => 'POSTEADA',
        ]);
        AfiDepreciacion::create([
            'activo_id'  => $activo->id,
            'fecha'      => '2026-02-28',
            'monto'      => 100,
            'acumulado'  => 200,
            'estado'     => 'POSTEADA',
        ]);

        $this->assertEquals(100.0, $activo->depreciacionMensual());
        $this->assertEquals(200.0, $activo->depreciacionAcumulada());
        $this->assertEquals(5800.0, $activo->valorLibros());
        $this->assertEquals(2, $activo->mesesDepreciados());
        $this->assertEquals(58, $activo->mesesRestantes());
    }
}
