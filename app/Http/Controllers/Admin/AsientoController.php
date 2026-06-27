<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AsientoPlantillaExport;
use App\Http\Controllers\Controller;
use App\Imports\AsientoSaldosImport;
use App\Models\Asiento;
use App\Models\AsientoDetalle;
use App\Models\CuentaContable;
use App\Models\Diario;
use App\Models\PeriodoContable;
use App\Models\TipoCuenta;
use App\Services\CuentasControlContable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AsientoController extends Controller
{
    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in([Asiento::ESTADO_BORRADOR, Asiento::ESTADO_POSTEADO, Asiento::ESTADO_ANULADO])],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
            'origen' => ['nullable', Rule::in(Asiento::ETIQUETAS_ORIGEN)],
            'orden' => ['nullable', Rule::in(['numero', 'fecha', 'descripcion', 'referencia', 'total_debito', 'total_credito', 'origen_modulo', 'estado'])],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $orden = $filtros['orden'] ?? 'fecha';
        $dir = $filtros['dir'] ?? 'desc';

        $asientos = Asiento::query()
            ->where('compania_id', $companiaId)
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['origen'] ?? null, fn ($q, $origen) => $q->origenEtiqueta($origen))
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->when($filtros['q'] ?? null, function ($q, $texto) {
                $busqueda = '%'.mb_strtolower($texto).'%';
                $q->where(function ($q) use ($busqueda) {
                    $q->whereRaw('LOWER(numero) LIKE ?', [$busqueda])
                        ->orWhereRaw('LOWER(descripcion) LIKE ?', [$busqueda])
                        ->orWhereRaw('LOWER(referencia) LIKE ?', [$busqueda]);
                });
            })
            ->when(
                in_array($orden, ['descripcion', 'referencia'], true),
                // texto opcional: vacíos/NULL siempre al final, orden sin distinguir mayúsculas
                fn ($q) => $q->orderByRaw("LOWER(NULLIF({$orden}, '')) ".($dir === 'desc' ? 'DESC' : 'ASC').' NULLS LAST'),
                fn ($q) => $q->orderBy($orden, $dir)
            )
            ->when($orden !== 'numero', fn ($q) => $q->orderBy('numero', $dir))
            ->paginate(25)
            ->withQueryString();

        return view('admin.asientos.index', compact('asientos', 'filtros', 'orden', 'dir'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);

        return view('admin.asientos.create', $this->datosFormulario($request));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);

        $companiaId = $this->companiaActivaId($request);
        [$data, $lineas] = $this->validated($request, $companiaId);
        $usuario = $request->user();
        $postear = $request->input('accion') === 'postear';

        $asiento = DB::transaction(function () use ($companiaId, $data, $lineas, $usuario, $postear) {
            $asiento = Asiento::create([
                'compania_id' => $companiaId,
                'diario_id' => Diario::general($companiaId, $usuario->email)->id,
                'numero' => Asiento::siguienteNumero($companiaId),
                'fecha' => $data['fecha'],
                'descripcion' => $data['descripcion'] ?? null,
                'referencia' => $data['referencia'] ?? null,
                'estado' => Asiento::ESTADO_BORRADOR,
                'origen_modulo' => 'CGL',
                'total_debito' => collect($lineas)->sum('debito'),
                'total_credito' => collect($lineas)->sum('credito'),
                'usuario_id' => $usuario->id,
                'created_by' => $usuario->email,
            ]);

            $this->guardarLineas($asiento, $lineas, $usuario->email);

            if ($postear) {
                $this->postearAsiento($asiento, $usuario);
            }

            return $asiento;
        });

        return redirect()->route('admin.asientos.show', $asiento)
            ->with('status', $postear ? "Asiento {$asiento->numero} posteado." : "Asiento {$asiento->numero} guardado como borrador.");
    }

    /**
     * Duplica un asiento existente: redirige al formulario de creación
     * prellenado con sus mismas líneas (fecha = hoy) para registrar uno nuevo.
     * No copia número, estado ni asiento_id; nada se postea aquí. Sirve para
     * cualquier asiento (borrador, posteado o anulado) como punto de partida.
     */
    public function copiar(Request $request, Asiento $asiento): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);
        $this->verificarCompania($request, $asiento);

        $asiento->load('detalle');

        return redirect()->route('admin.asientos.create')->withInput([
            'fecha' => now()->format('Y-m-d'),
            'descripcion' => $asiento->descripcion,
            'referencia' => $asiento->referencia,
            'lineas' => $asiento->detalle->map(fn ($l) => [
                'cuenta_id' => $l->cuenta_id,
                'descripcion' => $l->descripcion,
                'debito' => (float) $l->debito,
                'credito' => (float) $l->credito,
            ])->values()->all(),
        ])->with('status', "Copia de {$asiento->numero}: revisa los datos y guarda el nuevo asiento.");
    }

    /**
     * Formulario para importar un asiento (saldos iniciales) desde Excel.
     */
    public function importarForm(Request $request): View
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);

        return view('admin.asientos.importar');
    }

    /**
     * Descarga una plantilla .xlsx con las columnas y cuentas de ejemplo.
     */
    public function plantillaImport(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);

        $companiaId = $this->companiaActivaId($request);

        // Usa hasta 5 cuentas reales de movimiento como ejemplo en la plantilla.
        $ejemplos = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->where('activa', true)
            ->orderBy('codigo')
            ->limit(5)
            ->get(['codigo', 'nombre'])
            ->map(fn ($c) => [$c->codigo, $c->nombre])
            ->all();

        return Excel::download(new AsientoPlantillaExport($ejemplos), 'plantilla_saldos_iniciales.xlsx');
    }

    /**
     * Procesa el Excel: resuelve cada código contra el catálogo, valida y
     * crea el asiento como BORRADOR para revisarlo y postearlo después.
     */
    public function importar(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.crear'), 403);

        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $import = new AsientoSaldosImport;
        Excel::import($import, $request->file('archivo'));

        if ($import->lineas === []) {
            return back()->withErrors(['archivo' => 'El archivo no tiene filas con datos. Verifica que la primera fila sean los encabezados (codigo, descripcion, debito, credito).'])->withInput();
        }

        // Resuelve códigos contra el catálogo de la compañía.
        $codigos = collect($import->lineas)->pluck('codigo')->filter()->unique();
        $cuentas = CuentaContable::where('compania_id', $companiaId)
            ->whereIn('codigo', $codigos)
            ->get()
            ->keyBy('codigo');

        // Catálogo completo (id/codigo/nivel) para inferir el padre por prefijo
        // al crear automáticamente las cuentas que no existan, y mapa de tipos.
        $catalogo = CuentaContable::where('compania_id', $companiaId)
            ->get(['id', 'codigo', 'nivel'])
            ->map(fn ($c) => ['id' => $c->id, 'codigo' => $c->codigo, 'nivel' => $c->nivel])
            ->all();
        $tiposPorCodigo = TipoCuenta::pluck('id', 'codigo')->all();

        $control = $this->cuentasControl($companiaId);
        $errores = [];
        $lineas = [];
        $porCrear = [];   // codigo => datos de la cuenta a crear (deduplicado por código)
        $totalDebito = 0.0;
        $totalCredito = 0.0;

        foreach ($import->lineas as $l) {
            $fila = $l['fila'];

            if ($l['codigo'] === '') {
                $errores[] = "Fila {$fila}: falta el código de cuenta.";

                continue;
            }

            if (($l['debito'] > 0) === ($l['credito'] > 0)) {
                $errores[] = "Fila {$fila}: indica débito o crédito (uno solo, mayor que cero) para la cuenta {$l['codigo']}.";

                continue;
            }

            $cuenta = $cuentas->get($l['codigo']);

            if (! $cuenta) {
                // La cuenta no existe: se crea automáticamente. El tipo se infiere
                // por el primer dígito y la naturaleza por la columna del asiento.
                $tipoId = $this->tipoCuentaPorCodigo($l['codigo'], $tiposPorCodigo);

                if (! $tipoId) {
                    $errores[] = "Fila {$fila}: no se pudo crear la cuenta {$l['codigo']}: el código debe empezar en 1-6 (1=Activo, 2=Pasivo, 3=Patrimonio, 4=Ingreso, 5=Costo, 6=Gasto).";

                    continue;
                }

                $porCrear[$l['codigo']] = [
                    'codigo' => $l['codigo'],
                    'nombre' => $l['descripcion'] !== '' ? $l['descripcion'] : "Cuenta {$l['codigo']}",
                    'tipo_cuenta_id' => $tipoId,
                    'naturaleza' => $l['debito'] > 0 ? 'DEBITO' : 'CREDITO',
                ];

                $totalDebito += $l['debito'];
                $totalCredito += $l['credito'];

                $lineas[] = [
                    'codigo' => $l['codigo'],
                    'cuenta_id' => null,
                    'descripcion' => $l['descripcion'],
                    'debito' => $l['debito'],
                    'credito' => $l['credito'],
                ];

                continue;
            }

            if (! $cuenta->permite_movimiento) {
                $errores[] = "Fila {$fila}: la cuenta {$cuenta->codigo} es de título; no acepta movimientos.";

                continue;
            }

            if (! $cuenta->activa) {
                $errores[] = "Fila {$fila}: la cuenta {$cuenta->codigo} está inactiva.";

                continue;
            }

            if (isset($control[$cuenta->id])) {
                $errores[] = "Fila {$fila}: la cuenta {$cuenta->codigo} es de control ({$control[$cuenta->id]}); su saldo inicial se carga desde el módulo de {$control[$cuenta->id]}, no por asiento.";

                continue;
            }

            $totalDebito += $l['debito'];
            $totalCredito += $l['credito'];

            $lineas[] = [
                'codigo' => $cuenta->codigo,
                'cuenta_id' => $cuenta->id,
                'descripcion' => $l['descripcion'],
                'debito' => $l['debito'],
                'credito' => $l['credito'],
            ];
        }

        if ($errores !== []) {
            return back()->withErrors(['archivo' => $errores])->withInput();
        }

        if (count($lineas) < 2) {
            return back()->withErrors(['archivo' => 'El asiento necesita al menos 2 líneas válidas.'])->withInput();
        }

        if (abs($totalDebito - $totalCredito) > 0.004) {
            return back()->withErrors([
                'archivo' => sprintf('El archivo está descuadrado: débito B/. %.2f ≠ crédito B/. %.2f.', $totalDebito, $totalCredito),
            ])->withInput();
        }

        $usuario = $request->user();

        $asiento = DB::transaction(function () use ($companiaId, $data, $lineas, $porCrear, $catalogo, $totalDebito, $totalCredito, $usuario) {
            // Crea las cuentas faltantes de menor a mayor longitud de código (para
            // que un padre incluido en el mismo archivo exista antes que su hijo) y
            // enlaza al padre por el prefijo más largo del catálogo (nivel 1 si no hay).
            $nuevasPorCodigo = [];
            uasort($porCrear, fn ($a, $b) => strlen($a['codigo']) <=> strlen($b['codigo']));

            foreach ($porCrear as $nueva) {
                $padre = $this->padrePorPrefijo($nueva['codigo'], $catalogo);

                $cuenta = CuentaContable::create([
                    'compania_id' => $companiaId,
                    'codigo' => $nueva['codigo'],
                    'nombre' => $nueva['nombre'],
                    'cuenta_padre_id' => $padre['id'] ?? null,
                    'nivel' => isset($padre['nivel']) ? $padre['nivel'] + 1 : 1,
                    'tipo_cuenta_id' => $nueva['tipo_cuenta_id'],
                    'naturaleza' => $nueva['naturaleza'],
                    'permite_movimiento' => true,
                    'activa' => true,
                    'created_by' => $usuario->email,
                ]);

                $nuevasPorCodigo[$nueva['codigo']] = $cuenta->id;
                // La cuenta recién creada puede ser padre de otra del mismo archivo.
                $catalogo[] = ['id' => $cuenta->id, 'codigo' => $cuenta->codigo, 'nivel' => $cuenta->nivel];
            }

            // Resuelve el cuenta_id de las líneas que apuntaban a cuentas nuevas.
            foreach ($lineas as &$linea) {
                if ($linea['cuenta_id'] === null) {
                    $linea['cuenta_id'] = $nuevasPorCodigo[$linea['codigo']];
                }
            }
            unset($linea);

            $asiento = Asiento::create([
                'compania_id' => $companiaId,
                'diario_id' => Diario::general($companiaId, $usuario->email)->id,
                'numero' => Asiento::siguienteNumero($companiaId),
                'fecha' => $data['fecha'],
                'descripcion' => $data['descripcion'] ?? 'Saldos iniciales (importado)',
                'referencia' => $data['referencia'] ?? null,
                'estado' => Asiento::ESTADO_BORRADOR,
                'origen_modulo' => 'CGL',
                'total_debito' => round($totalDebito, 2),
                'total_credito' => round($totalCredito, 2),
                'usuario_id' => $usuario->id,
                'created_by' => $usuario->email,
            ]);

            $this->guardarLineas($asiento, $lineas, $usuario->email);

            return $asiento;
        });

        $msg = "Asiento {$asiento->numero} importado como borrador con ".count($lineas).' líneas. Revísalo y postéalo.';
        if ($porCrear !== []) {
            $msg .= ' Se crearon '.count($porCrear).' cuenta(s) nueva(s) en el plan: '.implode(', ', array_keys($porCrear)).'. Revisa su tipo y nombre en Plan de cuentas.';
        }

        return redirect()->route('admin.asientos.show', $asiento)->with('status', $msg);
    }

    /**
     * Infiere el tipo de cuenta por el primer dígito del código
     * (1=Activo, 2=Pasivo, 3=Patrimonio, 4=Ingreso, 5=Costo, 6=Gasto).
     */
    private function tipoCuentaPorCodigo(string $codigo, array $tiposPorCodigo): ?int
    {
        $mapa = [
            '1' => 'ACTIVO', '2' => 'PASIVO', '3' => 'PATRIMONIO',
            '4' => 'INGRESO', '5' => 'COSTO', '6' => 'GASTO',
        ];
        $primer = substr($codigo, 0, 1);

        if (! isset($mapa[$primer])) {
            return null;
        }

        return $tiposPorCodigo[$mapa[$primer]] ?? null;
    }

    /**
     * Devuelve la cuenta del catálogo cuyo código es el prefijo propio más largo
     * del código dado (su cuenta padre por jerarquía), o null si no hay ninguna.
     *
     * @param  array<int, array{id:int, codigo:string, nivel:int}>  $catalogo
     * @return array{id:int, codigo:string, nivel:int}|null
     */
    private function padrePorPrefijo(string $codigo, array $catalogo): ?array
    {
        $mejor = null;

        foreach ($catalogo as $c) {
            if ($c['codigo'] !== $codigo
                && strlen($c['codigo']) < strlen($codigo)
                && str_starts_with($codigo, $c['codigo'])
                && ($mejor === null || strlen($c['codigo']) > strlen($mejor['codigo']))) {
                $mejor = $c;
            }
        }

        return $mejor;
    }

    public function show(Request $request, Asiento $asiento): View
    {
        $this->verificarCompania($request, $asiento);

        $asiento->load(['detalle.cuenta', 'detalle.contacto', 'periodo', 'posteadoPor']);

        return view('admin.asientos.show', compact('asiento'));
    }

    public function edit(Request $request, Asiento $asiento): View|RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $this->verificarCompania($request, $asiento);

        // Asiento de módulo (CXC, CXP, VEN…): la edición se hace en el documento
        // de origen, no aquí (editar el asiento descuadraría auxiliar vs mayor).
        // Si sabemos su URL, redirigimos a la fuente; si no, cae al bloqueo
        // explicativo de verificarEditable().
        if ($asiento->esPosteado() && ! $asiento->esManual()) {
            if ($url = $asiento->urlOrigen()) {
                return redirect($url)->with('status',
                    "El asiento {$asiento->numero} proviene de {$asiento->nombreModuloOrigen()}; edítalo en su documento de origen.");
            }
        }

        $this->verificarEditable($asiento);

        $asiento->load('detalle');

        return view('admin.asientos.edit', [
            'asiento' => $asiento,
            'reemitir' => $asiento->esPosteado(),
        ] + $this->datosFormulario($request));
    }

    public function update(Request $request, Asiento $asiento): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $this->verificarCompania($request, $asiento);
        $this->verificarEditable($asiento);

        // Re-emisión: un asiento posteado NO se muta en sitio (rompería la
        // inmutabilidad y la auditoría). Se anula el original y se crea uno
        // nuevo corregido, enlazado al anterior. Solo aplica a manuales.
        if ($asiento->esPosteado()) {
            return $this->reemitir($request, $asiento);
        }

        [$data, $lineas] = $this->validated($request, $asiento->compania_id);
        $usuario = $request->user();
        $postear = $request->input('accion') === 'postear';

        DB::transaction(function () use ($asiento, $data, $lineas, $usuario, $postear) {
            $asiento->update([
                'fecha' => $data['fecha'],
                'descripcion' => $data['descripcion'] ?? null,
                'referencia' => $data['referencia'] ?? null,
                'total_debito' => collect($lineas)->sum('debito'),
                'total_credito' => collect($lineas)->sum('credito'),
                'updated_by' => $usuario->email,
            ]);

            $asiento->detalle()->delete();
            $this->guardarLineas($asiento, $lineas, $usuario->email);

            if ($postear) {
                $this->postearAsiento($asiento, $usuario);
            }
        });

        return redirect()->route('admin.asientos.show', $asiento)
            ->with('status', $postear ? "Asiento {$asiento->numero} posteado." : "Asiento {$asiento->numero} actualizado.");
    }

    /**
     * Re-emite un asiento manual posteado: en una sola transacción anula el
     * original (queda ANULADO, con su reverso en saldos) y crea uno nuevo
     * posteado con las líneas corregidas, enlazado al anterior por origen_id.
     * Nada se borra: el número viejo permanece en el historial.
     */
    private function reemitir(Request $request, Asiento $asiento): RedirectResponse
    {
        [$data, $lineas] = $this->validated($request, $asiento->compania_id);
        $usuario = $request->user();

        $nuevo = DB::transaction(function () use ($asiento, $data, $lineas, $usuario) {
            // 1) Anular el original. El AsientoObserver revierte saldos y, si un
            //    movimiento bancario reflejado está conciliado, lanza y revierte.
            $asiento->update([
                'estado' => Asiento::ESTADO_ANULADO,
                'updated_by' => $usuario->email,
            ]);

            // 2) Crear el sustituto como borrador, enlazado al original.
            $nuevo = Asiento::create([
                'compania_id' => $asiento->compania_id,
                'diario_id' => $asiento->diario_id,
                'numero' => Asiento::siguienteNumero($asiento->compania_id),
                'fecha' => $data['fecha'],
                'descripcion' => $data['descripcion'] ?? null,
                'referencia' => $data['referencia'] ?? null,
                'estado' => Asiento::ESTADO_BORRADOR,
                'origen_modulo' => 'CGL',
                'origen_tabla' => 'cgl_asientos',
                'origen_id' => $asiento->id,
                'total_debito' => collect($lineas)->sum('debito'),
                'total_credito' => collect($lineas)->sum('credito'),
                'usuario_id' => $usuario->id,
                'created_by' => $usuario->email,
            ]);

            $this->guardarLineas($nuevo, $lineas, $usuario->email);
            $this->postearAsiento($nuevo, $usuario);

            return $nuevo;
        });

        return redirect()->route('admin.asientos.show', $nuevo)
            ->with('status', "Re-emitido: {$asiento->numero} anulado y reemplazado por {$nuevo->numero} (posteado).");
    }

    public function destroy(Request $request, Asiento $asiento): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.eliminar'), 403);
        $this->verificarCompania($request, $asiento);
        $this->soloBorrador($asiento, 'eliminar');

        $asiento->detalle()->delete();
        $asiento->delete();

        return redirect()->route('admin.asientos.index')->with('status', "Borrador {$asiento->numero} eliminado.");
    }

    public function postear(Request $request, Asiento $asiento): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $this->verificarCompania($request, $asiento);
        $this->soloBorrador($asiento, 'postear');

        DB::transaction(fn () => $this->postearAsiento($asiento, $request->user()));

        return redirect()->route('admin.asientos.show', $asiento)
            ->with('status', "Asiento {$asiento->numero} posteado.");
    }

    public function anular(Request $request, Asiento $asiento): RedirectResponse
    {
        abort_unless($request->user()->can('contabilidad.editar'), 403);
        $this->verificarCompania($request, $asiento);

        if (! $asiento->esPosteado()) {
            return back()->withErrors(['asiento' => 'Solo se pueden anular asientos posteados; los borradores se eliminan.']);
        }

        // Asiento de módulo (CXC, CXP, VEN…): se anula desde su documento de
        // origen, no aquí (anularlo en el mayor dejaría el submayor descuadrado
        // contra la contabilidad). Si conocemos la fuente, redirigimos a ella;
        // si no, asegurarManual() lo bloquea con un mensaje explicativo.
        if (! $asiento->esManual()) {
            if ($url = $asiento->urlOrigen()) {
                return redirect($url)->with('status',
                    "El asiento {$asiento->numero} proviene de {$asiento->nombreModuloOrigen()}; anúlalo en su documento de origen.");
            }
        }

        $this->asegurarManual($asiento, 'anular');
        $this->asegurarPeriodoAbierto($asiento, 'anular');

        // Opción: tras anular, dejar una copia en BORRADOR (mismas líneas) para
        // corregirla y volver a postear. No se postea aquí; queda manual y editable.
        $copiar = $request->boolean('copiar_borrador');
        $usuario = $request->user();

        // El AsientoObserver retira los movimientos bancarios reflejados; si
        // alguno está conciliado lanza ValidationException y la transacción
        // revierte la anulación.
        $copia = DB::transaction(function () use ($asiento, $usuario, $copiar) {
            $asiento->update([
                'estado' => Asiento::ESTADO_ANULADO,
                'updated_by' => $usuario->email,
            ]);

            if (! $copiar) {
                return null;
            }

            $asiento->load('detalle');

            $copia = Asiento::create([
                'compania_id' => $asiento->compania_id,
                'diario_id' => $asiento->diario_id,
                'numero' => Asiento::siguienteNumero($asiento->compania_id),
                'fecha' => $asiento->fecha,
                'descripcion' => $asiento->descripcion,
                'referencia' => $asiento->referencia,
                'estado' => Asiento::ESTADO_BORRADOR,
                'origen_modulo' => 'CGL',
                'origen_tabla' => 'cgl_asientos',
                'origen_id' => $asiento->id,
                'total_debito' => (float) $asiento->total_debito,
                'total_credito' => (float) $asiento->total_credito,
                'usuario_id' => $usuario->id,
                'created_by' => $usuario->email,
            ]);

            $this->guardarLineas($copia, $asiento->detalle->map(fn ($l) => [
                'cuenta_id' => $l->cuenta_id,
                'descripcion' => $l->descripcion,
                'debito' => (float) $l->debito,
                'credito' => (float) $l->credito,
            ])->all(), $usuario->email);

            return $copia;
        });

        if ($copia) {
            return redirect()->route('admin.asientos.show', $copia)
                ->with('status', "Asiento {$asiento->numero} anulado. Copia {$copia->numero} creada en borrador: revísala y postéala.");
        }

        return redirect()->route('admin.asientos.show', $asiento)
            ->with('status', "Asiento {$asiento->numero} anulado.");
    }

    /**
     * Postea un borrador: valida cuadre y período ABIERTO (el período
     * del mes se crea automáticamente si no existe). En PostgreSQL los
     * triggers de control contable re-validan todo al cambiar el estado.
     */
    private function postearAsiento(Asiento $asiento, $usuario): void
    {
        $debito = round((float) $asiento->detalle()->sum('debito'), 2);
        $credito = round((float) $asiento->detalle()->sum('credito'), 2);

        if (abs($debito - $credito) > 0.004 || $debito <= 0) {
            throw ValidationException::withMessages([
                'lineas' => "Asiento descuadrado: débito B/. {$debito} ≠ crédito B/. {$credito}.",
            ]);
        }

        // Bloqueo DURO: las cuentas de control de los auxiliares (CxC, CxP e
        // Inventario) solo se afectan por sus módulos, que mantienen el libro
        // auxiliar y postean vía AsientoAutomatico (fuera de este flujo). Un
        // asiento manual contra ellas se rechaza siempre.
        $control = $this->cuentasControl($asiento->compania_id);

        if ($control !== []) {
            $tocadas = $asiento->detalle()
                ->whereIn('cuenta_id', array_keys($control))
                ->pluck('cuenta_id')
                ->unique();

            if ($tocadas->isNotEmpty()) {
                $etiquetas = $tocadas->map(fn ($id) => $control[$id])->unique()->implode(', ');

                throw ValidationException::withMessages([
                    'lineas' => "No se puede afectar {$etiquetas} con un asiento manual. "
                        .'Estas cuentas se controlan por su libro auxiliar: registra el movimiento '
                        .'desde el módulo correspondiente ('.$etiquetas.').',
                ]);
            }
        }

        $periodo = PeriodoContable::paraFecha($asiento->compania_id, $asiento->fecha, $usuario->email);

        if (! $periodo->estaAbierto()) {
            throw ValidationException::withMessages([
                'fecha' => "El período {$periodo->anio}-".str_pad((string) $periodo->mes, 2, '0', STR_PAD_LEFT)." está {$periodo->estado}; no se puede postear en esa fecha.",
            ]);
        }

        $asiento->update([
            'estado' => Asiento::ESTADO_POSTEADO,
            'periodo_id' => $periodo->id,
            'total_debito' => $debito,
            'total_credito' => $credito,
            'posteado_por' => $usuario->id,
            'fecha_posteo' => now(),
            'updated_by' => $usuario->email,
        ]);
    }

    /**
     * Cuentas de control de los auxiliares (CxC, CxP, Inventario, Bancos, Caja,
     * Activos Fijos). Un asiento manual no puede afectarlas: se mueven solo por
     * sus módulos. Regla centralizada en CuentasControlContable (compartida con
     * Recurrentes).
     *
     * @return array<int, string>  [cuenta_id => etiqueta]
     */
    private function cuentasControl(int $companiaId): array
    {
        return CuentasControlContable::para($companiaId);
    }

    private function guardarLineas(Asiento $asiento, array $lineas, string $usuario): void
    {
        foreach (array_values($lineas) as $i => $linea) {
            AsientoDetalle::create([
                'asiento_id' => $asiento->id,
                'linea' => $i + 1,
                'cuenta_id' => $linea['cuenta_id'],
                'descripcion' => $linea['descripcion'] ?? null,
                'debito' => $linea['debito'],
                'credito' => $linea['credito'],
                'tasa_cambio' => 1,
                'debito_local' => $linea['debito'],
                'credito_local' => $linea['credito'],
                'created_by' => $usuario,
            ]);
        }
    }

    /**
     * @return array{0: array, 1: array} datos de cabecera y líneas normalizadas
     */
    private function validated(Request $request, int $companiaId): array
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'referencia' => ['nullable', 'string', 'max:100'],
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
                'lineas' => sprintf('Asiento descuadrado: débito B/. %.2f ≠ crédito B/. %.2f.', $totalDebito, $totalCredito),
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
        ];
    }

    private function soloBorrador(Asiento $asiento, string $accion): void
    {
        abort_unless(
            $asiento->esBorrador(),
            422,
            "Solo los borradores se pueden {$accion}; un asiento posteado es inmutable (anúlalo o reviértelo)."
        );
    }

    /**
     * Permite editar borradores y re-emitir asientos manuales posteados en
     * período abierto. Bloquea los asientos de módulo (CXC, CXP, VEN…): esos
     * se corrigen anulando su documento de origen, no desde Contabilidad.
     */
    private function verificarEditable(Asiento $asiento): void
    {
        if ($asiento->esBorrador()) {
            return;
        }

        abort_unless(
            $asiento->esPosteado(),
            422,
            "El asiento {$asiento->numero} está anulado; no se puede editar."
        );

        $this->asegurarManual($asiento, 're-emitir');
        $this->asegurarPeriodoAbierto($asiento, 're-emitir');
    }

    /**
     * Bloquea los asientos de módulo (CXC, CXP, VEN…): se corrigen y anulan
     * desde su documento de origen, no desde Contabilidad. Hacerlo aquí
     * descuadraría el libro auxiliar contra el mayor.
     */
    private function asegurarManual(Asiento $asiento, string $accion): void
    {
        if ($asiento->esManual()) {
            return;
        }

        $modulo = $asiento->nombreModuloOrigen();

        abort(422, $modulo
            ? "El asiento {$asiento->numero} refleja un documento de {$modulo}. "
              .'Corrígelo o anúlalo en ese documento, dentro de su módulo, no desde Contabilidad.'
            : "El asiento {$asiento->numero} no es un asiento manual; "
              ."solo los asientos manuales se pueden {$accion} desde Contabilidad.");
    }

    /**
     * Bloquea cualquier acción que altere un asiento cuyo período esté cerrado
     * (re-emitir, anular). Modificar un período cerrado exige reabrirlo primero.
     */
    private function asegurarPeriodoAbierto(Asiento $asiento, string $accion): void
    {
        $periodo = $asiento->periodo;

        abort_if(
            $periodo && ! $periodo->estaAbierto(),
            422,
            "El período {$periodo->anio}-".str_pad((string) $periodo->mes, 2, '0', STR_PAD_LEFT)." está {$periodo->estado}; no se puede {$accion} el asiento."
        );
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

    private function verificarCompania(Request $request, Asiento $asiento): void
    {
        abort_unless($asiento->compania_id === $this->companiaActivaId($request), 404);
    }
}
