<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\AsientoDetalle;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\PeriodoContable;
use App\Models\User;
use App\Services\CierreAnual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Cubre la guarda de integridad de CierreAnual::reversar: reversar el asiento
 * de cierre exige que su período de ajuste (mes 13) esté ABIERTO, igual que
 * cualquier anulación sobre período cerrado en Contabilidad.
 *
 * El asiento de cierre se arma como lo hace el propio servicio cerrar()
 * (BORRADOR → detalle balanceado → POSTEADO), con cuentas reales, de modo que
 * la anulación ejerza el AsientoObserver (Bancos) sobre datos bien formados.
 */
class CierreAnualReversarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private CuentaContable $gasto;

    private CuentaContable $utilidades;

    private CierreAnual $cierre;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);
        $this->cierre = app(CierreAnual::class);

        $this->gasto = CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => '50101',
            'nombre' => 'Gastos de operación',
            'nivel' => 3,
            'naturaleza' => 'DEBITO',
            'permite_movimiento' => true,
            'conciliable' => false,
            'activa' => true,
        ]);

        $this->utilidades = CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => '30201',
            'nombre' => 'Utilidades retenidas',
            'nivel' => 3,
            'naturaleza' => 'CREDITO',
            'permite_movimiento' => true,
            'conciliable' => false,
            'activa' => true,
        ]);
    }

    /**
     * Crea un asiento de cierre POSTEADO de $anio asentado en su período de
     * ajuste (mes 13), con el estado de período indicado. Se construye igual
     * que cerrar(): BORRADOR con detalle balanceado y luego POSTEADO.
     */
    private function crearCierrePosteado(int $anio, string $estadoPeriodo): Asiento
    {
        $periodo = PeriodoContable::create([
            'compania_id'  => $this->compania->id,
            'anio'         => $anio,
            'mes'          => PeriodoContable::MES_AJUSTE,
            'fecha_inicio' => "{$anio}-12-31",
            'fecha_fin'    => "{$anio}-12-31",
            'estado'       => $estadoPeriodo,
        ]);

        $asiento = Asiento::create([
            'compania_id'   => $this->compania->id,
            'periodo_id'    => $periodo->id,
            'numero'        => Asiento::siguienteNumero($this->compania->id),
            'fecha'         => "{$anio}-12-31",
            'descripcion'   => "Asiento de cierre del ejercicio {$anio}",
            'estado'        => Asiento::ESTADO_BORRADOR,
            'origen_modulo' => CierreAnual::ORIGEN,
            'origen_tabla'  => 'cgl_periodos',
            'origen_id'     => $anio,
            'total_debito'  => 100,
            'total_credito' => 100,
            'created_by'    => $this->admin->email,
        ]);

        foreach ([
            ['cuenta_id' => $this->utilidades->id, 'descripcion' => "Cierre {$anio} · saldar gasto", 'debito' => 100, 'credito' => 0],
            ['cuenta_id' => $this->gasto->id, 'descripcion' => "Cierre {$anio} · gasto del ejercicio", 'debito' => 0, 'credito' => 100],
        ] as $i => $l) {
            AsientoDetalle::create([
                'asiento_id'    => $asiento->id,
                'linea'         => $i + 1,
                'cuenta_id'     => $l['cuenta_id'],
                'descripcion'   => $l['descripcion'],
                'debito'        => $l['debito'],
                'credito'       => $l['credito'],
                'tasa_cambio'   => 1,
                'debito_local'  => $l['debito'],
                'credito_local' => $l['credito'],
                'created_by'    => $this->admin->email,
            ]);
        }

        $asiento->update([
            'estado'       => Asiento::ESTADO_POSTEADO,
            'posteado_por' => $this->admin->id,
            'fecha_posteo' => now(),
        ]);

        return $asiento;
    }

    public function test_no_se_puede_reversar_cierre_con_periodo_de_ajuste_cerrado(): void
    {
        $asiento = $this->crearCierrePosteado(2025, PeriodoContable::ESTADO_CERRADO);

        try {
            $this->cierre->reversar($this->compania->id, 2025, $this->admin);
            $this->fail('Se esperaba ValidationException por período de ajuste cerrado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('anio', $e->errors());
        }

        // El cierre sigue POSTEADO: reabrir el período es prerrequisito.
        $this->assertSame(Asiento::ESTADO_POSTEADO, $asiento->fresh()->estado);
    }

    public function test_reversar_cierre_con_periodo_de_ajuste_abierto_anula_el_asiento(): void
    {
        $asiento = $this->crearCierrePosteado(2025, PeriodoContable::ESTADO_ABIERTO);

        $this->cierre->reversar($this->compania->id, 2025, $this->admin);

        $this->assertSame(Asiento::ESTADO_ANULADO, $asiento->fresh()->estado);
    }
}
