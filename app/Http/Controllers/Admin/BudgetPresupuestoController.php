<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\BudgetEscenario;
use App\Models\BudgetPresupuesto;
use App\Models\BudgetPresupuestoDetalle;
use App\Models\BudgetVersion;
use App\Models\CoreCentroCosto;
use App\Models\CoreDepartamento;
use App\Models\CoreProyecto;
use App\Models\CuentaContable;
use App\Models\PeriodoContable;
use App\Services\PresupuestoReal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BudgetPresupuestoController extends Controller
{
    use ConCompaniaActiva;

    // ── Listado ───────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('presupuestos.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $search      = trim($request->input('q', ''));
        $escenarioId = $request->input('escenario_id');
        $versionId   = $request->input('version_id');
        $anio        = $request->input('anio');

        $escenarios = BudgetEscenario::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $versiones  = BudgetVersion::where('compania_id', $companiaId)->orderBy('nombre')->get();

        $presupuestos = BudgetPresupuesto::where('compania_id', $companiaId)
            ->when($escenarioId, fn ($q) => $q->where('escenario_id', $escenarioId))
            ->when($versionId, fn ($q) => $q->where('version_id', $versionId))
            ->when($anio, fn ($q) => $q->where('anio', $anio))
            ->when($search !== '', fn ($q) => $q->where('nombre', 'ilike', "%{$search}%"))
            ->with(['escenario', 'version'])
            ->orderBy('anio', 'desc')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('admin.presupuestos.index', compact(
            'presupuestos', 'escenarios', 'versiones', 'search', 'escenarioId', 'versionId', 'anio'
        ));
    }

    // ── Crear ─────────────────────────────────────────────────────────────────

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        return view('admin.presupuestos.create', [
            'escenarios' => BudgetEscenario::where('compania_id', $companiaId)->orderBy('nombre')->get(),
            'versiones'  => BudgetVersion::where('compania_id', $companiaId)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $this->validarCabecera($request, $companiaId);

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

        $presupuesto->load([
            'escenario', 'version',
            'detalle.cuenta', 'detalle.periodo', 'detalle.centroCosto',
            'detalle.departamento', 'detalle.proyecto',
        ]);

        $companiaId = $presupuesto->compania_id;

        return view('admin.presupuestos.show', [
            'presupuesto'   => $presupuesto,
            'cuentas'       => CuentaContable::where('compania_id', $companiaId)
                ->where('permite_movimiento', true)->where('activa', true)
                ->orderBy('codigo')->get(),
            'periodos'      => PeriodoContable::where('compania_id', $companiaId)
                ->where('anio', $presupuesto->anio)->orderBy('mes')->get(),
            'centrosCosto'  => CoreCentroCosto::where('compania_id', $companiaId)
                ->where('activo', true)->orderBy('codigo')->get(),
            'departamentos' => CoreDepartamento::where('compania_id', $companiaId)
                ->where('activo', true)->orderBy('codigo')->get(),
            'proyectos'     => CoreProyecto::where('compania_id', $companiaId)
                ->orderBy('codigo')->get(),
            'totales'       => [
                'presupuestado' => round((float) $presupuesto->detalle->sum('monto_presupuestado'), 2),
                'real'          => round((float) $presupuesto->detalle->sum('monto_real'), 2),
                'variacion'     => round((float) $presupuesto->detalle->sum('variacion'), 2),
            ],
        ]);
    }

    // ── Editar ────────────────────────────────────────────────────────────────

    public function edit(Request $request, BudgetPresupuesto $presupuesto): View
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);

        return view('admin.presupuestos.edit', [
            'presupuesto' => $presupuesto,
            'escenarios'  => BudgetEscenario::where('compania_id', $presupuesto->compania_id)->orderBy('nombre')->get(),
            'versiones'   => BudgetVersion::where('compania_id', $presupuesto->compania_id)->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, BudgetPresupuesto $presupuesto): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);

        $data = $this->validarCabecera($request, $presupuesto->compania_id, $presupuesto->id);

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

    // ── Calcular real (presupuesto vs. ejecutado) ───────────────────────────────

    public function calcularReal(Request $request, BudgetPresupuesto $presupuesto, PresupuestoReal $servicio): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);

        $lineas = $servicio->calcular($presupuesto);

        return back()->with('status', $lineas > 0
            ? "Real recalculado desde los asientos: {$lineas} línea(s) actualizada(s)."
            : 'No hay líneas de detalle que recalcular.');
    }

    // ── Detalle (líneas por cuenta/periodo) ─────────────────────────────────────

    public function storeDetalle(Request $request, BudgetPresupuesto $presupuesto): RedirectResponse
    {
        abort_unless($request->user()->can('presupuestos.gestionar'), 403);
        abort_unless($presupuesto->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'cuenta_id'           => ['required', 'integer'],
            'periodo_id'          => ['nullable', 'integer'],
            'centro_costo_id'     => ['nullable', 'integer'],
            'departamento_id'     => ['nullable', 'integer'],
            'proyecto_id'         => ['nullable', 'integer'],
            'monto_presupuestado' => ['required', 'numeric'],
        ]);

        $companiaId = $presupuesto->compania_id;

        // La cuenta debe ser de la compañía. Las dimensiones, si se indican, también.
        $cuenta = CuentaContable::findOrFail($data['cuenta_id']);
        abort_unless($cuenta->compania_id === $companiaId, 422, 'Cuenta inválida.');
        $this->validarPertenencia($data, $companiaId);

        BudgetPresupuestoDetalle::create([
            'presupuesto_id'      => $presupuesto->id,
            'cuenta_id'           => $cuenta->id,
            'periodo_id'          => $data['periodo_id'] ?? null,
            'centro_costo_id'     => $data['centro_costo_id'] ?? null,
            'departamento_id'     => $data['departamento_id'] ?? null,
            'proyecto_id'         => $data['proyecto_id'] ?? null,
            'monto_presupuestado' => round((float) $data['monto_presupuestado'], 2),
            'created_by'          => $request->user()->email,
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

    // ── Helpers ──────────────────────────────────────────────────────────────────

    /** Valida la cabecera del presupuesto (compartido entre store/update). */
    private function validarCabecera(Request $request, int $companiaId, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'nombre'       => [
                'required', 'string', 'max:150',
                Rule::unique('budget_presupuestos')
                    ->where(fn ($q) => $q->where('compania_id', $companiaId)->where('anio', $request->input('anio')))
                    ->ignore($ignoreId),
            ],
            'anio'         => ['required', 'integer', 'min:2000', 'max:2100'],
            'escenario_id' => ['nullable', 'integer'],
            'version_id'   => ['nullable', 'integer'],
        ]);

        if (! empty($data['escenario_id'])) {
            $escenario = BudgetEscenario::findOrFail($data['escenario_id']);
            abort_unless($escenario->compania_id === $companiaId, 422, 'Escenario inválido.');
        }
        if (! empty($data['version_id'])) {
            $version = BudgetVersion::findOrFail($data['version_id']);
            abort_unless($version->compania_id === $companiaId, 422, 'Versión inválida.');
        }

        return $data;
    }

    /** Verifica que las dimensiones indicadas pertenezcan a la compañía. */
    private function validarPertenencia(array $data, int $companiaId): void
    {
        $checks = [
            'periodo_id'      => PeriodoContable::class,
            'centro_costo_id' => CoreCentroCosto::class,
            'departamento_id' => CoreDepartamento::class,
            'proyecto_id'     => CoreProyecto::class,
        ];

        foreach ($checks as $campo => $modelo) {
            if (! empty($data[$campo])) {
                $registro = $modelo::findOrFail($data[$campo]);
                abort_unless($registro->compania_id === $companiaId, 422, 'Dimensión inválida.');
            }
        }
    }
}
