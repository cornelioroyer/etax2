<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\TallerPresupuesto;
use App\Models\TallerPresupuestoDetalle;
use App\Models\TallerTaller;
use App\Models\TallerEquipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TallerPresupuestoController extends Controller
{
    use ConCompaniaActiva;

    // ── Listado ───────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('taller.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $search   = trim($request->input('q', ''));
        $tallerId = $request->input('taller_id');
        $estado   = $request->input('estado');

        $talleres = TallerTaller::where('compania_id', $companiaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $presupuestos = TallerPresupuesto::where('compania_id', $companiaId)
            ->when($tallerId, fn ($q) => $q->where('taller_id', $tallerId))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($search !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('numero', 'ilike', "%{$search}%")
                ->orWhere('descripcion', 'ilike', "%{$search}%")
            ))
            ->with(['taller', 'cliente'])
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.taller.presupuestos.index', compact(
            'presupuestos', 'search', 'talleres', 'tallerId', 'estado'
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
        $equipos  = collect();

        if ($tallerId) {
            $equipos = TallerEquipo::where('taller_id', $tallerId)
                ->orderBy('nombre')
                ->get();
        }

        return view('admin.taller.presupuestos.create', compact(
            'talleres', 'tallerId', 'equipos'
        ));
    }

    // ── Guardar ───────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'taller_id'         => ['required', 'integer'],
            'cliente_id'        => ['nullable', 'integer'],
            'equipo_id'         => ['nullable', 'integer'],
            'descripcion'       => ['nullable', 'string'],
            'fecha'             => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
        ]);

        $taller = TallerTaller::findOrFail($data['taller_id']);
        abort_unless($taller->compania_id === $companiaId, 403);

        $numero = TallerPresupuesto::siguienteNumero($taller->id);

        $presupuesto = TallerPresupuesto::create([
            ...$data,
            'compania_id' => $companiaId,
            'numero'      => $numero,
            'fecha'       => $data['fecha'] ?? now()->toDateString(),
            'estado'      => 'borrador',
            'subtotal'    => 0,
            'descuento'   => 0,
            'impuesto'    => 0,
            'total'       => 0,
            'created_by'  => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.presupuestos.show', $presupuesto)
            ->with('status', "Presupuesto {$numero} creado correctamente.");
    }

    // ── Mostrar ───────────────────────────────────────────────────────────────

    public function show(Request $request, TallerPresupuesto $presupuesto): View
    {
        abort_unless($request->user()->can('taller.ver'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);

        $presupuesto->load(['taller', 'cliente', 'equipo', 'detalles']);

        return view('admin.taller.presupuestos.show', compact('presupuesto'));
    }

    // ── Editar ────────────────────────────────────────────────────────────────

    public function edit(Request $request, TallerPresupuesto $presupuesto): View
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);

        $presupuesto->load('taller');

        return view('admin.taller.presupuestos.edit', compact('presupuesto'));
    }

    // ── Actualizar ────────────────────────────────────────────────────────────

    public function update(Request $request, TallerPresupuesto $presupuesto): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'cliente_id'        => ['nullable', 'integer'],
            'equipo_id'         => ['nullable', 'integer'],
            'descripcion'       => ['nullable', 'string'],
            'fecha'             => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
        ]);

        $presupuesto->update([
            ...$data,
            'updated_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.taller.presupuestos.show', $presupuesto)
            ->with('status', 'Presupuesto actualizado.');
    }

    // ── Cambiar estado ────────────────────────────────────────────────────────

    public function cambiarEstado(Request $request, TallerPresupuesto $presupuesto): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'estado' => ['required', 'string', 'in:' . implode(',', array_keys(TallerPresupuesto::ESTADOS))],
        ]);

        $presupuesto->update([
            'estado'     => $data['estado'],
            'updated_by' => $request->user()->email,
        ]);

        return back()->with('status', 'Estado actualizado a: ' . (TallerPresupuesto::ESTADOS[$data['estado']] ?? $data['estado']));
    }

    // ── Detalles ──────────────────────────────────────────────────────────────

    public function storeDetalle(Request $request, TallerPresupuesto $presupuesto): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'tipo_linea'      => ['required', 'string', 'in:servicio,repuesto,mano_obra,externo,descuento,otro'],
            'descripcion'     => ['required', 'string', 'max:1000'],
            'cantidad'        => ['required', 'numeric', 'min:0.01'],
            'precio_unitario' => ['required', 'numeric', 'min:0'],
            'descuento'       => ['nullable', 'numeric', 'min:0'],
        ]);

        $descuento = (float) ($data['descuento'] ?? 0);
        $total     = round($data['cantidad'] * $data['precio_unitario'] - $descuento, 2);

        TallerPresupuestoDetalle::create([
            ...$data,
            'presupuesto_id'  => $presupuesto->id,
            'descuento'       => $descuento,
            'total'           => $total,
        ]);

        $this->recalcularTotales($presupuesto);

        return back()->with('status', 'Línea agregada.');
    }

    public function destroyDetalle(Request $request, TallerPresupuesto $presupuesto, TallerPresupuestoDetalle $detalle): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($detalle->presupuesto_id === $presupuesto->id, 404);

        $detalle->delete();
        $this->recalcularTotales($presupuesto);

        return back()->with('status', 'Línea eliminada.');
    }

    // ── Eliminar ──────────────────────────────────────────────────────────────

    public function destroy(Request $request, TallerPresupuesto $presupuesto): RedirectResponse
    {
        abort_unless($request->user()->can('taller.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($presupuesto->estado === 'borrador', 422);

        $presupuesto->detalles()->delete();
        $presupuesto->delete();

        return redirect()->route('admin.taller.presupuestos.index')
            ->with('status', 'Presupuesto eliminado.');
    }

    // ── Helper privado ────────────────────────────────────────────────────────

    private function recalcularTotales(TallerPresupuesto $presupuesto): void
    {
        $subtotal = $presupuesto->detalles()->sum('total');
        $subtotal = round((float) $subtotal, 2);

        $presupuesto->update([
            'subtotal' => $subtotal,
            'total'    => $subtotal,
        ]);
    }
}
