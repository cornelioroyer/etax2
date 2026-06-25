<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\TipoCuenta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CuentaImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    protected function setUp(): void
    {
        parent::setUp();

        // El temp por defecto de laravel-excel (storage/framework/cache) puede no
        // ser escribible por el usuario que corre los tests; usar uno propio.
        $tmp = sys_get_temp_dir().'/le_test_'.getmypid();
        @mkdir($tmp, 0777, true);
        config(['excel.temporary_files.local_path' => $tmp]);

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA PRUEBA', 'activa' => true]);

        // Tipos de cuenta base (cgl_tipos_cuenta).
        foreach ([
            ['ACTIVO', 'DEBITO'], ['PASIVO', 'CREDITO'], ['PATRIMONIO', 'CREDITO'],
            ['INGRESO', 'CREDITO'], ['COSTO', 'DEBITO'], ['GASTO', 'DEBITO'],
        ] as [$codigo, $naturaleza]) {
            TipoCuenta::firstOrCreate(['codigo' => $codigo], [
                'nombre' => ucfirst(strtolower($codigo)),
                'naturaleza' => $naturaleza,
            ]);
        }
    }

    private function actuar()
    {
        return $this->actingAs($this->admin)->withSession(['compania_activa_id' => $this->compania->id]);
    }

    private function importarCsv(string $csv)
    {
        $archivo = UploadedFile::fake()->createWithContent('plan.csv', $csv);

        return $this->actuar()->post(route('admin.cuentas.importar'), ['archivo' => $archivo]);
    }

    private const ENCABEZADO = 'codigo,nombre,tipo,naturaleza,codigo_padre,permite_movimiento,conciliable,renglon_isr';

    public function test_importa_cuentas_con_jerarquia_naturaleza_y_titulos(): void
    {
        $csv = implode("\n", [
            self::ENCABEZADO,
            '1000,ACTIVO,ACTIVO,,,,,',                       // título, naturaleza derivada DEBITO
            '1100,CAJA GENERAL,ACTIVO,,1000,SI,,',           // hijo, MOV
            '1200,BANCO,ACTIVO,,1000,SI,SI,',                // hijo conciliable
            '1800,DEPRECIACION ACUMULADA,ACTIVO,CREDITO,1000,SI,,', // contra-cuenta: CREDITO explícito
            '4000,INGRESOS,INGRESO,,,,,1',                   // naturaleza derivada CREDITO, renglón ISR
        ]);

        $this->importarCsv($csv)
            ->assertRedirect(route('admin.cuentas.index'))
            ->assertSessionHas('status');

        $cuentas = CuentaContable::where('compania_id', $this->compania->id)->get()->keyBy('codigo');
        $this->assertCount(5, $cuentas);

        // Padre 1000: naturaleza derivada del tipo, nivel 1, título (tiene hijos).
        $padre = $cuentas['1000'];
        $this->assertSame('DEBITO', $padre->naturaleza);
        $this->assertSame(1, $padre->nivel);
        $this->assertNull($padre->cuenta_padre_id);
        $this->assertFalse($padre->permite_movimiento, 'El padre debe quedar como título');

        // Hijo 1100: enlazado al padre por codigo_padre explícito, nivel 2, MOV.
        $caja = $cuentas['1100'];
        $this->assertSame($padre->id, $caja->cuenta_padre_id);
        $this->assertSame(2, $caja->nivel);
        $this->assertSame('DEBITO', $caja->naturaleza);
        $this->assertTrue($caja->permite_movimiento);

        // 1200 conciliable.
        $this->assertTrue($cuentas['1200']->conciliable);

        // 1800 contra-cuenta: naturaleza CREDITO aunque el tipo ACTIVO es DEBITO.
        $this->assertSame('CREDITO', $cuentas['1800']->naturaleza);

        // 4000 ingreso: naturaleza derivada CREDITO, renglón ISR conservado.
        $this->assertSame('CREDITO', $cuentas['4000']->naturaleza);
        $this->assertSame('1', $cuentas['4000']->renglon_isr);
    }

    public function test_importacion_es_idempotente_omite_codigos_existentes(): void
    {
        $csv = implode("\n", [
            self::ENCABEZADO,
            '1000,ACTIVO,ACTIVO,,,,,',
            '1100,CAJA GENERAL,ACTIVO,,1000,SI,,',
        ]);

        $this->importarCsv($csv);
        $this->assertSame(2, CuentaContable::where('compania_id', $this->compania->id)->count());

        // Reimportar el mismo archivo no duplica nada.
        $this->importarCsv($csv)->assertSessionHas('status');
        $this->assertSame(2, CuentaContable::where('compania_id', $this->compania->id)->count());
    }

    public function test_no_modifica_cuentas_existentes(): void
    {
        $existente = CuentaContable::create([
            'compania_id' => $this->compania->id,
            'codigo' => '1100',
            'nombre' => 'NOMBRE ORIGINAL',
            'nivel' => 1,
            'naturaleza' => 'DEBITO',
            'permite_movimiento' => true,
            'conciliable' => false,
            'activa' => true,
        ]);

        $this->importarCsv(implode("\n", [
            self::ENCABEZADO,
            '1100,NOMBRE NUEVO,ACTIVO,,,NO,SI,',
        ]));

        $existente->refresh();
        $this->assertSame('NOMBRE ORIGINAL', $existente->nombre, 'No debe sobrescribir cuentas existentes');
        $this->assertTrue($existente->permite_movimiento);
        $this->assertFalse($existente->conciliable);
    }

    public function test_fila_con_tipo_invalido_se_reporta_y_no_se_crea(): void
    {
        $this->importarCsv(implode("\n", [
            self::ENCABEZADO,
            '9000,CUENTA RARA,XYZ,,,,,',
        ]))->assertSessionHas('status');

        $this->assertSame(0, CuentaContable::where('compania_id', $this->compania->id)->count());
    }

    public function test_descarga_plantillas_csv_y_xlsx(): void
    {
        $this->actuar()->get(route('admin.cuentas.importar.plantilla'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actuar()->get(route('admin.cuentas.importar.plantilla-xlsx'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
