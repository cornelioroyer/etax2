<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Adjunto;
use App\Services\AdjuntoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Endpoints centrales de adjuntos: subir, descargar y eliminar archivos ligados
 * a un documento de cualquier módulo. La autorización se decide por el REGISTRO
 * de tablas permitidas (tabla_origen -> módulo + permisos), que es la única
 * superficie donde se habilita una tabla para adjuntos.
 */
class AdjuntoController extends Controller
{
    use ConCompaniaActiva;

    /**
     * Tablas habilitadas para adjuntos y sus permisos. Para sumar un módulo,
     * basta con agregar su entrada aquí (más el componente en su vista).
     *
     * @var array<string, array{modulo: string, ver: string, gestionar: string}>
     */
    private const REGISTRO = [
        'cxp_documentos' => ['modulo' => 'CXP', 'ver' => 'cxp.ver', 'gestionar' => 'cxp.gestionar'],
        'caj_movimientos' => ['modulo' => 'CAJA', 'ver' => 'caja.ver', 'gestionar' => 'caja.gestionar'],
    ];

    public function subir(Request $request, AdjuntoService $servicio): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'tabla_origen' => ['required', 'string', Rule::in(array_keys(self::REGISTRO))],
            'registro_id' => ['required', 'integer'],
            'archivos' => ['required', 'array', 'min:1', 'max:10'],
            'archivos.*' => ['file', 'mimes:'.implode(',', AdjuntoService::EXTENSIONES), 'max:'.AdjuntoService::MAX_KB],
        ]);

        $reg = self::REGISTRO[$data['tabla_origen']];
        abort_unless($request->user()->can($reg['gestionar']), 403);
        $this->verificarDuenoDelRegistro($data['tabla_origen'], (int) $data['registro_id'], $companiaId);

        foreach ($request->file('archivos') as $file) {
            $servicio->guardar($file, $data['tabla_origen'], (int) $data['registro_id'], $companiaId, $reg['modulo'], $request->user());
        }

        $n = count($request->file('archivos'));

        return back()->with('status', $n === 1 ? 'Adjunto subido.' : "{$n} adjuntos subidos.");
    }

    public function descargar(Request $request, Adjunto $adjunto): StreamedResponse
    {
        $companiaId = $this->companiaActivaId($request);
        abort_unless($adjunto->compania_id === $companiaId, 404);

        $reg = $this->registroDe($adjunto->tabla_origen);
        abort_unless($reg && $request->user()->can($reg['ver']), 403);

        $disk = $adjunto->storage_disk ?: config('filesystems.adjuntos', 's3');
        abort_unless(Storage::disk($disk)->exists($adjunto->storage_path), 404);

        return Storage::disk($disk)->response($adjunto->storage_path, $adjunto->nombre_archivo);
    }

    public function eliminar(Request $request, Adjunto $adjunto, AdjuntoService $servicio): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        abort_unless($adjunto->compania_id === $companiaId, 404);

        $reg = $this->registroDe($adjunto->tabla_origen);
        abort_unless($reg && $request->user()->can($reg['gestionar']), 403);

        $servicio->eliminar($adjunto);

        return back()->with('status', 'Adjunto eliminado.');
    }

    /** @return array{modulo: string, ver: string, gestionar: string}|null */
    private function registroDe(?string $tabla): ?array
    {
        return $tabla !== null ? (self::REGISTRO[$tabla] ?? null) : null;
    }

    /** Verifica que el registro destino exista y pertenezca a la compañía activa. */
    private function verificarDuenoDelRegistro(string $tabla, int $registroId, int $companiaId): void
    {
        $existe = DB::table($tabla)
            ->where('id', $registroId)
            ->where('compania_id', $companiaId)
            ->exists();

        abort_unless($existe, 404);
    }
}
