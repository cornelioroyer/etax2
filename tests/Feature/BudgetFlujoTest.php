<?php

namespace Tests\Feature;

use App\Models\BudgetEscenario;
use App\Models\BudgetPresupuesto;
use App\Models\BudgetVersion;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\PeriodoContable;
use App\Models\TipoCuenta;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BudgetFlujoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Las dimensiones core_* viven en el esquema maestro (dev/prod) y no
        // tienen migración Laravel; en SQLite las creamos para poder ejercitar
        // el flujo completo (los controladores las consultan en show()).
        foreach (['core_centros_costos', 'core_departamentos', 'core_proyectos'] as $t) {
            if (! Schema::hasTable($t)) {
                Schema::create($t, function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('compania_id');
                    $table->string('codigo')->nullable();
                    $table->string('nombre')->nullable();
                    $table->boolean('activo')->default(true);
                    $table->timestamps();
                });
            }
        }
    }

    public function test_flujo_completo_presupuesto(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $compania = Compania::create(['nombre' => 'COMPANIA BUDGET', 'activa' => true]);

        $tipoId = TipoCuenta::firstOrCreate(['codigo' => 'GASTO'], ['nombre' => 'Gasto', 'naturaleza' => 'DEBITO'])->id;
        $periodo = PeriodoContable::create([
            'compania_id' => $compania->id, 'anio' => 2026, 'mes' => 1,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-01-31', 'estado' => 'ABIERTO',
        ]);
        $cuenta = CuentaContable::create([
            'compania_id' => $compania->id, 'codigo' => '51101', 'nombre' => 'Gastos de Alquiler',
            'nivel' => 3, 'tipo_cuenta_id' => $tipoId, 'naturaleza' => 'DEBITO',
            'permite_movimiento' => true, 'conciliable' => false, 'activa' => true,
        ]);

        $act = fn () => $this->actingAs($user)->withSession(['compania_activa_id' => $compania->id]);

        // 1. Escenario
        $act()->get(route('admin.presupuestos.escenarios.index'))->assertOk();
        $act()->post(route('admin.presupuestos.escenarios.store'), ['nombre' => 'Base'])->assertRedirect();
        $escenario = BudgetEscenario::where('compania_id', $compania->id)->firstOrFail();

        // 2. Versión
        $act()->post(route('admin.presupuestos.versiones.store'), ['nombre' => '2026 v1', 'activa' => '1'])->assertRedirect();
        $version = BudgetVersion::where('compania_id', $compania->id)->firstOrFail();

        // 3. Presupuesto
        $act()->get(route('admin.presupuestos.create'))->assertOk();
        $act()->post(route('admin.presupuestos.store'), [
            'nombre' => 'Presupuesto 2026', 'anio' => 2026,
            'escenario_id' => $escenario->id, 'version_id' => $version->id,
        ])->assertRedirect();
        $pre = BudgetPresupuesto::where('compania_id', $compania->id)->firstOrFail();
        $this->assertSame(BudgetPresupuesto::ESTADO_BORRADOR, $pre->estado);

        // 4. Ver (show ejercita la carga de dimensiones)
        $act()->get(route('admin.presupuestos.show', $pre))->assertOk()->assertSee('Presupuesto 2026');

        // 5. Línea de detalle
        $act()->post(route('admin.presupuestos.detalle.store', $pre), [
            'cuenta_id' => $cuenta->id, 'periodo_id' => $periodo->id, 'monto_presupuestado' => 1500.00,
        ])->assertRedirect();
        $this->assertDatabaseHas('budget_presupuestos_detalle', [
            'presupuesto_id' => $pre->id, 'cuenta_id' => $cuenta->id, 'monto_presupuestado' => 1500.00,
        ]);

        // 6. show con la línea cargada (renderiza el detalle con su cuenta/periodo)
        $act()->get(route('admin.presupuestos.show', $pre))->assertOk()->assertSee('51101');

        // 7. Cambiar estado a APROBADO
        $act()->post(route('admin.presupuestos.cambiar-estado', $pre), ['estado' => BudgetPresupuesto::ESTADO_APROBADO])->assertRedirect();
        $this->assertSame(BudgetPresupuesto::ESTADO_APROBADO, $pre->fresh()->estado);

        // 8. Index lista el presupuesto
        $act()->get(route('admin.presupuestos.index'))->assertOk()->assertSee('Presupuesto 2026');
    }

    public function test_no_se_puede_eliminar_presupuesto_aprobado(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $compania = Compania::create(['nombre' => 'COMPANIA BUDGET 2', 'activa' => true]);

        $pre = BudgetPresupuesto::create([
            'compania_id' => $compania->id, 'nombre' => 'P', 'anio' => 2026,
            'estado' => BudgetPresupuesto::ESTADO_APROBADO,
        ]);

        $this->actingAs($user)->withSession(['compania_activa_id' => $compania->id])
            ->delete(route('admin.presupuestos.destroy', $pre))
            ->assertStatus(422);

        $this->assertDatabaseHas('budget_presupuestos', ['id' => $pre->id]);
    }
}
