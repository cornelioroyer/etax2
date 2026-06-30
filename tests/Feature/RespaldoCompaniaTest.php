<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\Respaldo;
use App\Models\User;
use App\Services\RespaldoCompania;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class RespaldoCompaniaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $companiaA;

    private Compania $companiaB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->companiaA = Compania::create(['nombre' => 'COMPANIA A', 'activa' => true]);
        $this->companiaB = Compania::create(['nombre' => 'COMPANIA B', 'activa' => true]);

        // Tabla DIRECTA (compania_id): 2 contactos de A, 1 de B.
        $a1 = DB::table('contact_contactos')->insertGetId([
            'compania_id' => $this->companiaA->id, 'nombre' => 'Cliente A1',
        ]);
        DB::table('contact_contactos')->insert([
            'compania_id' => $this->companiaA->id, 'nombre' => 'Cliente A2',
        ]);
        $b1 = DB::table('contact_contactos')->insertGetId([
            'compania_id' => $this->companiaB->id, 'nombre' => 'Cliente B1',
        ]);

        // Tabla HIJA (sin compania_id, pertenece por contacto_id): una de A, una de B.
        DB::table('contact_contactos_tipos')->insert([
            ['contacto_id' => $a1, 'tipo_id' => 1],
            ['contacto_id' => $b1, 'tipo_id' => 1],
        ]);
    }

    /** El respaldo de A contiene SOLO datos de A, incluidas las tablas hija. */
    public function test_respaldo_contiene_solo_datos_de_la_compania(): void
    {
        Storage::fake('local');

        $respaldo = Respaldo::create([
            'compania_id' => $this->companiaA->id,
            'usuario' => 'admin@test',
            'estado' => Respaldo::ESTADO_PENDIENTE,
        ]);

        app(RespaldoCompania::class)->generar($respaldo);

        $respaldo->refresh();
        $this->assertSame(Respaldo::ESTADO_COMPLETADO, $respaldo->estado);
        $this->assertNotNull($respaldo->ruta);
        $this->assertTrue(Storage::disk('local')->exists($respaldo->ruta));

        // Abrir el ZIP producido.
        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($respaldo->ruta)) === true);

        // Tabla directa: solo los 2 contactos de A, ninguno de B.
        $contactos = $this->ndjson($zip, 'data/contact_contactos.ndjson');
        $this->assertCount(2, $contactos);
        foreach ($contactos as $c) {
            $this->assertSame($this->companiaA->id, (int) $c->compania_id);
        }
        $this->assertNotContains('Cliente B1', array_map(fn ($c) => $c->nombre, $contactos));

        // Tabla hija: solo el tipo del contacto de A (resuelto por la cadena fk).
        $tipos = $this->ndjson($zip, 'data/contact_contactos_tipos.ndjson');
        $this->assertCount(1, $tipos);

        // Manifest: las tablas que ESTE fixture usa deben quedar clasificadas y
        // exportadas. No se exige cobertura total del esquema aquí: las
        // migraciones locales (solo para SQLite/tests) no declaran FK en muchas
        // tablas que sí lo tienen en el esquema real de Postgres (ver
        // [[etax2_respaldos_compania]]), así que bajo SQLite aparecen varias
        // tablas ajenas a este test como "no exportadas" — esa cobertura total
        // se verifica aparte, contra Postgres real (memoria del proyecto:
        // "Verificado: 261 tablas exportadas, no_exportadas=0").
        $manifest = json_decode($zip->getFromName('manifest.json'));
        $this->assertSame($this->companiaA->id, $manifest->compania->id);
        $this->assertNotContains('contact_contactos', $manifest->tablas_no_exportadas);
        $this->assertNotContains('contact_contactos_tipos', $manifest->tablas_no_exportadas);
        $this->assertSame(2, $manifest->tablas->contact_contactos);

        $zip->close();
    }

    /** No se puede descargar el respaldo de OTRA compañía (aislamiento / anti-IDOR). */
    public function test_no_descarga_respaldo_de_otra_compania(): void
    {
        // Respaldo perteneciente a B.
        $respaldoB = Respaldo::create([
            'compania_id' => $this->companiaB->id,
            'usuario' => 'admin@test',
            'estado' => Respaldo::ESTADO_COMPLETADO,
            'archivo' => 'x.zip',
            'ruta' => 'respaldos/'.$this->companiaB->id.'/x.zip',
            'disco' => 'local',
        ]);

        // Usuario con compañía activa = A intenta bajar el respaldo de B -> 404.
        $resp = $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => $this->companiaA->id])
            ->get(route('admin.respaldos.download', $respaldoB));

        $resp->assertNotFound();
    }

    /** @return array<int,object> */
    private function ndjson(ZipArchive $zip, string $nombre): array
    {
        $contenido = $zip->getFromName($nombre);
        $this->assertNotFalse($contenido, "Falta {$nombre} en el ZIP");

        return collect(explode("\n", trim($contenido)))
            ->filter(fn ($l) => $l !== '')
            ->map(fn ($l) => json_decode($l))
            ->values()
            ->all();
    }
}
