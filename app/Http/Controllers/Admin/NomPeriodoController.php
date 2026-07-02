<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\NomConfiguracion;
use App\Models\NomEmpleado;
use App\Models\NomPeriodo;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NomPeriodoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('nomina.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $anio = (int) $request->query('anio', now()->year);
        $config = NomConfiguracion::deCompania($companiaId);

        $items = NomPeriodo::where('compania_id', $companiaId)
            ->where('anio', $anio)
            ->orderBy('tipo_planilla')
            ->orderBy('numero')
            ->get();

        return view('admin.nomina.periodos.index', compact('items', 'anio', 'config'));
    }

    /**
     * Genera de una vez todos los períodos de un año para un tipo de planilla.
     * QUINCENAL: 1-15 (pago 15) y 16-fin de mes (pago fin de mes).
     * MENSUAL: mes completo (pago fin de mes). SEMANAL: lunes a domingo
     * (pago viernes siguiente). Idempotente: no duplica los ya existentes.
     */
    public function generarAnio(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'tipo_planilla' => ['required', Rule::in(array_keys(NomEmpleado::TIPOS_PLANILLA))],
        ]);

        $anio = (int) $data['anio'];
        $tipo = $data['tipo_planilla'];
        $usuario = $request->user()->email;
        $creados = 0;

        DB::transaction(function () use ($companiaId, $anio, $tipo, $usuario, &$creados) {
            $existentes = NomPeriodo::where('compania_id', $companiaId)
                ->where('tipo_planilla', $tipo)
                ->where('anio', $anio)
                ->pluck('numero')
                ->all();

            foreach ($this->calendario($anio, $tipo) as $numero => [$desde, $hasta, $pago]) {
                if (in_array($numero, $existentes, true)) {
                    continue;
                }

                NomPeriodo::create([
                    'compania_id' => $companiaId,
                    'tipo_planilla' => $tipo,
                    'anio' => $anio,
                    'numero' => $numero,
                    'desde' => $desde->toDateString(),
                    'hasta' => $hasta->toDateString(),
                    'fecha_pago' => $pago->toDateString(),
                    'estado' => NomPeriodo::ESTADO_ABIERTO,
                    'created_by' => $usuario,
                ]);

                $creados++;
            }
        });

        return back()->with('status', $creados > 0
            ? "$creados períodos $tipo de $anio creados."
            : "Los períodos $tipo de $anio ya existían.");
    }

    public function cerrar(Request $request, NomPeriodo $periodo): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        abort_unless($periodo->compania_id === $this->companiaActivaId($request), 404);

        $periodo->update([
            'estado' => NomPeriodo::ESTADO_CERRADO,
            'updated_by' => $request->user()->email,
        ]);

        return back()->with('status', 'Período cerrado.');
    }

    public function reabrir(Request $request, NomPeriodo $periodo): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        abort_unless($periodo->compania_id === $this->companiaActivaId($request), 404);

        $periodo->update([
            'estado' => NomPeriodo::ESTADO_ABIERTO,
            'updated_by' => $request->user()->email,
        ]);

        return back()->with('status', 'Período reabierto.');
    }

    /** Configuración de nómina de la compañía (riesgo profesional, defaults). */
    public function actualizarConfiguracion(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'riesgo_profesional' => ['required', 'numeric', 'min:0', 'max:20'],
            'tipo_planilla_default' => ['required', Rule::in(array_keys(NomEmpleado::TIPOS_PLANILLA))],
        ]);

        NomConfiguracion::deCompania($companiaId)->update(array_merge($data, [
            'updated_by' => $request->user()->email,
        ]));

        return back()->with('status', 'Configuración de nómina actualizada.');
    }

    /**
     * Calendario de un año: numero => [desde, hasta, fecha_pago].
     *
     * @return array<int, array{0:Carbon,1:Carbon,2:Carbon}>
     */
    private function calendario(int $anio, string $tipo): array
    {
        $periodos = [];

        if ($tipo === 'MENSUAL') {
            for ($mes = 1; $mes <= 12; $mes++) {
                $desde = Carbon::create($anio, $mes, 1);
                $hasta = $desde->copy()->endOfMonth()->startOfDay();
                $periodos[$mes] = [$desde, $hasta, $hasta->copy()];
            }
        } elseif ($tipo === 'QUINCENAL') {
            $numero = 1;
            for ($mes = 1; $mes <= 12; $mes++) {
                $finMes = Carbon::create($anio, $mes, 1)->endOfMonth()->startOfDay();
                $periodos[$numero++] = [Carbon::create($anio, $mes, 1), Carbon::create($anio, $mes, 15), Carbon::create($anio, $mes, 15)];
                $periodos[$numero++] = [Carbon::create($anio, $mes, 16), $finMes, $finMes->copy()];
            }
        } else { // SEMANAL: lunes a domingo, pago el viernes de la semana siguiente
            $inicio = Carbon::create($anio, 1, 1)->startOfWeek(Carbon::MONDAY);
            if ($inicio->year < $anio) {
                $inicio->addWeek();
            }

            $numero = 1;
            $cursor = $inicio->copy();
            while ($cursor->year === $anio) {
                $desde = $cursor->copy();
                $hasta = $cursor->copy()->addDays(6);
                $periodos[$numero++] = [$desde, $hasta, $hasta->copy()->next(Carbon::FRIDAY)];
                $cursor->addWeek();
            }
        }

        return $periodos;
    }
}
