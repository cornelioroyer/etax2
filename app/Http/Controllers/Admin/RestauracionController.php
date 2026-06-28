<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RestaurarCompaniaJob;
use App\Models\Respaldo;
use App\Models\Restauracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Restauración de un respaldo lógico en una compañía NUEVA de la misma instancia.
 *
 * Crear una compañía es una acción de aprovisionamiento a nivel de sistema, por
 * eso —además del permiso respaldos.gestionar de la ruta— estas acciones exigen
 * is_admin. El motor vive en App\Services\RestaurarCompania.
 */
class RestauracionController extends Controller
{
    /** Formulario de restauración + historial. */
    public function form(Request $request)
    {
        $this->soloAdmin($request);

        // Respaldos del sistema que se pueden usar como origen (cualquier compañía).
        $respaldos = Respaldo::where('estado', Respaldo::ESTADO_COMPLETADO)
            ->whereNotNull('ruta')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function ($r) {
                $r->compania_nombre = $r->compania->nombre ?? ('Compañía '.$r->compania_id);

                return $r;
            });

        $restauraciones = Restauracion::orderByDesc('id')->limit(50)->get();
        $enCurso = $restauraciones->firstWhere(fn ($r) => ! $r->terminado());

        return view('admin.respaldos.restaurar', compact('respaldos', 'restauraciones', 'enCurso'));
    }

    /** Encola una restauración (origen: respaldo del sistema o .zip subido). */
    public function store(Request $request)
    {
        $this->soloAdmin($request);

        $data = $request->validate([
            'origen_tipo' => ['required', Rule::in(['respaldo', 'archivo'])],
            'respaldo_id' => ['nullable', 'required_if:origen_tipo,respaldo', 'integer', 'exists:respaldos,id'],
            'archivo' => ['nullable', 'required_if:origen_tipo,archivo', 'file', 'mimes:zip', 'max:524288'],
            'compania_destino_nombre' => ['required', 'string', 'max:255'],
        ]);

        $rest = new Restauracion([
            'usuario' => $request->user()->name ?? $request->user()->email,
            'estado' => Restauracion::ESTADO_PENDIENTE,
            'compania_destino_nombre' => $data['compania_destino_nombre'],
        ]);

        if ($data['origen_tipo'] === 'respaldo') {
            $respaldo = Respaldo::findOrFail($data['respaldo_id']);
            $disco = Storage::disk($respaldo->disco ?: config('filesystems.default', 'local'));
            abort_unless($disco->exists($respaldo->ruta), 404, 'El archivo del respaldo ya no existe.');

            // Disco local: se procesa en su sitio (no se borra). Otro disco: se copia a temporal.
            try {
                $rutaLocal = $disco->path($respaldo->ruta);
            } catch (\Throwable $e) {
                $rutaLocal = null;
            }
            if ($rutaLocal && is_file($rutaLocal)) {
                $rest->archivo_tmp = $rutaLocal;
            } else {
                $rest->archivo_tmp = $this->copiarADisco($disco->readStream($respaldo->ruta), $respaldo->archivo ?: 'respaldo.zip');
            }

            $rest->respaldo_id = $respaldo->id;
            $rest->origen = $respaldo->archivo;
            $rest->compania_origen_id = $respaldo->compania_id;
        } else {
            $archivo = $request->file('archivo');
            $rest->archivo_tmp = $this->moverSubidaADisco($archivo);
            $rest->origen = $archivo->getClientOriginalName();
        }

        $rest->save();

        RestaurarCompaniaJob::dispatch($rest->id);

        return redirect()
            ->route('admin.restauraciones.form')
            ->with('success', 'Restauración en proceso. Se creará la compañía «'.$data['compania_destino_nombre'].'» al terminar.');
    }

    /** Estado JSON para la barra de progreso (polling). */
    public function estado(Request $request, Restauracion $restauracion)
    {
        $this->soloAdmin($request);

        return response()->json([
            'estado' => $restauracion->estado,
            'porcentaje' => $restauracion->porcentaje(),
            'tablas_procesadas' => $restauracion->tablas_procesadas,
            'total_tablas' => $restauracion->total_tablas,
            'total_filas' => $restauracion->total_filas,
            'terminado' => $restauracion->terminado(),
            'compania_destino_id' => $restauracion->compania_destino_id,
            'mensaje_error' => $restauracion->mensaje_error,
        ]);
    }

    private function soloAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403, 'Solo un administrador del sistema puede restaurar respaldos.');
    }

    /** Mueve un archivo subido al directorio temporal escribible por el worker. */
    private function moverSubidaADisco($archivo): string
    {
        $dir = storage_path('app/private/restauraciones-tmp');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            abort(500, 'No se pudo preparar el área temporal de restauración.');
        }
        $nombre = 'upload_'.uniqid().'.zip';
        $archivo->move($dir, $nombre);

        return $dir.'/'.$nombre;
    }

    /** Copia un stream (respaldo en disco remoto) al área temporal local. */
    private function copiarADisco($stream, string $nombreSugerido): string
    {
        $dir = storage_path('app/private/restauraciones-tmp');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            abort(500, 'No se pudo preparar el área temporal de restauración.');
        }
        $destino = $dir.'/copia_'.uniqid().'.zip';
        $out = fopen($destino, 'w');
        stream_copy_to_stream($stream, $out);
        fclose($out);
        is_resource($stream) && fclose($stream);

        return $destino;
    }
}
