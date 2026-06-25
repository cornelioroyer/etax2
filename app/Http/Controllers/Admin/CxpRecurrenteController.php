<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CxpRecurrente;
use App\Models\CxpRecurrenteDetalle;
use App\Services\GeneradorCxpRecurrentes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CxpRecurrenteController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in([
                CxpRecurrente::ESTADO_ACTIVA,
                CxpRecurrente::ESTADO_PAUSADA,
                CxpRecurrente::ESTADO_FINALIZADA,
            ])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $plantillas = CxpRecurrente::query()
            ->with('proveedor')
            ->where('compania_id', $companiaId)
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['q'] ?? null, function ($q, $texto) {
                $busqueda = '%'.mb_strtolower($texto).'%';
                $q->where(fn ($q) => $q
                    ->whereRaw('LOWER(nombre) LIKE ?', [$busqueda])
                    ->orWhereHas('proveedor', fn ($c) => $c->whereRaw('LOWER(nombre) LIKE ?', [$busqueda])));
            })
            ->withCount('detalle')
            ->orderByRaw("CASE estado WHEN 'ACTIVA' THEN 0 WHEN 'PAUSADA' THEN 1 ELSE 2 END")
            ->orderBy('proxima_fecha')
            ->paginate(25)
            ->withQueryString();

        $pendientes = CxpRecurrente::where('compania_id', $companiaId)
            ->where('estado', CxpRecurrente::ESTADO_ACTIVA)
            ->whereDate('proxima_fecha', '<=', now()->toDateString())
            ->count();

        return view('admin.cxp.recurrentes.index', compact('plantillas', 'filtros', 'pendientes'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('cxp.gestionar'), 403);

        return view('admin.cxp.recurrentes.create', $this->datosFormulario($request));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('cxp.gestionar'), 403);

        $companiaId = $this->companiaActivaId($request);
        [$data, $lineas, $tot] = $this->validated($request, $companiaId);
        $usuario = $request->user();

        $plantilla = DB::transaction(function () use ($companiaId, $data, $lineas, $tot, $usuario) {
            $plantilla = CxpRecurrente::create([
                'compania_id' => $companiaId,
                'proveedor_id' => $data['proveedor_id'],
                'nombre' => $data['nombre'],
                'referencia' => $data['referencia'] ?? null,
                'frecuencia' => $data['frecuencia'],
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_fin' => $data['fecha_fin'] ?? null,
                'dias_credito' => $data['dias_credito'] ?? 0,
                'ocurrencias_max' => $data['ocurrencias_max'] ?? null,
                'ocurrencias_generadas' => 0,
                'proxima_fecha' => $data['fecha_inicio'],
                'estado' => CxpRecurrente::ESTADO_ACTIVA,
                'subtotal' => $tot['subtotal'],
                'impuesto' => $tot['impuesto'],
                'total' => $tot['total'],
                'usuario_id' => $usuario->id,
                'created_by' => $usuario->email,
            ]);

            $this->guardarLineas($plantilla, $lineas, $usuario->email);

            return $plantilla;
        });

        return redirect()->route('admin.cxp.recurrentes.show', $plantilla)
            ->with('status', "Plantilla de factura recurrente «{$plantilla->nombre}» creada.");
    }

    public function show(Request $request, CxpRecurrente $recurrente): View
    {
        $this->verificarCompania($request, $recurrente);

        $recurrente->load(['detalle.cuenta', 'proveedor']);

        $generadas = $recurrente->facturasGeneradas()
            ->orderByDesc('fecha')
            ->limit(50)
            ->get();

        return view('admin.cxp.recurrentes.show', [
            'plantilla' => $recurrente,
            'generadas' => $generadas,
        ]);
    }

    public function edit(Request $request, CxpRecurrente $recurrente): View
    {
        abort_unless($request->user()->can('cxp.gestionar'), 403);
        $this->verificarCompania($request, $recurrente);

        $recurrente->load('detalle');

        return view('admin.cxp.recurrentes.edit', [
            'plantilla' => $recurrente,
        ] + $this->datosFormulario($request));
    }

    public function update(Request $request, CxpRecurrente $recurrente): RedirectResponse
    {
        abort_unless($request->user()->can('cxp.gestionar'), 403);
        $this->verificarCompania($request, $recurrente);

        $companiaId = $recurrente->compania_id;
        [$data, $lineas, $tot] = $this->validated($request, $companiaId);
        $usuario = $request->user();

        DB::transaction(function () use ($recurrente, $data, $lineas, $tot, $usuario) {
            $attrs = [
                'proveedor_id' => $data['proveedor_id'],
                'nombre' => $data['nombre'],
                'referencia' => $data['referencia'] ?? null,
                'frecuencia' => $data['frecuencia'],
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_fin' => $data['fecha_fin'] ?? null,
                'dias_credito' => $data['dias_credito'] ?? 0,
                'ocurrencias_max' => $data['ocurrencias_max'] ?? null,
                'subtotal' => $tot['subtotal'],
                'impuesto' => $tot['impuesto'],
                'total' => $tot['total'],
                'updated_by' => $usuario->email,
            ];

            // Si todavía no ha generado ninguna factura, la próxima fecha sigue la
            // fecha de inicio; si ya generó, no se mueve hacia atrás el calendario.
            if ($recurrente->ocurrencias_generadas === 0) {
                $attrs['proxima_fecha'] = $data['fecha_inicio'];
            }

            $recurrente->update($attrs);

            $recurrente->detalle()->delete();
            $this->guardarLineas($recurrente, $lineas, $usuario->email);
        });

        return redirect()->route('admin.cxp.recurrentes.show', $recurrente)
            ->with('status', "Plantilla «{$recurrente->nombre}» actualizada.");
    }

    public function destroy(Request $request, CxpRecurrente $recurrente): RedirectResponse
    {
        abort_unless($request->user()->can('cxp.gestionar'), 403);
        $this->verificarCompania($request, $recurrente);

        $nombre = $recurrente->nombre;

        // Borrar la plantilla NO toca las facturas ya generadas: son documentos
        // independientes con su propio ciclo de vida.
        DB::transaction(function () use ($recurrente) {
            $recurrente->detalle()->delete();
            $recurrente->delete();
        });

        return redirect()->route('admin.cxp.recurrentes.index')
            ->with('status', "Plantilla «{$nombre}» eliminada. Las facturas ya generadas se conservan.");
    }

    /** Pausa una plantilla activa: deja de generar hasta reactivarla. */
    public function pausar(Request $request, CxpRecurrente $recurrente): RedirectResponse
    {
        abort_unless($request->user()->can('cxp.gestionar'), 403);
        $this->verificarCompania($request, $recurrente);

        if ($recurrente->esActiva()) {
            $recurrente->update([
                'estado' => CxpRecurrente::ESTADO_PAUSADA,
                'updated_by' => $request->user()->email,
            ]);
        }

        return back()->with('status', "Plantilla «{$recurrente->nombre}» pausada.");
    }

    /** Reactiva una plantilla pausada (no aplica a finalizadas). */
    public function reactivar(Request $request, CxpRecurrente $recurrente): RedirectResponse
    {
        abort_unless($request->user()->can('cxp.gestionar'), 403);
        $this->verificarCompania($request, $recurrente);

        if ($recurrente->estaFinalizada()) {
            return back()->withErrors(['estado' => 'La plantilla está finalizada; ajusta la fecha fin o el número de ocurrencias para reactivarla.']);
        }

        $recurrente->update([
            'estado' => CxpRecurrente::ESTADO_ACTIVA,
            'updated_by' => $request->user()->email,
        ]);

        return back()->with('status', "Plantilla «{$recurrente->nombre}» reactivada.");
    }

    /** Genera ahora las facturas pendientes (BORRADOR) de UNA plantilla (hasta hoy). */
    public function generar(Request $request, CxpRecurrente $recurrente, GeneradorCxpRecurrentes $generador): RedirectResponse
    {
        abort_unless($request->user()->can('cxp.gestionar'), 403);
        $this->verificarCompania($request, $recurrente);

        $creadas = $generador->generarPlantilla($recurrente, now(), $request->user()->email);

        $msg = $creadas > 0
            ? "Se generaron {$creadas} factura(s) en BORRADOR. Revísalas en Facturas de Compras y contabilízalas."
            : 'No había vencimientos pendientes para generar.';

        return redirect()->route('admin.cxp.recurrentes.show', $recurrente)->with('status', $msg);
    }

    /** Genera las facturas pendientes (BORRADOR) de TODAS las plantillas de la compañía. */
    public function generarTodos(Request $request, GeneradorCxpRecurrentes $generador): RedirectResponse
    {
        abort_unless($request->user()->can('cxp.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $r = $generador->generarPendientes(now(), $companiaId, $request->user()->email);

        $msg = $r['facturas'] > 0
            ? "Se generaron {$r['facturas']} factura(s) en BORRADOR desde {$r['plantillas']} plantilla(s). Revísalas en Facturas de Compras."
            : 'No había vencimientos pendientes en ninguna plantilla.';

        return redirect()->route('admin.cxp.recurrentes.index')->with('status', $msg);
    }

    private function guardarLineas(CxpRecurrente $plantilla, array $lineas, string $usuario): void
    {
        foreach (array_values($lineas) as $i => $linea) {
            CxpRecurrenteDetalle::create([
                'recurrente_id' => $plantilla->id,
                'linea' => $i + 1,
                'item_id' => $linea['item_id'] ?? null,
                'descripcion' => $linea['descripcion'],
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $linea['precio_unitario'],
                'tasa_itbms' => $linea['tasa_itbms'],
                'cuenta_id' => $linea['cuenta_id'],
                'created_by' => $usuario,
            ]);
        }
    }

    /**
     * Valida cabecera + líneas y calcula los totales de la plantilla.
     *
     * @return array{0: array, 1: array, 2: array{subtotal:float,impuesto:float,total:float}}
     */
    private function validated(Request $request, int $companiaId): array
    {
        $data = $request->validate([
            'proveedor_id' => [
                'required', 'integer',
                Rule::exists('contact_contactos', 'id')->where('compania_id', $companiaId),
            ],
            'nombre' => ['required', 'string', 'max:200'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'frecuencia' => ['required', Rule::in(array_keys(CxpRecurrente::FRECUENCIAS))],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'dias_credito' => ['nullable', 'integer', 'min:0', 'max:365'],
            'ocurrencias_max' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.item_id' => ['nullable', 'integer'],
            'lineas.*.descripcion' => ['required', 'string', 'max:500'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'gte:0', 'max:999999999'],
            'lineas.*.tasa_itbms' => ['required', 'integer', Rule::in(CxcFacturaController::TASAS_ITBMS)],
            'lineas.*.cuenta_id' => [
                'required', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
        ]);

        $cuentas = CuentaContable::whereIn('id', collect($data['lineas'])->pluck('cuenta_id'))->get()->keyBy('id');

        $lineas = [];
        $subtotal = 0.0;
        $impuesto = 0.0;

        foreach (array_values($data['lineas']) as $i => $linea) {
            $n = $i + 1;
            $cuenta = $cuentas[$linea['cuenta_id']];

            if (! $cuenta->permite_movimiento) {
                throw ValidationException::withMessages([
                    "lineas.{$i}" => "Línea {$n}: la cuenta {$cuenta->codigo} es de título; no acepta movimientos.",
                ]);
            }

            if (! $cuenta->activa) {
                throw ValidationException::withMessages([
                    "lineas.{$i}" => "Línea {$n}: la cuenta {$cuenta->codigo} está inactiva.",
                ]);
            }

            $cantidad = round((float) $linea['cantidad'], 4);
            $precio = round((float) $linea['precio_unitario'], 4);
            $base = round($cantidad * $precio, 2);
            $itbms = round($base * ((int) $linea['tasa_itbms']) / 100, 2);

            $subtotal += $base;
            $impuesto += $itbms;

            $lineas[] = [
                'item_id' => ! empty($linea['item_id']) ? (int) $linea['item_id'] : null,
                'descripcion' => $linea['descripcion'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'tasa_itbms' => (int) $linea['tasa_itbms'],
                'cuenta_id' => (int) $linea['cuenta_id'],
            ];
        }

        $subtotal = round($subtotal, 2);
        $impuesto = round($impuesto, 2);
        $total = round($subtotal + $impuesto, 2);

        if ($total <= 0) {
            throw ValidationException::withMessages(['lineas' => 'El total de la plantilla debe ser mayor que cero.']);
        }

        return [$data, $lineas, ['subtotal' => $subtotal, 'impuesto' => $impuesto, 'total' => $total]];
    }

    private function datosFormulario(Request $request): array
    {
        $companiaId = $this->companiaActivaId($request);

        return [
            'proveedores' => Contacto::where('compania_id', $companiaId)
                ->where('activo', true)
                ->whereHas('tipos', fn ($q) => $q->where('codigo', 'PROVEEDOR'))
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'cuenta_gasto_id']),
            'cuentas' => CuentaContable::where('compania_id', $companiaId)
                ->where('permite_movimiento', true)
                ->where('activa', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
            'frecuencias' => CxpRecurrente::FRECUENCIAS,
            'tasasItbms' => CxcFacturaController::TASAS_ITBMS,
        ];
    }

    private function verificarCompania(Request $request, CxpRecurrente $plantilla): void
    {
        abort_unless($plantilla->compania_id === $this->companiaActivaId($request), 404);
    }
}
