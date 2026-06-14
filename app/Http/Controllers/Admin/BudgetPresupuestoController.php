<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\BudgetEscenario;
use App\Models\BudgetPresupuesto;
use App\Models\BudgetPresupuestoDetalle;
use App\Models\CuentaContable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetPresupuestoController extends Controller
{
    use ConCompaniaActiva;

    /** Meses de la columna monto_01..monto_12 (campo => etiqueta). */
    public const MESES = [
        'monto_01' => 'Ene', 'monto_02' => 'Feb', 'monto_03' => 'Mar',
        'monto_04' => 'Abr', 'monto_05' => 'May', 'monto_06' => 'Jun',
        'monto_07' => 'Jul', 'monto_08' => 'Ago', 'monto_09' => 'Sep',
        'monto_10' => 'Oct', 'monto_11' => 'Nov', 'monto_12' => 'Dic',
    ];

    // ── Listado ───────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('presupuestos.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $search      = trim($request->input('q', ''));
        $escenarioId = $request->input('escenario_id');
        $anio        = $request->input('anio');

        $escenarios = BudgetEscenario::where('compania_id', $companiaId)
            ->orderBy('nombre')
            ->get();

        $presupuestos = BudgetPresupuesto::where('compania_id', $companiaId)
            ->when($escenarioId, fn ($q) => $q->where('escenario_id', $escenarioId))
            ->when($anio, fn ($q) => $q->where('anio', $anio))
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%"))
            ->with('escenario')
            ->orderBy('anio', 'desc')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('admin.presupuestos.index', compact(
            'presupuestos', 'escenarios', 'search', 'escenarioId', 'anio'
        ));
    }

    // ── Crear ─────────────────────────────────────────────────────────────────

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $escenarios = BudgetEscenario::where('compania_id', $companiaId)
            ->orderBy('nombre')
            ->get();

        return view('admin.presupuestos.create', compact('escenarios'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'escenario_id' => ['required', 'integer'],
            'nombre'       => ['required', 'string', 'max:150'],
            'anio'         => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $this->resolverEscenario($request, (int) $data['escenario_id']);

        $presupuesto = BudgetPresupuesto::create([
            ...$data,
            'compania_id' => $companiaId,
            'estado'      => BudgetPresupuesto::ESTADO_BORRADOR,
            'created_by'  => $request->user()->email,
        ]);

        return redirect()->route('admin.presupuestos.show', $presupuesto)
            ->with('status', "Presupuesto «{$presupuesto->nombre}» creado.");
    }

    // ── Mostrar ───────────────────────────────────────────────────────────────

    public function show(Request $request, BudgetPresupuesto $presupuesto): View
    {
        abort_unless($request->user()->can('presupuestos.ver'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);

        $presupuesto->load(['escenario', 'detalle.cuenta']);

        $cuentas = CuentaContable::where('compania_id', $presupuesto->compania_id)
            ->where('permite_movimiento', true)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get();

        $totalesMes = [];
        foreach (array_keys(self::MESES) as $col) {
            $totalesMes[$col] = round((float) $presupuesto->detalle->sum($col), 2);
        }

        return view('admin.presupuestos.show', [
            'presupuesto' => $presupuesto,
            'cuentas'     => $cuentas,
            'meses'       => self::MESES,
            'totalesMes'  => $totalesMes,
        ]);
    }

    // ── Editar ────────────────────────────────────────────────────────────────

    public function edit(Request $request, BudgetPresupuesto $presupuesto): View
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);

        $escenarios = BudgetEscenario::where('compania_id', $presupuesto->compania_id)
            ->orderBy('nombre')
            ->get();

        return view('admin.presupuestos.edit', compact('presupuesto', 'escenarios'));
    }

    public function update(Request $request, BudgetPresupuesto $presupuesto): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'escenario_id' => ['required', 'integer'],
            'nombre'       => ['required', 'string', 'max:150'],
            'anio'         => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $this->resolverEscenario($request, (int) $data['escenario_id']);

        $presupuesto->update([
            ...$data,
            'updated_by' => $request->user()->email,
        ]);

        return redirect()->route('admin.presupuestos.show', $presupuesto)
            ->with('status', 'Presupuesto actualizado.');
    }

    // ── Estado ────────────────────────────────────────────────────────────────

    public function cambiarEstado(Request $request, BudgetPresupuesto $presupuesto): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'estado' => ['required', 'string', 'in:' . implode(',', array_keys(BudgetPresupuesto::ESTADOS))],
        ]);

        $presupuesto->update([
            'estado'     => $data['estado'],
            'updated_by' => $request->user()->email,
        ]);

        return back()->with('status', 'Estado actualizado a: ' . (BudgetPresupuesto::ESTADOS[$data['estado']] ?? $data['estado']));
    }

    // ── Detalle (líneas por cuenta) ─────────────────────────────────────────────

    public function storeDetalle(Request $request, BudgetPresupuesto $presupuesto): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);

        $reglasMontos = [];
        foreach (array_keys(self::MESES) as $col) {
            $reglasMontos[$col] = ['nullable', 'numeric'];
        }

        $data = $request->validate([
            'cuenta_id' => ['required', 'integer'],
            ...$reglasMontos,
        ]);

        $cuenta = CuentaContable::findOrFail($data['cuenta_id']);
        abort_unless($cuenta->compania_id === $presupuesto->compania_id, 422, 'Cuenta inválida.');
        abort_if(
            $presupuesto->detalle()->where('cuenta_id', $cuenta->id)->exists(),
            422,
            'Esa cuenta ya tiene una línea en este presupuesto.'
        );

        $montos = [];
        $total  = 0;
        foreach (array_keys(self::MESES) as $col) {
            $monto       = round((float) ($data[$col] ?? 0), 2);
            $montos[$col] = $monto;
            $total       += $monto;
        }

        BudgetPresupuestoDetalle::create([
            'presupuesto_id' => $presupuesto->id,
            'cuenta_id'      => $cuenta->id,
            ...$montos,
            'monto_total'    => round($total, 2),
            'created_by'     => $request->user()->email,
        ]);

        return back()->with('status', "Línea para la cuenta {$cuenta->codigo} agregada.");
    }

    public function destroyDetalle(Request $request, BudgetPresupuesto $presupuesto, BudgetPresupuestoDetalle $detalle): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($detalle->presupuesto_id === $presupuesto->id, 404);

        $detalle->delete();

        return back()->with('status', 'Línea eliminada.');
    }

    // ── Eliminar ──────────────────────────────────────────────────────────────

    public function destroy(Request $request, BudgetPresupuesto $presupuesto): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($presupuesto->estado === BudgetPresupuesto::ESTADO_BORRADOR, 422, 'Solo se pueden eliminar presupuestos en borrador.');

        $presupuesto->detalle()->delete();
        $presupuesto->delete();

        return redirect()->route('admin.presupuestos.index')
            ->with('status', 'Presupuesto eliminado.');
    }

    // ── Helper ──────────────────────────────────────────────────────────────────

    private function resolverEscenario(Request $request, int $escenarioId): BudgetEscenario
    {
        $escenario = BudgetEscenario::findOrFail($escenarioId);
        abort_unless($escenario->compania_id === $this->companiaActivaId($request), 403);
        return $escenario;
    }
}
