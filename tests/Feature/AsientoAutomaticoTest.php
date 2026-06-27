<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\User;
use App\Services\AsientoAutomatico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
}
