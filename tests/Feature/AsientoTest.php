<?php

namespace Tests\Feature;

use App\Models\Asiento;
use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\PeriodoContable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AsientoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    private CuentaContable $caja;

    private CuentaContable $ventas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);

        $this->caja = CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => '10101',
            'nombre' => 'Caja General',
            'nivel' => 3,
            'naturaleza' => 'DEBITO',
            'permite_movimiento' => true,
            'conciliable' => false,
            'activa' => true,
        ]);

        $this->ventas = CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => '40101',
            'nombre' => 'Ventas',
            'nivel' => 3,
            'naturaleza' => 'CREDITO',
            'permite_movimiento' => true,
            'conciliable' => false,
            'activa' => true,
        ]);
    }

    private function lineasCuadradas(float $monto = 100): array
    {
        return [
            ['cuenta_id' => $this->caja->id, 'descripcion' => 'Cobro', 'debito' => $monto, 'credito' => 0],
            ['cuenta_id' => $this->ventas->id, 'descripcion' => 'Venta', 'debito' => 0, 'credito' => $monto],
        ];
    }

    private function crearBorrador(float $monto = 100): Asiento
    {
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.store'), [
                'fecha' => '2026-06-12',
                'descripcion' => 'Asiento de prueba',
                'lineas' => $this->lineasCuadradas($monto),
                'accion' => 'borrador',
            ]);

        return Asiento::latest('id')->firstOrFail();
    }

    public function test_listado_de_asientos_se_muestra(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->get(route('admin.asientos.index'))
            ->assertOk()
            ->assertSee('Asientos de diario');
    }

    public function test_crear_borrador_cuadrado(): void
    {
        $asiento = $this->crearBorrador();

        $this->assertSame('BORRADOR', $asiento->estado);
        $this->assertSame('AS-000001', $asiento->numero);
        $this->assertSame('100.00', (string) $asiento->total_debito);
        $this->assertSame('100.00', (string) $asiento->total_credito);
        $this->assertCount(2, $asiento->detalle);
    }

    public function test_copiar_redirige_al_create_con_las_lineas_prellenadas(): void
    {
        $asiento = $this->crearBorrador(175);

        $resp = $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->get(route('admin.asientos.copiar', $asiento));

        $resp->assertRedirect(route('admin.asientos.create'));
        // Las líneas del origen quedan flasheadas como old() para el formulario.
        $resp->assertSessionHas('_old_input.descripcion', 'Asiento de prueba');
        $lineas = session('_old_input.lineas');
        $this->assertCount(2, $lineas);
        $this->assertSame($this->caja->id, $lineas[0]['cuenta_id']);
        $this->assertEquals(175, $lineas[0]['debito']);

        // Copiar no crea ni postea nada por sí mismo: sigue habiendo un solo asiento.
        $this->assertSame(1, Asiento::count());
    }

    public function test_hacer_recurrente_prellena_la_plantilla_desde_un_asiento(): void
    {
        $asiento = $this->crearBorrador(320);

        $resp = $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->get(route('admin.asientos-recurrentes.desde-asiento', $asiento));

        $resp->assertRedirect(route('admin.asientos-recurrentes.create'));
        $resp->assertSessionHas('_old_input.frecuencia', 'MENSUAL');
        $resp->assertSessionHas('_old_input.nombre', 'Asiento de prueba');
        $lineas = session('_old_input.lineas');
        $this->assertCount(2, $lineas);
        $this->assertSame($this->caja->id, $lineas[0]['cuenta_id']);
        $this->assertEquals(320, $lineas[0]['debito']);

        // No crea ninguna plantilla por sí mismo: solo prellena el formulario.
        $this->assertSame(0, \App\Models\AsientoRecurrente::count());
    }

    public function test_guardar_y_postear_crea_periodo_y_postea(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.store'), [
                'fecha' => '2026-06-12',
                'descripcion' => 'Venta de contado',
                'lineas' => $this->lineasCuadradas(250.50),
                'accion' => 'postear',
            ])
            ->assertSessionHasNoErrors();

        $asiento = Asiento::firstOrFail();
        $this->assertSame('POSTEADO', $asiento->estado);
        $this->assertNotNull($asiento->fecha_posteo);
        $this->assertSame($this->admin->id, $asiento->posteado_por);

        $periodo = PeriodoContable::where('compania_id', $this->compania->id)
            ->where('anio', 2026)->where('mes', 6)->first();
        $this->assertNotNull($periodo);
        $this->assertSame('ABIERTO', $periodo->estado);
        $this->assertSame($periodo->id, $asiento->periodo_id);
    }

    public function test_asiento_descuadrado_es_rechazado(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.store'), [
                'fecha' => '2026-06-12',
                'lineas' => [
                    ['cuenta_id' => $this->caja->id, 'debito' => 100, 'credito' => 0],
                    ['cuenta_id' => $this->ventas->id, 'debito' => 0, 'credito' => 90],
                ],
                'accion' => 'postear',
            ])
            ->assertSessionHasErrors('lineas');

        $this->assertSame(0, Asiento::count());
    }

    public function test_cuenta_de_titulo_es_rechazada(): void
    {
        $titulo = CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => '101',
            'nombre' => 'Activo Corriente',
            'nivel' => 2,
            'naturaleza' => 'DEBITO',
            'permite_movimiento' => false,
            'conciliable' => false,
            'activa' => true,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.store'), [
                'fecha' => '2026-06-12',
                'lineas' => [
                    ['cuenta_id' => $titulo->id, 'debito' => 100, 'credito' => 0],
                    ['cuenta_id' => $this->ventas->id, 'debito' => 0, 'credito' => 100],
                ],
                'accion' => 'borrador',
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, Asiento::count());
    }

    public function test_editar_borrador_actualiza_lineas(): void
    {
        $asiento = $this->crearBorrador(100);

        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->put(route('admin.asientos.update', $asiento), [
                'fecha' => '2026-06-13',
                'descripcion' => 'Corregido',
                'lineas' => $this->lineasCuadradas(75),
                'accion' => 'borrador',
            ])
            ->assertSessionHasNoErrors();

        $asiento->refresh();
        $this->assertSame('Corregido', $asiento->descripcion);
        $this->assertSame('75.00', (string) $asiento->total_debito);
        $this->assertCount(2, $asiento->detalle);
    }

    public function test_postear_borrador_existente(): void
    {
        $asiento = $this->crearBorrador();

        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.postear', $asiento))
            ->assertSessionHasNoErrors();

        $this->assertSame('POSTEADO', $asiento->fresh()->estado);
    }

    public function test_no_se_puede_postear_en_periodo_cerrado(): void
    {
        PeriodoContable::create([
            'compania_id' => $this->compania->id,
            'anio' => 2026,
            'mes' => 6,
            'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2026-06-30',
            'estado' => 'CERRADO',
        ]);

        $asiento = $this->crearBorrador();

        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.postear', $asiento))
            ->assertSessionHasErrors('fecha');

        $this->assertSame('BORRADOR', $asiento->fresh()->estado);
    }

    public function test_anular_asiento_posteado(): void
    {
        $asiento = $this->crearBorrador();
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.postear', $asiento));

        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.anular', $asiento))
            ->assertSessionHasNoErrors();

        $this->assertSame('ANULADO', $asiento->fresh()->estado);
    }

    public function test_anular_con_copia_deja_un_borrador_enlazado(): void
    {
        $asiento = $this->crearBorrador();
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.postear', $asiento))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.anular', $asiento), ['copiar_borrador' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame('ANULADO', $asiento->fresh()->estado);

        // La copia queda en BORRADOR, manual, enlazada al original y con las
        // mismas líneas (cuadrada). No se postea: se revisa y postea aparte.
        $copia = \App\Models\Asiento::where('compania_id', $this->compania->id)
            ->where('origen_id', $asiento->id)
            ->where('estado', 'BORRADOR')
            ->first();

        $this->assertNotNull($copia, 'Debe existir la copia en borrador enlazada al anulado.');
        $this->assertTrue($copia->esManual());
        $this->assertSame(
            $asiento->detalle()->count(),
            $copia->detalle()->count(),
            'La copia debe replicar todas las líneas del original.'
        );
        $this->assertEqualsWithDelta(
            (float) $asiento->total_debito,
            (float) $copia->total_debito,
            0.004
        );
    }

    public function test_anular_sin_copia_no_crea_borrador(): void
    {
        $asiento = $this->crearBorrador();
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.postear', $asiento));

        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.anular', $asiento), ['copiar_borrador' => '0'])
            ->assertSessionHasNoErrors();

        $this->assertSame('ANULADO', $asiento->fresh()->estado);
        $this->assertFalse(
            \App\Models\Asiento::where('origen_id', $asiento->id)->exists(),
            'Sin opción de copia no debe crearse ningún borrador.'
        );
    }

    public function test_no_se_puede_anular_asiento_en_periodo_cerrado(): void
    {
        $asiento = $this->crearBorrador();
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.postear', $asiento))
            ->assertSessionHasNoErrors();

        // Cerrar el período del asiento (junio 2026), forzando pese a borradores.
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.periodos.cerrar'), ['anio' => 2026, 'mes' => 6, 'forzar' => 1])
            ->assertSessionHasNoErrors();

        // Anular ahora alteraría un período cerrado: se bloquea (422). El asiento
        // sigue POSTEADO; para anularlo habría que reabrir el período primero.
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.anular', $asiento))
            ->assertStatus(422);

        $this->assertSame('POSTEADO', $asiento->fresh()->estado);
    }

    public function test_no_se_puede_anular_asiento_de_modulo_desde_contabilidad(): void
    {
        // Asiento POSTEADO que refleja un documento de CxC (origen de módulo):
        // anularlo desde Contabilidad descuadraría el submayor contra el mayor.
        $asiento = Asiento::create([
            'compania_id' => $this->compania->id,
            'numero' => Asiento::siguienteNumero($this->compania->id),
            'fecha' => '2026-06-12',
            'estado' => Asiento::ESTADO_POSTEADO,
            'origen_modulo' => 'CXC',
            'origen_tabla' => 'cxc_documentos',
            'origen_id' => null,
            'total_debito' => 100,
            'total_credito' => 100,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.anular', $asiento))
            ->assertStatus(422);

        $this->assertSame('POSTEADO', $asiento->fresh()->estado);
    }

    public function test_eliminar_borrador(): void
    {
        $asiento = $this->crearBorrador();

        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->delete(route('admin.asientos.destroy', $asiento));

        $this->assertNull(Asiento::find($asiento->id));
    }

    public function test_un_asiento_posteado_manual_se_reemite_pero_no_se_elimina(): void
    {
        $asiento = $this->crearBorrador();
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.postear', $asiento));

        // Un asiento MANUAL (origen CGL) posteado en período abierto se puede
        // corregir vía re-emisión: edit() abre el formulario (no se bloquea).
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->get(route('admin.asientos.edit', $asiento))
            ->assertOk();

        // Pero un asiento posteado NUNCA se elimina físicamente.
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->delete(route('admin.asientos.destroy', $asiento))
            ->assertStatus(422);

        $this->assertSame('POSTEADO', $asiento->fresh()->estado);
    }

    public function test_reemitir_asiento_posteado_anula_original_y_crea_nuevo(): void
    {
        $asiento = $this->crearBorrador();
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.postear', $asiento));

        // Re-emisión vía update() sobre un posteado: anula el original (queda en
        // historial) y crea uno nuevo posteado con las líneas corregidas.
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->put(route('admin.asientos.update', $asiento), [
                'fecha' => '2026-06-12',
                'descripcion' => 'Asiento corregido',
                'lineas' => $this->lineasCuadradas(150),
                'accion' => 'postear',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('ANULADO', $asiento->fresh()->estado);

        $nuevo = Asiento::where('origen_id', $asiento->id)
            ->where('estado', 'POSTEADO')
            ->latest('id')
            ->first();
        $this->assertNotNull($nuevo);
        $this->assertSame('150.00', (string) $nuevo->total_debito);
        $this->assertSame('150.00', (string) $nuevo->total_credito);
    }

    public function test_usuario_solo_consulta_ve_pero_no_crea(): void
    {
        // El Gate::before traduce CUALQUIER ability con forma vieja (modulo.ver,
        // .gestionar, ...) al modelo nuevo por opción × acción leyendo
        // core_menu_items (PermisoLegacy::candidatos) — nunca consulta un permiso
        // legacy literal aunque exista. Por eso hace falta el catálogo real
        // (MenuItemsSeeder + PermisosPorOpcionSeeder), no un Permission::findOrCreate
        // suelto. El rol 'usuario' ya trae 'contabilidad.ver' de fábrica
        // (RolesYPermisosSeeder) y PermisosPorOpcionSeeder lo traduce solo.
        $this->seed(\Database\Seeders\RolesYPermisosSeeder::class);
        $this->seed(\Database\Seeders\MenuItemsSeeder::class);
        $this->seed(\Database\Seeders\PermisosPorOpcionSeeder::class);

        $lector = User::factory()->create(['is_admin' => false]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->compania->id);
        $lector->assignRole('usuario');
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $this->actingAs($lector)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->get(route('admin.asientos.index'))
            ->assertOk();

        $this->actingAs($lector)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->get(route('admin.asientos.create'))
            ->assertForbidden();
    }

    public function test_numeracion_consecutiva_por_compania(): void
    {
        $this->crearBorrador(10);
        $segundo = $this->crearBorrador(20);

        $this->assertSame('AS-000002', $segundo->numero);
    }

    /** Crea la cuenta de control CXC y la registra como cuenta por defecto. */
    private function cuentaControlCxc(): CuentaContable
    {
        $cxc = CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => '10103',
            'nombre' => 'Cuentas por Cobrar',
            'nivel' => 3,
            'naturaleza' => 'DEBITO',
            'permite_movimiento' => true,
            'conciliable' => false,
            'activa' => true,
        ]);

        \App\Models\CuentaDefault::create([
            'compania_id' => $this->compania->id,
            'clave' => 'CXC',
            'cuenta_id' => $cxc->id,
        ]);

        return $cxc;
    }

    /** Crea una cuenta de inventario y un producto que la usa como control. */
    private function cuentaControlInventario(): CuentaContable
    {
        $inv = CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => '10401',
            'nombre' => 'Inventario',
            'nivel' => 3,
            'naturaleza' => 'DEBITO',
            'permite_movimiento' => true,
            'conciliable' => false,
            'activa' => true,
        ]);

        \Illuminate\Support\Facades\DB::table('item_productos_servicios')->insert([
            'compania_id' => $this->compania->id,
            'codigo' => 'IT-1',
            'nombre' => 'Producto',
            'tipo' => 'PRODUCTO',
            'cuenta_inventario_id' => $inv->id,
            'extra' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $inv;
    }

    public function test_postear_contra_cxc_se_bloquea_siempre(): void
    {
        $cxc = $this->cuentaControlCxc();

        $payload = [
            'fecha' => '2026-06-12',
            'descripcion' => 'Ajuste manual a CxC',
            'lineas' => [
                ['cuenta_id' => $cxc->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $this->ventas->id, 'debito' => 0, 'credito' => 100],
            ],
            'accion' => 'postear',
        ];

        // Sin confirmación: bloqueado.
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.store'), $payload)
            ->assertSessionHasErrors('lineas');

        // Incluso CON confirmación: el bloqueo de CxC/CxP es duro, no se permite.
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.store'), $payload + ['confirmar_control' => '1'])
            ->assertSessionHasErrors('lineas');

        $this->assertSame(0, Asiento::count());
    }

    public function test_postear_contra_inventario_se_bloquea_siempre(): void
    {
        $inv = $this->cuentaControlInventario();

        $payload = [
            'fecha' => '2026-06-12',
            'descripcion' => 'Ajuste manual de inventario',
            'lineas' => [
                ['cuenta_id' => $inv->id, 'debito' => 100, 'credito' => 0],
                ['cuenta_id' => $this->ventas->id, 'debito' => 0, 'credito' => 100],
            ],
            'accion' => 'postear',
        ];

        // El bloqueo de inventario también es duro: se rechaza siempre.
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.store'), $payload)
            ->assertSessionHasErrors('lineas');

        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.store'), $payload + ['confirmar_control' => '1'])
            ->assertSessionHasErrors('lineas');

        $this->assertSame(0, Asiento::count());
    }

    public function test_postear_sin_cuentas_de_control_no_pide_confirmacion(): void
    {
        // Sin CuentaDefault CXC/CXP ni inventario, postear normal no se bloquea.
        $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->compania->id])
            ->post(route('admin.asientos.store'), [
                'fecha' => '2026-06-12',
                'lineas' => $this->lineasCuadradas(100),
                'accion' => 'postear',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('POSTEADO', Asiento::firstOrFail()->estado);
    }
}
