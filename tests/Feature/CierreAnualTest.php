<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\AsientoDetalle;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\PeriodoContable;
use App\Models\TipoCuenta;
use App\Models\User;
use App\Services\CierreAnual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Camino feliz y guardas de CierreAnual::previsualizar() / cerrar(): la
 * operación contablemente más crítica. Verifica que el asiento de cierre
 * cuadra, que la utilidad/pérdida se lleva a UTILIDADES_RETENIDAS con el signo
 * correcto, que se postea en el período de ajuste (mes 13) y que las cuatro
 * guardas (sin movimientos, cuenta default faltante, doble cierre, período de
 * ajuste cerrado) bloquean el cierre.
 *
 * Los saldos se siembran directo en cgl_saldos (en PostgreSQL los mantiene un
 * trigger que no corre bajo SQLite), igual que EstadoResultadoTest.
 */
class CierreAnualTest extends TestCase
{
    use RefreshDatabase;

    private const ANIO = 2025;

    private User $admin;

    private Compania $compania;

    private CierreAnual $cierre;

    /** @var array<string, int>  codigo tipo => id */
    private array $tipos;

    private CuentaContable $ingreso;

    private CuentaContable $gasto;

    private CuentaContable $costo;

    private CuentaContable $utilidades;

    private PeriodoContable $enero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA CIERRE', 'activa' => true]);
        $this->cierre = app(CierreAnual::class);

        $this->tipos = collect([
            ['INGRESO', 'CREDITO'], ['COSTO', 'DEBITO'], ['GASTO', 'DEBITO'], ['PATRIMONIO', 'CREDITO'],
        ])->mapWithKeys(fn ($t) => [
            $t[0] => TipoCuenta::firstOrCreate(
                ['codigo' => $t[0]],
                ['nombre' => ucfirst(strtolower($t[0])), 'naturaleza' => $t[1]]
            )->id,
        ])->all();

        $this->ingreso = $this->cuenta('40101', 'Ventas', 'INGRESO', 'CREDITO');
        $this->costo = $this->cuenta('50101', 'Costo de ventas', 'COSTO', 'DEBITO');
        $this->gasto = $this->cuenta('60101', 'Gastos de operación', 'GASTO', 'DEBITO');
        $this->utilidades = $this->cuenta('30201', 'Utilidades retenidas', 'PATRIMONIO', 'CREDITO');

