<?php

namespace Tests\Feature;

use App\Models\Compania;
use App\Models\Respaldo;
use App\Models\Restauracion;
use App\Models\User;
use App\Services\RespaldoCompania;
use App\Services\RestaurarCompania;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestaurarCompaniaTest extends TestCase
{
    use RefreshDatabase;

    private Compania $companiaA;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['is_admin' => true]);
        $this->companiaA = Compania::create(['nombre' => 'COMPANIA A', 'activa' => true]);

        // Mismo par DIRECTA + HIJA (FK CASCADE) que ejercita el exportador.
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

    /** Exportar A y restaurar en una compañía NUEVA remapeando id, compania_id y FK. */
    public function test_restaura_en_compania_nueva_remapeando_ids_y_fk(): void
    {
        Storage::fake('local');

        // Datos de A: 2 padres; el primero con una hija.
        $pa1 = DB::table('zz_padres')->insertGetId(['compania_id' => $this->companiaA->id, 'nombre' => 'A1']);
        DB::table('zz_padres')->insert(['compania_id' => $this->companiaA->id, 'nombre' => 'A2']);
        DB::table('zz_hijos')->insert(['padre_id' => $pa1, 'dato' => 'hija de A1']);

        // 1) Exportar A.
        $respaldo = Respaldo::create([
            'compania_id' => $this->companiaA->id,
            'usuario' => 'admin@test',
            'estado' => Respaldo::ESTADO_PENDIENTE,
        ]);
        app(RespaldoCompania::class)->generar($respaldo);
        $respaldo->refresh();
        $this->assertSame(Respaldo::ESTADO_COMPLETADO, $respaldo->estado);

        $companiasAntes = Compania::count();

        // 2) Restaurar en compañía nueva.
        $rest = Restauracion::create([
            'usuario' => 'admin@test',
            'estado' => Restauracion::ESTADO_PENDIENTE,
            'compania_destino_nombre' => 'A RESTAURADA',
            'archivo_tmp' => Storage::disk('local')->path($respaldo->ruta),
            'respaldo_id' => $respaldo->id,
        ]);
        app(RestaurarCompania::class)->restaurar($rest);
        $rest->refresh();

        $this->assertSame(Restauracion::ESTADO_COMPLETADO, $rest->estado, $rest->mensaje_error ?? '');
        $this->assertNotNull($rest->compania_destino_id);

        // Compañía nueva creada (no se tocó A).
        $this->assertSame($companiasAntes + 1, Compania::count());
        $nueva = Compania::find($rest->compania_destino_id);
        $this->assertSame('A RESTAURADA', $nueva->nombre);
        $this->assertNotSame($this->companiaA->id, $nueva->id);

        // DIRECTA: los 2 padres se recrearon bajo la compañía nueva, con ids nuevos.
        $padresNuevos = DB::table('zz_padres')->where('compania_id', $nueva->id)->get();
        $this->assertCount(2, $padresNuevos);
        $this->assertEqualsCanonicalizing(['A1', 'A2'], $padresNuevos->pluck('nombre')->all());

        $idsOriginales = DB::table('zz_padres')->where('compania_id', $this->companiaA->id)->pluck('id')->all();
        foreach ($padresNuevos as $p) {
            $this->assertNotContains($p->id, $idsOriginales, 'el id del padre debe ser nuevo');
        }

        // HIJA: la hija se recreó apuntando al NUEVO padre (FK remapeado).
        $padresNuevosIds = $padresNuevos->pluck('id')->all();
        $hijasRestauradas = DB::table('zz_hijos')->whereIn('padre_id', $padresNuevosIds)->get();
        $this->assertCount(1, $hijasRestauradas);
        $this->assertSame('hija de A1', $hijasRestauradas[0]->dato);

        // El padre de la hija restaurada es el "A1" nuevo (no el original).
        $a1Nuevo = $padresNuevos->firstWhere('nombre', 'A1');
        $this->assertSame((int) $a1Nuevo->id, (int) $hijasRestauradas[0]->padre_id);

        // Aislamiento: A conserva sus 2 padres y su 1 hija originales.
        $this->assertSame(2, DB::table('zz_padres')->where('compania_id', $this->companiaA->id)->count());
        $this->assertSame(1, DB::table('zz_hijos')->whereIn('padre_id', $idsOriginales)->count());
    }

    /** Un ZIP con checksum alterado se rechaza (no restaura nada). */
    public function test_rechaza_respaldo_corrupto(): void
    {
        Storage::fake('local');

        DB::table('zz_padres')->insert(['compania_id' => $this->companiaA->id, 'nombre' => 'A1']);
        $respaldo = Respaldo::create([
            'compania_id' => $this->companiaA->id,
            'usuario' => 'admin@test',
            'estado' => Respaldo::ESTADO_PENDIENTE,
        ]);
        app(RespaldoCompania::class)->generar($respaldo);
        $respaldo->refresh();

        // Corromper el ZIP: reescribir un dato sin recalcular el checksum del manifest.
        $ruta = Storage::disk('local')->path($respaldo->ruta);
        $zip = new \ZipArchive();
        $zip->open($ruta);
        $zip->addFromString('data/zz_padres.ndjson', '{"id":1,"compania_id":999,"nombre":"HACK"}'."\n");
        $zip->close();

        $rest = Restauracion::create([
            'usuario' => 'admin@test',
            'estado' => Restauracion::ESTADO_PENDIENTE,
            'compania_destino_nombre' => 'NO DEBE CREARSE',
            'archivo_tmp' => $ruta,
        ]);

        $companiasAntes = Compania::count();
        $this->expectException(\RuntimeException::class);
        try {
            app(RestaurarCompania::class)->restaurar($rest);
        } finally {
            $this->assertSame($companiasAntes, Compania::count(), 'no debe crear compañía si el respaldo es corrupto');
        }
    }
}
