<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\NomPeriodo;
use App\Models\NomPlanilla;
use App\Services\NomMotorPlanilla;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NomPlanillaController extends Controller
{
    use ConCompaniaActiva;

    public function __construct(private NomMotorPlanilla $motor)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('nomina.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $items = NomPlanilla::where('compania_id', $companiaId)
            ->with('periodo')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.nomina.planillas.index', compact('items'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $periodos = NomPeriodo::where('compania_id', $companiaId)
            ->where('estado', NomPeriodo::ESTADO_ABIERTO)
            ->orderByDesc('anio')
            ->orderByDesc('numero')
            ->limit(60)
            ->get();

        return view('admin.nomina.planillas.create', compact('periodos'));
    }

    /** Crea la corrida y la calcula de una vez (queda PROCESADA, revisable). */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'periodo_id' => ['required', Rule::exists('nom_periodos', 'id')->where('compania_id', $companiaId)],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:300'],
        ]);

        $periodo = NomPeriodo::findOrFail($data['periodo_id']);

        $repetida = NomPlanilla::where('compania_id', $companiaId)
            ->where('periodo_id', $periodo->id)
            ->where('tipo', NomPlanilla::TIPO_REGULAR)
            ->where('estado', '!=', NomPlanilla::ESTADO_ANULADA)
            ->exists();

        if ($repetida) {
            return back()->withErrors(['periodo_id' => 'Ya existe una planilla regular vigente para ese período (anúlala primero si necesitas repetirla).'])->withInput();
        }

        $planilla = DB::transaction(function () use ($companiaId, $periodo, $data, $request) {
            $planilla = NomPlanilla::create([
                'compania_id' => $companiaId,
                'periodo_id' => $periodo->id,
                'numero' => NomPlanilla::siguienteNumero($companiaId),
                'tipo' => NomPlanilla::TIPO_REGULAR,
                'descripcion' => $data['descripcion'] ?? null,
                'estado' => NomPlanilla::ESTADO_BORRADOR,
                'fecha' => $data['fecha'],
                'usuario_id' => $request->user()->id,
                'created_by' => $request->user()->email,
            ]);

            return $this->motor->procesar($planilla, $request->user());
        });

        return redirect()->route('admin.nomina.planillas.show', $planilla)
            ->with('status', "Planilla {$planilla->numero} calculada. Revisa y contabiliza.");
    }

    public function show(Request $request, NomPlanilla $planilla): View
    {
        abort_unless($request->user()->can('nomina.ver'), 403);
        abort_unless($planilla->compania_id === $this->companiaActivaId($request), 404);

        $planilla->load(['periodo', 'asiento']);

        $movimientos = $planilla->movimientos()
            ->with(['empleado:id,codigo,nombre,apellido', 'concepto:id,codigo,descripcion,tipo,orden_impresion'])
            ->get()
            ->sortBy(fn ($m) => [$m->empleado->codigo, $m->concepto->orden_impresion])
            ->groupBy('empleado_id');

        return view('admin.nomina.planillas.show', compact('planilla', 'movimientos'));
    }

    /** Recalcula (solo borrador/procesada): re-lee novedades y empleados. */
    public function recalcular(Request $request, NomPlanilla $planilla): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        abort_unless($planilla->compania_id === $this->companiaActivaId($request), 404);

        DB::transaction(fn () => $this->motor->procesar($planilla, $request->user()));

        return back()->with('status', 'Planilla recalculada con las novedades vigentes.');
    }

    public function contabilizar(Request $request, NomPlanilla $planilla): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        abort_unless($planilla->compania_id === $this->companiaActivaId($request), 404);

        DB::transaction(fn () => $this->motor->contabilizar($planilla, $request->user()));

        return back()->with('status', "Planilla contabilizada. Asiento {$planilla->refresh()->asiento?->numero} posteado.");
    }

    public function anular(Request $request, NomPlanilla $planilla): RedirectResponse
    {
        abort_unless($request->user()->can('nomina.gestionar'), 403);
        abort_unless($planilla->compania_id === $this->companiaActivaId($request), 404);

        DB::transaction(fn () => $this->motor->anular($planilla, $request->user()));

        return back()->with('status', 'Planilla anulada; el asiento fue reversado.');
    }

    /** Recibo de pago imprimible de un empleado de la corrida. */
    public function recibo(Request $request, NomPlanilla $planilla, int $empleadoId): View
    {
        abort_unless($request->user()->can('nomina.ver'), 403);
        abort_unless($planilla->compania_id === $this->companiaActivaId($request), 404);

        $planilla->load('periodo');

        $movimientos = $planilla->movimientos()
            ->where('empleado_id', $empleadoId)
            ->with(['empleado', 'concepto'])
            ->get()
            ->filter(fn ($m) => $m->concepto->imprime_en_recibo)
            ->sortBy(fn ($m) => $m->concepto->orden_impresion);

        abort_if($movimientos->isEmpty(), 404);

        $empleado = $movimientos->first()->empleado;

        return view('admin.nomina.planillas.recibo', compact('planilla', 'movimientos', 'empleado'));
    }
}
