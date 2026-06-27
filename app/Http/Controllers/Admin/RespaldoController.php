<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerarRespaldoCompania;
use App\Models\Respaldo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RespaldoController extends Controller
{
    /** Historial de respaldos de la compañía activa. */
    public function index(Request $request)
    {
        $companiaId = $this->companiaActivaId($request);

        $respaldos = Respaldo::where('compania_id', $companiaId)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        // ¿Hay alguno en curso? Para que la vista active el polling.
        $enCurso = $respaldos->firstWhere(fn ($r) => ! $r->terminado());

        return view('admin.respaldos.index', compact('respaldos', 'enCurso'));
    }

    /** Encola la generación de un respaldo para la compañía activa. */
    public function store(Request $request)
    {
        $companiaId = $this->companiaActivaId($request);

        // Evitar encolar dos respaldos simultáneos de la misma compañía.
        $enCurso = Respaldo::where('compania_id', $companiaId)
            ->whereIn('estado', [Respaldo::ESTADO_PENDIENTE, Respaldo::ESTADO_PROCESANDO])
            ->exists();

        if ($enCurso) {
            return redirect()
                ->route('admin.respaldos.index')
                ->with('error', 'Ya hay un respaldo en proceso para esta compañía.');
        }

        $respaldo = Respaldo::create([
            'compania_id' => $companiaId,
            'usuario' => $request->user()->name ?? $request->user()->email,
            'estado' => Respaldo::ESTADO_PENDIENTE,
        ]);

        GenerarRespaldoCompania::dispatch($respaldo->id);

        return redirect()
            ->route('admin.respaldos.index')
            ->with('success', 'Respaldo en proceso. La descarga estará disponible al terminar.');
    }

    /** Estado JSON para la barra de progreso (polling). */
    public function estado(Request $request, Respaldo $respaldo)
    {
        $this->verificarCompania($request, $respaldo);

        return response()->json([
            'estado' => $respaldo->estado,
            'porcentaje' => $respaldo->porcentaje(),
            'tablas_procesadas' => $respaldo->tablas_procesadas,
            'total_tablas' => $respaldo->total_tablas,
            'total_filas' => $respaldo->total_filas,
            'terminado' => $respaldo->terminado(),
            'tamano' => $respaldo->tamanoLegible(),
            'mensaje_error' => $respaldo->mensaje_error,
        ]);
    }

    /** Descarga autorizada del ZIP (stream desde disco privado). */
    public function download(Request $request, Respaldo $respaldo): StreamedResponse
    {
        $this->verificarCompania($request, $respaldo);

        abort_unless(
            $respaldo->estado === Respaldo::ESTADO_COMPLETADO && $respaldo->ruta,
            404,
            'El respaldo no está disponible.'
        );

        $disco = Storage::disk($respaldo->disco ?: config('filesystems.default', 'local'));
        abort_unless($disco->exists($respaldo->ruta), 404, 'El archivo del respaldo ya no existe.');

        return $disco->download($respaldo->ruta, $respaldo->archivo);
    }

    /** Elimina el respaldo (archivo + registro). */
    public function destroy(Request $request, Respaldo $respaldo)
    {
        $this->verificarCompania($request, $respaldo);

        if ($respaldo->ruta) {
            $disco = Storage::disk($respaldo->disco ?: config('filesystems.default', 'local'));
            $disco->exists($respaldo->ruta) && $disco->delete($respaldo->ruta);
        }

        $respaldo->delete();

        return redirect()
            ->route('admin.respaldos.index')
            ->with('success', 'Respaldo eliminado.');
    }

    private function companiaActivaId(Request $request): int
    {
        $companiaId = session('compania_activa_id');

        abort_if(! $companiaId, 404, 'No hay compañía activa.');
        abort_unless(
            $request->user()->is_admin || $request->user()->companiasAccesibles()->contains('id', $companiaId),
            403
        );

        return (int) $companiaId;
    }

    /** Aísla por compañía: un respaldo solo es accesible desde su compañía activa. */
    private function verificarCompania(Request $request, Respaldo $respaldo): void
    {
        abort_unless((int) $respaldo->compania_id === $this->companiaActivaId($request), 404);
    }
}
