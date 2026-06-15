<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\AsientoDetalle;
use App\Models\BcoBanco;
use App\Models\BcoCuenta;
use App\Models\BcoMovimiento;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Services\BancoSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BancoSyncAsientoTest extends TestCase
{
    use RefreshDatabase;

    private Compania $compania;

    private CuentaContable $glBanco;

    private CuentaContable $glCapital;

    private BcoCuenta $banco;

    private BancoSync $sync;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->glBanco = $crear('10102', 'Bancos', 'DEBITO');
        $this->glCapital = $crear('30101', 'Acciones Comunes', 'CREDITO');

        $bancoEntidad = BcoBanco::create(['nombre' => 'Banco General', 'activo' => true]);
        $this->banco = BcoCuenta::create([
            'compania_id' => $this->compania->id,
            'banco_id' => $bancoEntidad->id,
            'cuenta_contable_id' => $this->glBanco->id,
            'numero_cuenta' => '0001',
            'nombre' => 'Cuenta corriente',
            'saldo_inicial' => 0,
            'activa' => true,
        ]);

        $this->sync = app(BancoSync::class);
    }

    /** Crea un asiento POSTEADO sin disparar el observer (para probar el servicio aislado). */
    private function asientoPosteado(array $lineas, string $origen = 'CGL', string $numero = 'AS-000001'): Asiento
    {
        return Asiento::withoutEvents(function () use ($lineas, $origen, $numero) {
            $asiento = Asiento::create([
                'compania_id' => $this->compania->id,
                'numero' => $numero,
                'fecha' => '2026-06-01',
                'descripcion' => 'Aporte inicial de capital',
                'estado' => Asiento::ESTADO_POSTEADO,
                'origen_modulo' => $origen,
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

            return $asiento;
        });
    }

    public function test_debito_al_banco_genera_ingreso_credito_en_bancos(): void
    {
        $asiento = $this->asientoPosteado([
            ['cuenta_id' => $this->glBanco->id, 'debito' => 5000, 'credito' => 0],
            ['cuenta_id' => $this->glCapital->id, 'debito' => 0, 'credito' => 5000],
        ]);

        $this->sync->sincronizar($asiento);

        $mov = BcoMovimiento::where('asiento_id', $asiento->id)->sole();
        $this->assertSame($this->banco->id, $mov->cuenta_bancaria_id);
        // Débito al banco en el mayor = ingreso (crédito) en Bancos.
        $this->assertEquals(5000, $mov->credito);
        $this->assertEquals(0, $mov->debito);
        $this->assertSame(BcoMovimiento::TIPO_ASIENTO, $mov->tipo_movimiento);
        $this->assertSame('AS-000001', $mov->referencia);
        $this->assertEquals(5000, $this->banco->fresh()->saldo_actual);
    }

    public function test_credito_al_banco_genera_egreso_debito_en_bancos(): void
    {
        $asiento = $this->asientoPosteado([
            ['cuenta_id' => $this->glCapital->id, 'debito' => 800, 'credito' => 0],
            ['cuenta_id' => $this->glBanco->id, 'debito' => 0, 'credito' => 800],
        ]);

        $this->sync->sincronizar($asiento);

        $mov = BcoMovimiento::where('asiento_id', $asiento->id)->sole();
        // Crédito al banco en el mayor = egreso (débito) en Bancos.
        $this->assertEquals(800, $mov->debito);
        $this->assertEquals(0, $mov->credito);
        $this->assertEquals(-800, $this->banco->fresh()->saldo_actual);
    }

    public function test_es_idempotente(): void
    {
        $asiento = $this->asientoPosteado([
            ['cuenta_id' => $this->glBanco->id, 'debito' => 5000, 'credito' => 0],
            ['cuenta_id' => $this->glCapital->id, 'debito' => 0, 'credito' => 5000],
        ]);

        $this->sync->sincronizar($asiento);
        $this->sync->sincronizar($asiento);

        $this->assertSame(1, BcoMovimiento::where('asiento_id', $asiento->id)->count());
    }

    public function test_omite_asientos_de_origen_bancos(): void
    {
        $asiento = $this->asientoPosteado([
            ['cuenta_id' => $this->glBanco->id, 'debito' => 5000, 'credito' => 0],
            ['cuenta_id' => $this->glCapital->id, 'debito' => 0, 'credito' => 5000],
        ], origen: 'BANCOS');

        $this->sync->sincronizar($asiento);

        $this->assertSame(0, BcoMovimiento::where('asiento_id', $asiento->id)->count());
    }

    public function test_omite_cuentas_no_bancarias(): void
    {
        $asiento = $this->asientoPosteado([
            ['cuenta_id' => $this->glCapital->id, 'debito' => 100, 'credito' => 0],
            ['cuenta_id' => $this->glCapital->id, 'debito' => 0, 'credito' => 100],
        ]);

        $this->sync->sincronizar($asiento);

        $this->assertSame(0, BcoMovimiento::count());
    }

    public function test_omite_enlace_ambiguo(): void
    {
        // Segunda cuenta bancaria enlazada a la MISMA cuenta contable.
        BcoCuenta::create([
            'compania_id' => $this->compania->id,
            'banco_id' => BcoBanco::create(['nombre' => 'Otro Banco', 'activo' => true])->id,
            'cuenta_contable_id' => $this->glBanco->id,
            'numero_cuenta' => '0002',
            'nombre' => 'Otra cuenta',
            'saldo_inicial' => 0,
            'activa' => true,
        ]);

        $asiento = $this->asientoPosteado([
            ['cuenta_id' => $this->glBanco->id, 'debito' => 5000, 'credito' => 0],
            ['cuenta_id' => $this->glCapital->id, 'debito' => 0, 'credito' => 5000],
        ]);

        $this->sync->sincronizar($asiento);

        $this->assertSame(0, BcoMovimiento::count());
    }

    public function test_observer_sincroniza_al_postear_y_revierte_al_anular(): void
    {
        // Borrador: no debe reflejar nada todavía.
        $asiento = Asiento::create([
            'compania_id' => $this->compania->id,
            'numero' => 'AS-000010',
            'fecha' => '2026-06-01',
            'descripcion' => 'Aporte inicial',
            'estado' => Asiento::ESTADO_BORRADOR,
            'origen_modulo' => 'CGL',
            'created_by' => 'tester@etax2.com',
        ]);
        AsientoDetalle::create(['asiento_id' => $asiento->id, 'linea' => 1, 'cuenta_id' => $this->glBanco->id, 'debito' => 5000, 'credito' => 0, 'tasa_cambio' => 1, 'debito_local' => 5000, 'credito_local' => 0]);
        AsientoDetalle::create(['asiento_id' => $asiento->id, 'linea' => 2, 'cuenta_id' => $this->glCapital->id, 'debito' => 0, 'credito' => 5000, 'tasa_cambio' => 1, 'debito_local' => 0, 'credito_local' => 5000]);

        $this->assertSame(0, BcoMovimiento::count());

        // Postear → el observer crea el movimiento.
        $asiento->update(['estado' => Asiento::ESTADO_POSTEADO]);
        $this->assertSame(1, BcoMovimiento::where('asiento_id', $asiento->id)->count());

        // Anular → el observer lo retira.
        $asiento->update(['estado' => Asiento::ESTADO_ANULADO]);
        $this->assertSame(0, BcoMovimiento::where('asiento_id', $asiento->id)->count());
    }

    public function test_no_se_puede_anular_si_el_movimiento_esta_conciliado(): void
    {
        $asiento = $this->asientoPosteado([
            ['cuenta_id' => $this->glBanco->id, 'debito' => 5000, 'credito' => 0],
            ['cuenta_id' => $this->glCapital->id, 'debito' => 0, 'credito' => 5000],
        ]);
        $this->sync->sincronizar($asiento);

        BcoMovimiento::where('asiento_id', $asiento->id)->update(['conciliado' => true]);

        $this->expectException(ValidationException::class);
        $this->sync->revertir($asiento);
    }
}
