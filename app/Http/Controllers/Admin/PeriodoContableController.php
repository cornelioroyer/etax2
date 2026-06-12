<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asiento;
use App\Models\PeriodoContable;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PeriodoContableController extends Controller
{
    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'anio' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $anio = (int) ($filtros['anio'] ?? now()->year);

        $periodos = PeriodoContable::query()
            ->with('cerradoPor')
            ->where('compania_id', $companiaId)
            ->where('anio', $anio)
            ->get()
            ->keyBy('mes');

        // Asientos posteados y borradores por mes, para mostrar actividad del período
        $asientosPorMes = Asiento::query()
            ->where('compania_id', $companiaId)
            ->whereYear('fecha', $anio)
            ->whereIn('estado', [Asiento::ESTADO_POSTEADO, Asiento::ESTADO_BORRADOR])
            ->selectRaw('EXTRACT(MONTH FROM fecha)::int AS mes, estado, COUNT(*) AS total')
            ->groupBy('mes', 'estado')
            ->get()
            ->groupBy('mes')
            ->map(fn ($grupo) => $grupo->pluck('total', 'estado'));

        // Años con períodos o asientos, para el selector
        $anios = PeriodoContable::query()
            ->where('compania_id', $companiaId)
            ->distinct()
            ->pluck('anio')
            ->concat([now()->year, $anio])
            ->unique()
            ->sortDesc()
            ->values();

        return view('admin.periodos.index', compact('periodos', 'asientosPorMes', 'anio', 'anios'));
    }

    /**
     * Cierra el período del mes indicado (lo crea primero si nunca se ha usado).
     */
    public function cerrar(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);

        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $usuario = $request->user();
        $fecha = Carbon::create($data['anio'], $data['mes'], 1);

        $periodo = PeriodoContable::paraFecha($companiaId, $fecha, $usuario->email);

        if (! $periodo->estaAbierto()) {
            return back()->withErrors(['periodo' => "El período {$periodo->anio}-".str_pad((string) $periodo->mes, 2, '0', STR_PAD_LEFT).' ya está cerrado.']);
        }

        $borradores = Asiento::query()
            ->where('compania_id', $companiaId)
            ->whereBetween('fecha', [$periodo->fecha_inicio, $periodo->fecha_fin])
            ->where('estado', Asiento::ESTADO_BORRADOR)
            ->count();

        if ($borradores > 0 && ! $request->boolean('forzar')) {
            return back()->withErrors([
                'periodo' => "El período tiene {$borradores} asiento(s) en borrador que ya no se podrán postear en esa fecha. Marca \"cerrar de todos modos\" para continuar.",
            ])->withInput(['mes_confirmar' => $periodo->mes]);
        }

        $periodo->update([
            'estado' => PeriodoContable::ESTADO_CERRADO,
            'cerrado_por' => $usuario->id,
            'fecha_cierre' => now(),
            'updated_by' => $usuario->email,
        ]);

        return redirect()->route('admin.periodos.index', ['anio' => $periodo->anio])
            ->with('status', "Período {$periodo->anio}-".str_pad((string) $periodo->mes, 2, '0', STR_PAD_LEFT).' cerrado.');
    }

    /**
     * Reabre un período cerrado; exige motivo y lo registra en audit_reaperturas.
     */
    public function reabrir(Request $request, PeriodoContable $periodo): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        abort_unless($periodo->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'motivo.required' => 'Indica el motivo de la reapertura.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        if ($periodo->estaAbierto()) {
            return back()->withErrors(['periodo' => 'El período ya está abierto.']);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($periodo, $data, $usuario) {
            $periodo->update([
                'estado' => PeriodoContable::ESTADO_ABIERTO,
                'cerrado_por' => null,
                'fecha_cierre' => null,
                'updated_by' => $usuario->email,
            ]);

            DB::table('audit_reaperturas')->insert([
                'periodo_id' => $periodo->id,
                'motivo' => $data['motivo'],
                'usuario_id' => $usuario->id,
                'created_at' => now(),
                'created_by' => $usuario->email,
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.periodos.index', ['anio' => $periodo->anio])
            ->with('status', "Período {$periodo->anio}-".str_pad((string) $periodo->mes, 2, '0', STR_PAD_LEFT).' reabierto.');
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
}