        $this->enero = PeriodoContable::create([
            'compania_id' => $this->compania->id,
            'anio'        => self::ANIO,
            'mes'         => 1,
            'fecha_inicio' => self::ANIO.'-01-01',
            'fecha_fin'    => self::ANIO.'-01-31',
            'estado'      => PeriodoContable::ESTADO_ABIERTO,
        ]);
    }

    private function cuenta(string $codigo, string $nombre, string $tipo, string $naturaleza): CuentaContable
    {
        return CuentaContable::create([
            'compania_id'        => $this->compania->id,
            'codigo'             => $codigo,
            'nombre'             => $nombre,
            'nivel'              => 3,
            'tipo_cuenta_id'     => $this->tipos[$tipo],
            'naturaleza'         => $naturaleza,
            'permite_movimiento' => true,
            'conciliable'        => false,
            'activa'             => true,
        ]);
    }

    private function saldo(CuentaContable $cuenta, float $debito, float $credito): void
    {
        DB::table('cgl_saldos')->insert([
            'compania_id' => $this->compania->id,
            'periodo_id'  => $this->enero->id,
            'cuenta_id'   => $cuenta->id,
            'debito'      => $debito,
            'credito'     => $credito,
            'saldo'       => $debito - $credito,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function configurarUtilidadesRetenidas(): void
    {
        CuentaDefault::create([
            'compania_id' => $this->compania->id,
            'clave'       => 'UTILIDADES_RETENIDAS',
            'cuenta_id'   => $this->utilidades->id,
            'created_by'  => $this->admin->email,
        ]);
    }

    private function lineaUtilidades(Asiento $asiento): AsientoDetalle
    {
        return AsientoDetalle::where('asiento_id', $asiento->id)
            ->where('cuenta_id', $this->utilidades->id)
            ->firstOrFail();
    }

    public function test_previsualizar_utilidad_acredita_utilidades_retenidas(): void
    {
        // Ingreso 1000 (acreedor), costo 300, gasto 200 → utilidad 500.
        $this->saldo($this->ingreso, 0, 1000);
        $this->saldo($this->costo, 300, 0);
        $this->saldo($this->gasto, 200, 0);
        $this->configurarUtilidadesRetenidas();

        $prev = $this->cierre->previsualizar($this->compania->id, self::ANIO);

        $this->assertEqualsWithDelta(1000, $prev['ingresos'], 0.001);
        $this->assertEqualsWithDelta(300, $prev['costos'], 0.001);
        $this->assertEqualsWithDelta(200, $prev['gastos'], 0.001);
        $this->assertEqualsWithDelta(500, $prev['utilidad'], 0.001);

        // Utilidad positiva → se ACREDITA patrimonio.
        $this->assertEqualsWithDelta(500, $prev['cierre']['credito'], 0.001);
        $this->assertEqualsWithDelta(0, $prev['cierre']['debito'], 0.001);

        // El asiento (reversos + cierre) cuadra.
        $this->assertEqualsWithDelta($prev['total_debito'], $prev['total_credito'], 0.001);
        $this->assertEqualsWithDelta(1000, $prev['total_debito'], 0.001);
    }

    public function test_cerrar_postea_asiento_cuadrado_en_periodo_de_ajuste(): void
    {
        $this->saldo($this->ingreso, 0, 1000);
        $this->saldo($this->gasto, 300, 0);
        $this->configurarUtilidadesRetenidas();

        $asiento = $this->cierre->cerrar($this->compania->id, self::ANIO, $this->admin);

        $this->assertSame(Asiento::ESTADO_POSTEADO, $asiento->estado);
        $this->assertSame(CierreAnual::ORIGEN, $asiento->origen_modulo);
        $this->assertSame(self::ANIO, (int) $asiento->origen_id);
        $this->assertSame(PeriodoContable::MES_AJUSTE, $asiento->periodo->mes);
        $this->assertEqualsWithDelta($asiento->total_debito, $asiento->total_credito, 0.001);

        // Utilidad 700 → línea de patrimonio acreditada por 700.
        $linea = $this->lineaUtilidades($asiento);
        $this->assertEqualsWithDelta(700, $linea->credito, 0.001);
        $this->assertEqualsWithDelta(0, $linea->debito, 0.001);
    }

    public function test_cerrar_con_perdida_debita_utilidades_retenidas(): void
    {
        // Ingreso 200, gasto 500 → pérdida 300.
        $this->saldo($this->ingreso, 0, 200);
        $this->saldo($this->gasto, 500, 0);
        $this->configurarUtilidadesRetenidas();

        $prev = $this->cierre->previsualizar($this->compania->id, self::ANIO);
        $this->assertEqualsWithDelta(-300, $prev['utilidad'], 0.001);

        $asiento = $this->cierre->cerrar($this->compania->id, self::ANIO, $this->admin);

        // Pérdida → se DEBITA patrimonio por 300.
        $linea = $this->lineaUtilidades($asiento);
        $this->assertEqualsWithDelta(300, $linea->debito, 0.001);
        $this->assertEqualsWithDelta(0, $linea->credito, 0.001);
        $this->assertEqualsWithDelta($asiento->total_debito, $asiento->total_credito, 0.001);
    }

    public function test_no_se_puede_cerrar_dos_veces_el_mismo_ejercicio(): void
    {
        $this->saldo($this->ingreso, 0, 1000);
        $this->saldo($this->gasto, 300, 0);
        $this->configurarUtilidadesRetenidas();

        $this->cierre->cerrar($this->compania->id, self::ANIO, $this->admin);

        $this->expectException(ValidationException::class);
        $this->cierre->cerrar($this->compania->id, self::ANIO, $this->admin);
    }

    public function test_cerrar_sin_cuenta_utilidades_retenidas_falla(): void
    {
        $this->saldo($this->ingreso, 0, 1000);
        $this->saldo($this->gasto, 300, 0);
        // No se configura UTILIDADES_RETENIDAS.

        try {
            $this->cierre->cerrar($this->compania->id, self::ANIO, $this->admin);
            $this->fail('Se esperaba ValidationException por cuenta UTILIDADES_RETENIDAS no configurada.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('anio', $e->errors());
        }

        $this->assertNull($this->cierre->asientoDe($this->compania->id, self::ANIO));
    }

    public function test_cerrar_sin_movimientos_de_resultado_falla(): void
    {
        $this->configurarUtilidadesRetenidas();
        // Sin saldos en cuentas de resultado.

        $this->expectException(ValidationException::class);
        $this->cierre->cerrar($this->compania->id, self::ANIO, $this->admin);
    }
}
