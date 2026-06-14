<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asiento;
use App\Models\PeriodoContable;
use App\Services\CierreAnual;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CierreAnualController extends Controller
{
    public function __construct(private readonly CierreAnual $cierre)
    {
    }

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'anio' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        // Años con saldos de resultado, para el selector.
        $anios = PeriodoContable::query()
            ->where('compania_id', $companiaId)
            ->where('mes', '<=', 12)
            ->distinct()
            ->pluck('anio')
            ->concat([now()->year])
            ->unique()
            ->sortDesc()
            ->values();

        $anio = (int) ($filtros['anio'] ?? $anios->first() ?? now()->year);

        $preview = $this->cierre->previsualizar($companiaId, $anio);
        $asiento = $this->cierre->asientoDe($companiaId, $anio);

        // Borradores del año: avisan que el resultado aún no es definitivo.
        $borradores = Asiento::query()
            ->where('compania_id', $companiaId)
            ->whereYear('fecha', $anio)
            ->where('estado', Asiento::ESTADO_BORRADOR)
            ->count();

        return view('admin.cierre-anual.index', compact(
            'anio', 'anios', 'preview', 'asiento', 'borradores'
        ));
    }

    public function cerrar(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $asiento = $this->cierre->cerrar($companiaId, (int) $data['anio'], $request->user());

        return redirect()->route('admin.cierre-anual.index', ['anio' => $data['anio']])
            ->with('status', "Ejercicio {$data['anio']} cerrado. Asiento {$asiento->numero} posteado en el período de ajuste.");
    }

    public function reversar(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $this->cierre->reversar($companiaId, (int) $data['anio'], $request->user());

        return redirect()->route('admin.cierre-anual.index', ['anio' => $data['anio']])
            ->with('status', "Cierre del ejercicio {$data['anio']} reversado (asiento anulado).");
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
