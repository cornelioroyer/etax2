<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\PeriodoContable;
use App\Models\User;
use App\Services\AsientoAutomatico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AsientoAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    private function cuenta(Compania $c, string $codigo, string $naturaleza): CuentaContable
    {
        return CuentaContable::create([
            'compania_id' => $c->id, 'codigo' => $codigo, 'nombre' => 'Cuenta '.$codigo,
            'nivel' => 3, 'naturaleza' => $naturaleza,
            'permite_movimiento' => true, 'conciliable' => false, 'activa' => true,
        ]);
    }

    /**
     * M1: con importes de >2 decimales, round(Σ) (como calculaba la cabecera)
     * podía diferir de Σ(round) (lo que se guarda en el detalle) → el trigger de
     * control rechazaba "totales no coinciden" un asiento que en realidad cuadra.
     * El fix redondea cada línea ANTES de sumar, así la cabecera SIEMPRE iguala
     * la suma de los detalles.
     */
    public function test_postear_cuadra_cabecera_con_detalle_en_importes_fraccionarios(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $compania = Compania::create(['nombre' => 'COMPANIA FRACCION', 'activa' => true]);

        $d1 = $this->cuenta($compania, '10101', 'DEBITO');
        $d2 = $this->cuenta($compania, '10102', 'DEBITO');
        $c1 = $this->cuenta($compania, '40101', 'CREDITO');
        $c2 = $this->cuenta($compania, '40102', 'CREDITO');

        // Por lado: round(Σ) = round(1.000) = 1.00, pero Σ(round) = 0.33*3 = 0.99.
        // El asiento cuadra (0.99 == 0.99); con el bug la cabecera valía 1.00 y
        // no coincidía con el detalle (0.99). Con el fix la cabecera vale 0.99.
        $lineas = [
            ['cuenta_id' => $d1->id, 'debito' => 0.334, 'credito' => 0],
            ['cuenta_id' => $d2->id, 'debito' => 0.333, 'credito' => 0],
            ['cuenta_id' => $d2->id, 'debito' => 0.333, 'credito' => 0],
            ['cuenta_id' => $c1->id, 'debito' => 0, 'credito' => 0.334],
            ['cuenta_id' => $c2->id, 'debito' => 0, 'credito' => 0.333],
            ['cuenta_id' => $c2->id, 'debito' => 0, 'credito' => 0.333],
        ];

        $asiento = DB::transaction(fn () => app(AsientoAutomatico::class)->postear(
            $compania->id, '2026-06-15', 'Prueba fracción', null,
            $lineas, 'CXC', 'cxc_documentos', null, $admin,
        ));

        $sumaDebito = (float) $asiento->detalle()->sum('debito');
        $sumaCredito = (float) $asiento->detalle()->sum('credito');

        // Invariante que exige el trigger de control: cabecera == suma del detalle.
        $this->assertEqualsWithDelta($sumaDebito, (float) $asiento->total_debito, 0.0001);
        $this->assertEqualsWithDelta($sumaCredito, (float) $asiento->total_credito, 0.0001);
        // El asiento cuadra y, concretamente, vale 0.99 por lado (Σ del detalle).
        $this->assertEqualsWithDelta((float) $asiento->total_debito, (float) $asiento->total_credito, 0.0001);
        $this->assertEqualsWithDelta(0.99, (float) $asiento->total_debito, 0.0001);
    }

    /**
     * Crea un asiento POSTEADO simple (Dr 10101 / Cr 40101) y devuelve [asiento, admin].
     */
    private function postearSimple(Compania $compania, User $admin): \App\Models\Asiento
    {
        $d = $this->cuenta($compania, '10101', 'DEBITO');
        $c = $this->cuenta($compania, '40101', 'CREDITO');

        return DB::transaction(fn () => app(AsientoAutomatico::class)->postear(
            $compania->id, '2026-06-15', 'Prueba anular', null,
            [
                ['cuenta_id' => $d->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $c->id, 'debito' => 0, 'credito' => 100],
            ],
            'CXC', 'cxc_documentos', null, $admin,
        ));
    }

    /**
     * A4: anular un documento cuyo período está CERRADO debe bloquearse en la
     * fuente única (AsientoAutomatico::anular), que cubre todas las anulaciones
     * de módulo. Mutar un período cerrado vía la reversión de saldos rompería su
     * inmutabilidad.
     */
    public function test_no_se_puede_anular_asiento_en_periodo_cerrado(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $compania = Compania::create(['nombre' => 'COMPANIA ANULAR CERRADO', 'activa' => true]);

        $asiento = $this->postearSimple($compania, $admin);

        // Cerrar el período en que quedó asentado.
        PeriodoContable::whereKey($asiento->periodo_id)->update(['estado' => PeriodoContable::ESTADO_CERRADO]);
        $asiento = $asiento->fresh();

        try {
            app(AsientoAutomatico::class)->anular($asiento, $admin);
            $this->fail('Se esperaba ValidationException por período cerrado.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('estado', $e->errors());
        }

        // El asiento sigue POSTEADO: reabrir el período es prerrequisito.
        $this->assertSame(\App\Models\Asiento::ESTADO_POSTEADO, $asiento->fresh()->estado);
    }

    /**
     * Contraparte: con el período ABIERTO la anulación procede normalmente.
     */
    public function test_anular_asiento_en_periodo_abierto_procede(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $compania = Compania::create(['nombre' => 'COMPANIA ANULAR ABIERTO', 'activa' => true]);

        $asiento = $this->postearSimple($compania, $admin);

        app(AsientoAutomatico::class)->anular($asiento, $admin);

        $this->assertSame(\App\Models\Asiento::ESTADO_ANULADO, $asiento->fresh()->estado);
    }
}
