<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\CglCierre;
use App\Models\PeriodoContable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CglCierreController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $cierres = CglCierre::with('periodo')
            ->where('compania_id', $companiaId)
            ->orderByDesc('created_at')
            ->paginate(15);

        $periodosSinCierre = PeriodoContable::where('compania_id', $companiaId)
            ->whereNotIn('id', CglCierre::where('compania_id', $companiaId)->pluck('periodo_id'))
            ->orderByDesc('fecha_inicio')
            ->get();

        return view('admin.contabilidad.cierres.index', compact('cierres', 'periodosSinCierre'));
    }

    public function show(Request $request, CglCierre $cierre): View
    {
        abort_unless($cierre->compania_id === $this->companiaActivaId($request), 404);
        $cierre->load(['periodo', 'detalle']);

        return view('admin.contabilidad.cierres.show', compact('cierre'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'periodo_id'  => ['required', 'integer', 'exists:cgl_periodos,id'],
            'observacion' => ['nullable', 'string'],
        ]);

        $periodo = PeriodoContable::where('compania_id', $companiaId)->findOrFail($data['periodo_id']);

        $existe = CglCierre::where('compania_id', $companiaId)
            ->where('periodo_id', $periodo->id)
            ->exists();

        if ($existe) {
            return back()->withErrors(['periodo_id' => 'Ya existe un registro de cierre para este período.']);
        }

        CglCierre::create([
            'compania_id' => $companiaId,
            'periodo_id'  => $periodo->id,
            'estado'      => CglCierre::ESTADO_PENDIENTE,
            'observacion' => $data['observacion'] ?? null,
            'created_by'  => $request->user()->email,
        ]);

        return redirect()->route('admin.contabilidad.cierres.index')->with('status', "Cierre para {$periodo->nombre} creado.");
    }

    public function cerrar(Request $request, CglCierre $cierre): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.gestionar'), 403);
        abort_unless($cierre->compania_id === $this->companiaActivaId($request), 404);

        if ($cierre->estaCompletado()) {
            return back()->withErrors(['cierre' => 'El cierre ya está completado.']);
        }

        $cierre->update([
            'estado'      => CglCierre::ESTADO_COMPLETADO,
            'cerrado_por' => $request->user()->id,
            'fecha_cierre' => now(),
            'updated_by'  => $request->user()->email,
        ]);

        // Cerrar el período contable
        $cierre->periodo->update(['estado' => 'CERRADO', 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.contabilidad.cierres.show', $cierre)
            ->with('status', 'Período cerrado correctamente.');
    }
}
