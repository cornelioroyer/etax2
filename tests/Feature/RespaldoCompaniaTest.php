<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\Respaldo;
use App\Models\User;
use App\Services\RespaldoCompania;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        // Par padre/hijo propio para ejercitar el mismo código que usa el esquema
        // real: DIRECTA (compania_id) + HIJA resuelta por FK ON DELETE CASCADE.
        Schema::dropIfExists('zz_hijos');
        Schema::dropIfExists('zz_padres');
        Schema::create('zz_padres', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('compania_id');
            $t->string('nombre');
        });
        Schema::create('zz_hijos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('padre_id')->constrained('zz_padres')->cascadeOnDelete();
            $t->string('dato');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('zz_hijos');
        Schema::dropIfExists('zz_padres');
        parent::tearDown();
    }

    /** El respaldo de A contiene SOLO datos de A, incluida la tabla hija (FK CASCADE). */
    public function test_respaldo_contiene_solo_datos_de_la_compania(): void
    {
        Storage::fake('local');

        // 2 padres de A, 1 de B; cada uno con un hijo.
        $pa1 = DB::table('zz_padres')->insertGetId(['compania_id' => $this->companiaA->id, 'nombre' => 'A1']);
        DB::table('zz_padres')->insert(['compania_id' => $this->companiaA->id, 'nombre' => 'A2']);
        $pb1 = DB::table('zz_padres')->insertGetId(['compania_id' => $this->companiaB->id, 'nombre' => 'B1']);
        DB::table('zz_hijos')->insert([
            ['padre_id' => $pa1, 'dato' => 'hijo de A'],
            ['padre_id' => $pb1, 'dato' => 'hijo de B'],
        ]);

        $respaldo = Respaldo::create([
            'compania_id' => $this->companiaA->id,
            'usuario' => 'admin@test',
            'estado' => Respaldo::ESTADO_PENDIENTE,
        ]);

        app(RespaldoCompania::class)->generar($respaldo);

        $respaldo->refresh();
        $this->assertSame(Respaldo::ESTADO_COMPLETADO, $respaldo->estado);
        $this->assertTrue(Storage::disk('local')->exists($respaldo->ruta));

        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($respaldo->ruta)) === true);

        // DIRECTA: solo los 2 padres de A.
        $padres = $this->ndjson($zip, 'data/zz_padres.ndjson');
        $this->assertCount(2, $padres);
        foreach ($padres as $p) {
            $this->assertSame($this->companiaA->id, (int) $p->compania_id);
        }
        $this->assertNotContains('B1', array_map(fn ($p) => $p->nombre, $padres));

        // HIJA: solo el hijo de A, resuelto por la cadena de FK CASCADE.
        $hijos = $this->ndjson($zip, 'data/zz_hijos.ndjson');
        $this->assertCount(1, $hijos);
        $this->assertSame('hijo de A', $hijos[0]->dato);

        $manifest = json_decode($zip->getFromName('manifest.json'));
        $this->assertSame($this->companiaA->id, $manifest->compania->id);
        $this->assertSame(2, $manifest->tablas->zz_padres);
        $this->assertSame(1, $manifest->tablas->zz_hijos);

        $zip->close();
    }

    /** No se puede descargar el respaldo de OTRA compañía (aislamiento / anti-IDOR). */
    public function test_no_descarga_respaldo_de_otra_compania(): void
    {
        $respaldoB = Respaldo::create([
            'compania_id' => $this->companiaB->id,
            'usuario' => 'admin@test',
            'estado' => Respaldo::ESTADO_COMPLETADO,
            'archivo' => 'x.zip',
            'ruta' => 'respaldos/'.$this->companiaB->id.'/x.zip',
            'disco' => 'local',
        ]);

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
