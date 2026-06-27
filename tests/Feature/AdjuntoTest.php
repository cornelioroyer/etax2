<?php

namespace Tests\Feature;

use App\Models\Adjunto;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\CxpDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdjuntoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Compania $compania;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->compania = Compania::create(['nombre' => 'COMPANIA ADJUNTOS', 'activa' => true]);
    }

    private function actuar(?Compania $compania = null)
    {
        return $this->actingAs($this->admin)
            ->withSession(['compania_activa_id' => ($compania ?? $this->compania)->id]);
    }

    private function crearFactura(?Compania $compania = null): CxpDocumento
    {
        $compania = $compania ?? $this->compania;
        $proveedor = Contacto::create([
            'compania_id' => $compania->id,
            'nombre' => 'Proveedor X',
            'activo' => true,
        ]);

        return CxpDocumento::create([
            'compania_id' => $compania->id,
            'proveedor_id' => $proveedor->id,
            'tipo_documento' => CxpDocumento::TIPO_FACTURA,
            'numero' => 'F-'.uniqid(),
            'fecha' => '2026-06-27',
            'subtotal' => 100,
            'descuento' => 0,
            'impuesto' => 0,
            'total' => 100,
            'saldo' => 100,
            'estado' => CxpDocumento::ESTADO_BORRADOR,
            'created_by' => $this->admin->email,
        ]);
    }

    public function test_sube_adjunto_y_crea_fila_y_archivo(): void
    {
        Storage::fake('s3');
        $factura = $this->crearFactura();

        $this->actuar()->post(route('admin.adjuntos.subir'), [
            'tabla_origen' => 'cxp_documentos',
            'registro_id' => $factura->id,
            'archivos' => [UploadedFile::fake()->create('factura.pdf', 50, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $adj = Adjunto::firstOrFail();
        $this->assertSame($this->compania->id, $adj->compania_id);
        $this->assertSame('cxp_documentos', $adj->tabla_origen);
        $this->assertSame($factura->id, $adj->registro_id);
        $this->assertSame('CXP', $adj->modulo);
        $this->assertNotNull($adj->hash_archivo);
        Storage::disk('s3')->assertExists($adj->storage_path);
    }

    public function test_descarga_sirve_el_archivo(): void
    {
        Storage::fake('s3');
        $factura = $this->crearFactura();
        $this->actuar()->post(route('admin.adjuntos.subir'), [
            'tabla_origen' => 'cxp_documentos',
            'registro_id' => $factura->id,
            'archivos' => [UploadedFile::fake()->create('factura.pdf', 50, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $adj = Adjunto::firstOrFail();
        $this->actuar()->get(route('admin.adjuntos.descargar', $adj))->assertOk();
    }

    public function test_aislamiento_por_compania_en_descarga(): void
    {
        Storage::fake('s3');
        $facturaA = $this->crearFactura();
        $this->actuar()->post(route('admin.adjuntos.subir'), [
            'tabla_origen' => 'cxp_documentos',
            'registro_id' => $facturaA->id,
            'archivos' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $adjA = Adjunto::firstOrFail();

        // Otra compañía activa no puede descargar el adjunto de la compañía A.
        $companiaB = Compania::create(['nombre' => 'OTRA', 'activa' => true]);
        $this->actuar($companiaB)->get(route('admin.adjuntos.descargar', $adjA))->assertNotFound();
    }

    public function test_rechaza_tipo_no_permitido(): void
    {
        Storage::fake('s3');
        $factura = $this->crearFactura();

        $this->actuar()->post(route('admin.adjuntos.subir'), [
            'tabla_origen' => 'cxp_documentos',
            'registro_id' => $factura->id,
            'archivos' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')],
        ])->assertSessionHasErrors('archivos.0');

        $this->assertSame(0, Adjunto::count());
    }

    public function test_rechaza_tabla_no_registrada(): void
    {
        Storage::fake('s3');
        $this->actuar()->post(route('admin.adjuntos.subir'), [
            'tabla_origen' => 'users',
            'registro_id' => 1,
            'archivos' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
        ])->assertSessionHasErrors('tabla_origen');
    }

    public function test_no_se_puede_adjuntar_a_documento_de_otra_compania(): void
    {
        Storage::fake('s3');
        $companiaB = Compania::create(['nombre' => 'OTRA', 'activa' => true]);
        $facturaB = $this->crearFactura($companiaB);

        // Compañía A activa, intentando adjuntar a un documento de la compañía B.
        $this->actuar()->post(route('admin.adjuntos.subir'), [
            'tabla_origen' => 'cxp_documentos',
            'registro_id' => $facturaB->id,
            'archivos' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
        ])->assertNotFound();

        $this->assertSame(0, Adjunto::count());
    }

    public function test_elimina_adjunto(): void
    {
        Storage::fake('s3');
        $factura = $this->crearFactura();
        $this->actuar()->post(route('admin.adjuntos.subir'), [
            'tabla_origen' => 'cxp_documentos',
            'registro_id' => $factura->id,
            'archivos' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $adj = Adjunto::firstOrFail();
        $path = $adj->storage_path;

        $this->actuar()->delete(route('admin.adjuntos.eliminar', $adj))->assertSessionHasNoErrors();

        $this->assertSame(0, Adjunto::count());
        Storage::disk('s3')->assertMissing($path);
    }

    public function test_show_de_cxp_espeja_archivo_legado_idempotente(): void
    {
        Storage::fake('s3');
        $factura = $this->crearFactura();
        // Simula el flujo viejo: archivo ya en disco + columnas inline.
        Storage::disk('s3')->put('cxp/'.$this->compania->id.'/legacy.pdf', 'datos');
        $factura->update(['archivo_path' => 'cxp/'.$this->compania->id.'/legacy.pdf', 'archivo_disk' => 's3']);

        $this->actuar()->get(route('admin.cxp.facturas.show', $factura))->assertOk();
        $this->assertSame(1, Adjunto::where('registro_id', $factura->id)->count());

        // Segunda visita: idempotente, no duplica.
        $this->actuar()->get(route('admin.cxp.facturas.show', $factura))->assertOk();
        $this->assertSame(1, Adjunto::where('registro_id', $factura->id)->count());
    }
}
