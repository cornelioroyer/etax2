<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asiento;
use App\Models\AsientoRecurrente;
use App\Models\AsientoRecurrenteDetalle;
use App\Models\CuentaContable;
use App\Services\CuentasControlContable;
use App\Services\GeneradorAsientosRecurrentes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AsientoRecurrenteController extends Controller
{
    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in([
                AsientoRecurrente::ESTADO_ACTIVA,
                AsientoRecurrente::ESTADO_PAUSADA,
                AsientoRecurrente::ESTADO_FINALIZADA,
            ])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $plantillas = AsientoRecurrente::query()
            ->where('compania_id', $companiaId)
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['q'] ?? null, function ($q, $texto) {
                $busqueda = '%'.mb_strtolower($texto).'%';
                $q->where(fn ($q) => $q
                    ->whereRaw('LOWER(nombre) LIKE ?', [$busqueda])
                    ->orWhereRaw('LOWER(descripcion) LIKE ?', [$busqueda]));
            })
            ->withCount('detalle')
            ->orderByRaw("CASE estado WHEN 'ACTIVA' THEN 0 WHEN 'PAUSADA' THEN 1 ELSE 2 END")
            ->orderBy('proxima_fecha')
            ->paginate(25)
            ->withQueryString();

        // Cuántos vencimientos hay pendientes hasta hoy (para el aviso del index).
        $pendientes = AsientoRecurrente::where('compania_id', $companiaId)
            ->where('estado', AsientoRecurrente::ESTADO_ACTIVA)
            ->whereDate('proxima_fecha', '<=', now()->toDateString())
            ->count();

        return view('admin.asientos-recurrentes.index', compact('plantillas', 'filtros', 'pendientes'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);

        return view('admin.asientos-recurrentes.create', $this->datosFormulario($request));
    }

    /**
     * Prellena el formulario de nueva plantilla a partir de un asiento existente
     * (sus líneas, descripción y referencia), con periodicidad mensual y primer
     * vencimiento hoy. No crea nada: el usuario ajusta la periodicidad y guarda.
     * Si el asiento toca cuentas de control, el store lo rechazará al guardar.
     */
    public function desdeAsiento(Request $request, Asiento $asiento): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);
        abort_unless($asiento->compania_id === $this->companiaActivaId($request), 404);

        $asiento->load('detalle');

        $nombre = $asiento->descripcion
            ? mb_substr($asiento->descripcion, 0, 200)
            : "Plantilla desde {$asiento->numero}";

        return redirect()->route('admin.asientos-recurrentes.create')->withInput([
            'nombre' => $nombre,
            'descripcion' => $asiento->descripcion,
            'referencia' => $asiento->referencia,
            'frecuencia' => 'MENSUAL',
            'fecha_inicio' => now()->format('Y-m-d'),
            'lineas' => $asiento->detalle->map(fn ($l) => [
                'cuenta_id' => $l->cuenta_id,
                'descripcion' => $l->descripcion,
                'debito' => (float) $l->debito,
                'credito' => (float) $l->credito,
            ])->values()->all(),
        ])->with('status', "Plantilla recurrente a partir del asiento {$asiento->numero}: ajusta la periodicidad y guarda.");
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);

        $companiaId = $this->companiaActivaId($request);
        [$data, $lineas] = $this->validated($request, $companiaId);
        $usuario = $request->user();

        $plantilla = DB::transaction(function () use ($companiaId, $data, $lineas, $usuario) {
            $plantilla = AsientoRecurrente::create([
                'compania_id' => $companiaId,
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'referencia' => $data['referencia'] ?? null,
                'frecuencia' => $data['frecuencia'],
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_fin' => $data['fecha_fin'] ?? null,
                'ocurrencias_max' => $data['ocurrencias_max'] ?? null,
                'ocurrencias_generadas' => 0,
                'proxima_fecha' => $data['fecha_inicio'],
                'estado' => AsientoRecurrente::ESTADO_ACTIVA,
                'total_debito' => collect($lineas)->sum('debito'),
                'total_credito' => collect($lineas)->sum('credito'),
                'usuario_id' => $usuario->id,
                'created_by' => $usuario->email,
            ]);

            $this->guardarLineas($plantilla, $lineas, $usuario->email);

            return $plantilla;
        });

        return redirect()->route('admin.asientos-recurrentes.show', $plantilla)
            ->with('status', "Plantilla recurrente «{$plantilla->nombre}» creada.");
    }

    public function show(Request $request, AsientoRecurrente $asientos_recurrente): View
    {
        $this->verificarCompania($request, $asientos_recurrente);

        $asientos_recurrente->load(['detalle.cuenta']);

        $generados = $asientos_recurrente->asientosGenerados()
            ->orderByDesc('fecha')
            ->limit(50)
            ->get();

        return view('admin.asientos-recurrentes.show', [
            'plantilla' => $asientos_recurrente,
            'generados' => $generados,
        ]);
    }

    public function edit(Request $request, AsientoRecurrente $asientos_recurrente): View
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $this->verificarCompania($request, $asientos_recurrente);

        $asientos_recurrente->load('detalle');

        return view('admin.asientos-recurrentes.edit', [
            'plantilla' => $asientos_recurrente,
        ] + $this->datosFormulario($request));
    }

    public function update(Request $request, AsientoRecurrente $asientos_recurrente): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $this->verificarCompania($request, $asientos_recurrente);

        $companiaId = $asientos_recurrente->compania_id;
        [$data, $lineas] = $this->validated($request, $companiaId);
        $usuario = $request->user();

        DB::transaction(function () use ($asientos_recurrente, $data, $lineas, $usuario) {
            $attrs = [
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'referencia' => $data['referencia'] ?? null,
                'frecuencia' => $data['frecuencia'],
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_fin' => $data['fecha_fin'] ?? null,
                'ocurrencias_max' => $data['ocurrencias_max'] ?? null,
                'total_debito' => collect($lineas)->sum('debito'),
                'total_credito' => collect($lineas)->sum('credito'),
                'updated_by' => $usuario->email,
            ];

            // Si todavía no ha generado ningún asiento, la próxima fecha sigue la
            // fecha de inicio; si ya generó, no se mueve hacia atrás el calendario.
            if ($asientos_recurrente->ocurrencias_generadas === 0) {
                $attrs['proxima_fecha'] = $data['fecha_inicio'];
            }

            $asientos_recurrente->update($attrs);

            $asientos_recurrente->detalle()->delete();
            $this->guardarLineas($asientos_recurrente, $lineas, $usuario->email);
        });

        return redirect()->route('admin.asientos-recurrentes.show', $asientos_recurrente)
            ->with('status', "Plantilla «{$asientos_recurrente->nombre}» actualizada.");
    }

    public function destroy(Request $request, AsientoRecurrente $asientos_recurrente): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.eliminar'), 403);
        $this->verificarCompania($request, $asientos_recurrente);

        $nombre = $asientos_recurrente->nombre;

        // Borrar la plantilla NO toca los asientos ya generados: son asientos
        // independientes con su propio ciclo de vida.
        DB::transaction(function () use ($asientos_recurrente) {
            $asientos_recurrente->detalle()->delete();
            $asientos_recurrente->delete();
        });

        return redirect()->route('admin.asientos-recurrentes.index')
            ->with('status', "Plantilla «{$nombre}» eliminada. Los asientos ya generados se conservan.");
    }

    /** Pausa una plantilla activa: deja de generar hasta reactivarla. */
    public function pausar(Request $request, AsientoRecurrente $asientos_recurrente): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $this->verificarCompania($request, $asientos_recurrente);

        if ($asientos_recurrente->esActiva()) {
            $asientos_recurrente->update([
                'estado' => AsientoRecurrente::ESTADO_PAUSADA,
                'updated_by' => $request->user()->email,
            ]);
        }

        return back()->with('status', "Plantilla «{$asientos_recurrente->nombre}» pausada.");
    }

    /** Reactiva una plantilla pausada (no aplica a finalizadas). */
    public function reactivar(Request $request, AsientoRecurrente $asientos_recurrente): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $this->verificarCompania($request, $asientos_recurrente);

        if ($asientos_recurrente->estaFinalizada()) {
            return back()->withErrors(['estado' => 'La plantilla está finalizada; ajusta la fecha fin o el número de ocurrencias para reactivarla.']);
        }

        $asientos_recurrente->update([
            'estado' => AsientoRecurrente::ESTADO_ACTIVA,
            'updated_by' => $request->user()->email,
        ]);

        return back()->with('status', "Plantilla «{$asientos_recurrente->nombre}» reactivada.");
    }

    /** Genera ahora los vencimientos pendientes de UNA plantilla (hasta hoy). */
    public function generar(Request $request, AsientoRecurrente $asientos_recurrente, GeneradorAsientosRecurrentes $generador): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);
        $this->verificarCompania($request, $asientos_recurrente);

        $creados = $generador->generarPlantilla($asientos_recurrente, now(), $request->user()->email);

        $msg = $creados > 0
            ? "Se generaron {$creados} asiento(s) en BORRADOR. Revísalos y postéalos."
            : 'No había vencimientos pendientes para generar.';

        return redirect()->route('admin.asientos-recurrentes.show', $asientos_recurrente)->with('status', $msg);
    }

    /** Genera los vencimientos pendientes de TODAS las plantillas de la compañía. */
    public function generarTodos(Request $request, GeneradorAsientosRecurrentes $generador): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);
        $companiaId = $this->companiaActivaId($request);

        $r = $generador->generarPendientes(now(), $companiaId, $request->user()->email);

        $msg = $r['asientos'] > 0
            ? "Se generaron {$r['asientos']} asiento(s) en BORRADOR desde {$r['plantillas']} plantilla(s). Revísalos en Asientos."
            : 'No había vencimientos pendientes en ninguna plantilla.';

        return redirect()->route('admin.asientos-recurrentes.index')->with('status', $msg);
    }

    private function guardarLineas(AsientoRecurrente $plantilla, array $lineas, string $usuario): void
    {
        foreach (array_values($lineas) as $i => $linea) {
            AsientoRecurrenteDetalle::create([
                'recurrente_id' => $plantilla->id,
                'linea' => $i + 1,
                'cuenta_id' => $linea['cuenta_id'],
                'descripcion' => $linea['descripcion'] ?? null,
                'debito' => $linea['debito'],
                'credito' => $linea['credito'],
                'created_by' => $usuario,
            ]);
        }
    }

    /**
     * Valida cabecera + líneas de la plantilla. Aplica las mismas reglas que un
     * asiento manual: una sola columna por línea, cuadre débito=crédito y bloqueo
     * de cuentas de control (CxC/CxP/Inventario/Bancos/Caja/Activos Fijos), que
     * solo se mueven por sus módulos.
     *
     * @return array{0: array, 1: array}
     */
    private function validated(Request $request, int $companiaId): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'frecuencia' => ['required', Rule::in(array_keys(AsientoRecurrente::FRECUENCIAS))],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'ocurrencias_max' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'lineas' => ['required', 'array', 'min:2'],
            'lineas.*.cuenta_id' => [
                'required', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
            'lineas.*.descripcion' => ['nullable', 'string', 'max:300'],
            'lineas.*.debito' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'lineas.*.credito' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);

        $cuentas = CuentaContable::whereIn('id', collect($data['lineas'])->pluck('cuenta_id'))->get()->keyBy('id');
        $control = CuentasControlContable::para($companiaId);

        $lineas = [];
        $totalDebito = 0.0;
        $totalCredito = 0.0;

        foreach (array_values($data['lineas']) as $i => $linea) {
            $n = $i + 1;
            $debito = round((float) ($linea['debito'] ?? 0), 2);
            $credito = round((float) ($linea['credito'] ?? 0), 2);

            if (($debito > 0) === ($credito > 0)) {
                throw ValidationException::withMessages([
                    "lineas.{$i}" => "Línea {$n}: indica débito o crédito (uno solo, mayor que cero).",
                ]);
            }

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

            if (isset($control[$cuenta->id])) {
                throw ValidationException::withMessages([
                    "lineas.{$i}" => "Línea {$n}: la cuenta {$cuenta->codigo} es de control ({$control[$cuenta->id]}); "
                        .'esa cuenta solo se mueve desde su módulo, no por un asiento recurrente.',
                ]);
            }

            $totalDebito += $debito;
            $totalCredito += $credito;

            $lineas[] = [
                'cuenta_id' => (int) $linea['cuenta_id'],
                'descripcion' => $linea['descripcion'] ?? null,
                'debito' => $debito,
                'credito' => $credito,
            ];
        }

        if (abs($totalDebito - $totalCredito) > 0.004) {
            throw ValidationException::withMessages([
                'lineas' => sprintf('La plantilla debe cuadrar: débito B/. %.2f ≠ crédito B/. %.2f.', $totalDebito, $totalCredito),
            ]);
        }

        return [$data, $lineas];
    }

    private function datosFormulario(Request $request): array
    {
        $companiaId = $this->companiaActivaId($request);

        return [
            'cuentas' => CuentaContable::where('compania_id', $companiaId)
                ->where('permite_movimiento', true)
                ->where('activa', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
            'frecuencias' => AsientoRecurrente::FRECUENCIAS,
        ];
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

    private function verificarCompania(Request $request, AsientoRecurrente $plantilla): void
    {
        abort_unless($plantilla->compania_id === $this->companiaActivaId($request), 404);
    }
}
