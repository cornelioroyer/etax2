<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\TallerChecklist;
use App\Models\TallerChecklistDetalle;
use App\Models\TallerTaller;
use App\Models\TallerTipoEquipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallerChecklistController extends Controller
{
    use ConCompaniaActiva;

    private function resolverTaller(Request $request, int $tallerId): TallerTaller
    {
        $taller = TallerTaller::findOrFail($tallerId);
        abort_unless($taller->compania_id === $this->companiaActivaId($request), 404);
        return $taller;
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('taller.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $tallerId = $request->input('taller_id');
        $search   = trim($request->input('q', ''));

        $checklists = TallerChecklist::whereHas('taller', fn ($q) => $q->where('compania_id', $companiaId))
            ->with(['taller', 'tipoEquipo'])
            ->withCount('detalles')
            ->when($tallerId, fn ($q) => $q->where('taller_id', $tallerId))
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%")
                ->orWhere('codigo', 'ilike', "%{$search}%"))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        $talleres     = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerActual = $tallerId ? TallerTaller::find($tallerId) : null;

        return view('admin.taller.checklists.index', compact('checklists', 'talleres', 'tallerActual', 'search', 'tallerId'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId  = $this->companiaActivaId($request);
        $talleres    = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tallerId    = $request->input('taller_id');
        $tiposEquipo = $tallerId ? TallerTipoEquipo::where('taller_id', $tallerId)->orderBy('nombre')->get() : collect();
        return view('admin.taller.checklists.create', compact('talleres', 'tallerId', 'tiposEquipo'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);

        $data = $request->validate([
            'taller_id'      => ['required', 'integer', 'exists:taller_talleres,id'],
            'tipo_equipo_id' => ['nullable', 'integer', 'exists:taller_tipos_equipo,id'],
            'codigo'         => ['required', 'string', 'max:30'],
            'nombre'         => ['required', 'string', 'max:200'],
            'tipo_checklist' => ['required', 'string', 'in:' . implode(',', array_keys(TallerChecklist::TIPOS))],
        ]);

        $this->resolverTaller($request, (int) $data['taller_id']);

        $checklist = TallerChecklist::create([
            ...$data,
            'activo'     => true,
            'created_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.checklists.show', $checklist)
            ->with('status', "Checklist {$checklist->nombre} creado.");
    }

    public function show(Request $request, TallerChecklist $checklist): View
    {
        abort_unless($request->user()->can('taller.ver'), 403);
        $this->resolverTaller($request, $checklist->taller_id);

        $checklist->load(['taller', 'tipoEquipo', 'detalles']);

        return view('admin.taller.checklists.show', compact('checklist'));
    }

    public function edit(Request $request, TallerChecklist $checklist): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $checklist->taller_id);

        $checklist->load('detalles');
        $companiaId  = $this->companiaActivaId($request);
        $talleres    = TallerTaller::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tiposEquipo = TallerTipoEquipo::where('taller_id', $checklist->taller_id)->orderBy('nombre')->get();

        return view('admin.taller.checklists.edit', compact('checklist', 'talleres', 'tiposEquipo'));
    }

    public function update(Request $request, TallerChecklist $checklist): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $checklist->taller_id);

        $data = $request->validate([
            'tipo_equipo_id' => ['nullable', 'integer', 'exists:taller_tipos_equipo,id'],
            'codigo'         => ['required', 'string', 'max:30'],
            'nombre'         => ['required', 'string', 'max:200'],
            'tipo_checklist' => ['required', 'string', 'in:' . implode(',', array_keys(TallerChecklist::TIPOS))],
            'activo'         => ['boolean'],
        ]);

        $checklist->update([...$data, 'updated_by' => $request->user()->email]);

        return redirect()->route('admin.taller.checklists.show', $checklist)
            ->with('status', 'Checklist actualizado.');
    }

    public function destroy(Request $request, TallerChecklist $checklist): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $checklist->taller_id);

        $tallerId = $checklist->taller_id;
        $checklist->detalles()->delete();
        $checklist->delete();

        return redirect()->route('admin.taller.checklists.index', ['taller_id' => $tallerId])
            ->with('status', 'Checklist eliminado.');
    }

    // ── Gestión inline de detalles ──────────────────────────────────────────

    public function storeDetalle(Request $request, TallerChecklist $checklist): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $checklist->taller_id);

        $data = $request->validate([
            'codigo'         => ['required', 'string', 'max:30'],
            'descripcion'    => ['required', 'string', 'max:500'],
            'tipo_respuesta' => ['required', 'string', 'in:' . implode(',', array_keys(TallerChecklistDetalle::TIPOS_RESPUESTA))],
            'obligatorio'    => ['boolean'],
            'orden'          => ['nullable', 'integer', 'min:0'],
        ]);

        $maxOrden = $checklist->detalles()->max('orden') ?? 0;

        TallerChecklistDetalle::create([
            ...$data,
            'checklist_id' => $checklist->id,
            'orden'        => $data['orden'] ?? ($maxOrden + 10),
            'activo'       => true,
        ]);

        return redirect()->route('admin.taller.checklists.edit', $checklist)
            ->with('status', 'Ítem agregado.');
    }

    public function destroyDetalle(Request $request, TallerChecklist $checklist, TallerChecklistDetalle $detalle): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $this->resolverTaller($request, $checklist->taller_id);
        abort_unless($detalle->checklist_id === $checklist->id, 404);

        $detalle->delete();

        return redirect()->route('admin.taller.checklists.edit', $checklist)
            ->with('status', 'Ítem eliminado.');
    }
}
