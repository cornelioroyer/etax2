<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\TallerCita;
use App\Models\TallerTaller;
use App\Models\TallerEquipo;
use App\Models\TallerTecnico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallerCitaController extends Controller
{
    use ConCompaniaActiva;

    // ── Listado ───────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('taller.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $tallerId = $request->input('taller_id');
        $estado   = $request->input('estado');
        $fecha    = $request->input('fecha');

        $talleres = TallerTaller::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $citas = TallerCita::where('compania_id', $companiaId)
            ->when($tallerId, fn ($q) => $q->where('taller_id', $tallerId))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($fecha, fn ($q) => $q->whereDate('fecha_inicio', $fecha))
            ->with(['taller', 'cliente', 'tecnico'])
            ->orderBy('fecha_inicio', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.taller.citas.index', compact(
            'citas', 'talleres', 'tallerId', 'estado', 'fecha'
        ));
    }

    // ── Crear ─────────────────────────────────────────────────────────────────

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $talleres = TallerTaller::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $tallerId = $request->input('taller_id');
        $tecnicos = collect();
        $sucursales = collect();
        $areas = collect();

        if ($tallerId) {
            $taller = $talleres->firstWhere('id', $tallerId);
            if ($taller) {
                $taller->load(['sucursales', 'areas', 'tecnicos']);
                $tecnicos   = $taller->tecnicos;
                $sucursales = $taller->sucursales ?? collect();
                $areas      = $taller->areas ?? collect();
            }
        }

        return view('admin.taller.citas.create', compact(
            'talleres', 'tallerId', 'tecnicos', 'sucursales', 'areas'
        ));
    }

    // ── Guardar ───────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'taller_id'    => ['required', 'integer'],
            'sucursal_id'  => ['nullable', 'integer'],
            'area_id'      => ['nullable', 'integer'],
            'tecnico_id'   => ['nullable', 'integer'],
            'cliente_id'   => ['nullable', 'integer'],
            'equipo_id'    => ['nullable', 'integer'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo'       => ['nullable', 'string'],
            'estado'       => ['required', 'string', 'in:' . implode(',', array_keys(TallerCita::ESTADOS))],
        ]);

        $taller = TallerTaller::findOrFail($data['taller_id']);
        abort_unless($taller->compania_id === $companiaId, 403);

        $cita = TallerCita::create([
            ...$data,
            'compania_id' => $companiaId,
            'created_by'  => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.citas.index')
            ->with('status', 'Cita registrada correctamente.');
    }

    // ── Editar ────────────────────────────────────────────────────────────────

    public function edit(Request $request, TallerCita $cita): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($cita->compania_id === $this->companiaActivaId($request), 404);

        $cita->load('taller');

        $companiaId = $this->companiaActivaId($request);
        $talleres = TallerTaller::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $tecnicos   = TallerTecnico::where('taller_id', $cita->taller_id)->where('activo', true)->orderBy('nombre_publico')->get();
        $sucursales = \App\Models\TallerSucursal::where('taller_id', $cita->taller_id)->orderBy('nombre')->get();
        $areas      = \App\Models\TallerArea::where('taller_id', $cita->taller_id)->orderBy('nombre')->get();

        return view('admin.taller.citas.edit', compact(
            'cita', 'talleres', 'tecnicos', 'sucursales', 'areas'
        ));
    }

    // ── Actualizar ────────────────────────────────────────────────────────────

    public function update(Request $request, TallerCita $cita): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($cita->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'sucursal_id'  => ['nullable', 'integer'],
            'area_id'      => ['nullable', 'integer'],
            'tecnico_id'   => ['nullable', 'integer'],
            'cliente_id'   => ['nullable', 'integer'],
            'equipo_id'    => ['nullable', 'integer'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo'       => ['nullable', 'string'],
            'estado'       => ['required', 'string', 'in:' . implode(',', array_keys(TallerCita::ESTADOS))],
        ]);

        $cita->update([
            ...$data,
            'updated_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.citas.index')
            ->with('status', 'Cita actualizada.');
    }

    // ── Eliminar ──────────────────────────────────────────────────────────────

    public function destroy(Request $request, TallerCita $cita): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($cita->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($cita->estado !== 'atendida', 422);

        $cita->delete();

        return redirect()->route('admin.taller.citas.index')
            ->with('status', 'Cita eliminada.');
    }
}
