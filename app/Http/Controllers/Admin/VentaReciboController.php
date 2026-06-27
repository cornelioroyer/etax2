<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CobrosPlantillaExport;
use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\EmparejaContactos;
use App\Http\Controllers\Controller;
use App\Imports\CobrosGenericoImport;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxcAplicacion;
use App\Models\CxcDocumento;
use App\Models\VentaFactura;
use App\Models\VentaRecibo;
use App\Models\VentaReciboDetalle;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class VentaReciboController extends Controller
{
    use ConCompaniaActiva;
    use EmparejaContactos;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'cliente_id' => ['nullable', 'integer'],
            'desde'      => ['nullable', 'date'],
            'hasta'      => ['nullable', 'date'],
            'estado'     => ['nullable', 'string'],
        ]);

        $recibos = VentaRecibo::with('cliente')
            ->where('compania_id', $companiaId)
            ->when($filtros['cliente_id'] ?? null, fn ($q, $v) => $q->where('cliente_id', $v))
            ->when($filtros['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v))
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->orderByDesc('fecha')
            ->orderByDesc('numero')
            ->paginate(25)->withQueryString();

        $clientes = Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        return view('admin.ventas.recibos.index', compact('recibos', 'filtros', 'clientes'));
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);
        $clienteId  = $request->integer('cliente_id') ?: null;

        $facturasPendientes = $clienteId
            ? VentaFactura::where('compania_id', $companiaId)
                ->where('cliente_id', $clienteId)
                ->whereIn('estado', [VentaFactura::ESTADO_EMITIDA, VentaFactura::ESTADO_PARCIAL])
                ->where('saldo', '>', 0)
                ->orderBy('fecha')
                ->get()
            : collect();

        $clientes = Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        $cuentasCobro = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $cuentaBancoId = CuentaDefault::idPara($companiaId, 'BANCO_DEFAULT')
            ?? CuentaDefault::idPara($companiaId, 'CAJA_DEFAULT');

        return view('admin.ventas.recibos.create', compact(
            'clientes', 'clienteId', 'facturasPendientes', 'cuentasCobro', 'cuentaBancoId'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario    = $request->user();

        $data = $request->validate([
            'cliente_id'      => ['required', 'integer'],
            'fecha'           => ['required', 'date'],
            'metodo_pago'     => ['nullable', 'string', 'max:50'],
            'cuenta_cobro_id' => ['required', 'integer', 'exists:cgl_cuentas,id'],
            'referencia'      => ['nullable', 'string', 'max:100'],
            'facturas'        => ['required', 'array', 'min:1'],
            'facturas.*.id'   => ['required', 'integer'],
            'facturas.*.monto' => ['required', 'numeric', 'min:0'],
        ]);

        $aplicar = collect($data['facturas'])
            ->map(fn ($f) => ['factura_id' => (int) $f['id'], 'monto' => round((float) $f['monto'], 2)])
            ->filter(fn ($f) => $f['monto'] > 0)
            ->values();

        if ($aplicar->isEmpty()) {
            throw ValidationException::withMessages(['facturas' => 'Indica el monto a cobrar en al menos una factura.']);
        }

        // Cargar y validar facturas
        $facturas = VentaFactura::where('compania_id', $companiaId)
            ->where('cliente_id', $data['cliente_id'])
            ->whereIn('estado', [VentaFactura::ESTADO_EMITIDA, VentaFactura::ESTADO_PARCIAL])
            ->whereIn('id', $aplicar->pluck('factura_id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($aplicar as $apl) {
            $factura = $facturas->get($apl['factura_id']);
            if (! $factura) {
                throw ValidationException::withMessages(['facturas' => 'Una de las facturas no pertenece al cliente.']);
            }
            if ($apl['monto'] > round((float) $factura->saldo, 2) + 0.004) {
                throw ValidationException::withMessages([
                    'facturas' => "Monto en {$factura->numero} (B/. {$apl['monto']}) excede el saldo (B/. {$factura->saldo}).",
                ]);
            }
        }

        $total = round($aplicar->sum('monto'), 2);

        $cuentaCxcId = CuentaDefault::idPara($companiaId, 'CXC');

        $recibo = DB::transaction(function () use ($companiaId, $data, $aplicar, $facturas, $total, $cuentaCxcId, $usuario) {
            // Crear el VentaRecibo
            $recibo = VentaRecibo::create([
                'compania_id' => $companiaId,
                'cliente_id'  => $data['cliente_id'],
                'numero'      => VentaRecibo::siguienteNumero($companiaId),
                'fecha'       => $data['fecha'],
                'metodo_pago' => $data['metodo_pago'] ?? null,
                'total'       => $total,
                'estado'      => VentaRecibo::ESTADO_APLICADO,
                'created_by'  => $usuario->email,
                'updated_by'  => $usuario->email,
            ]);

            // Crear el CxcDocumento de pago vinculado
            $cobro = CxcDocumento::create([
                'compania_id'    => $companiaId,
                'cliente_id'     => $data['cliente_id'],
                'tipo_documento' => CxcDocumento::TIPO_PAGO,
                'numero'         => CxcDocumento::siguienteNumero($companiaId, CxcDocumento::TIPO_PAGO),
                'referencia'     => $data['referencia'] ?? null,
                'fecha'          => $data['fecha'],
                'subtotal'       => $total,
                'impuesto'       => 0,
                'total'          => $total,
                'saldo'          => 0,
                'estado'         => CxcDocumento::ESTADO_PAGADO,
                'created_by'     => $usuario->email,
            ]);

            $recibo->update(['cxc_documento_id' => $cobro->id]);

            foreach ($aplicar as $apl) {
                $factura = $facturas->get($apl['factura_id']);

                VentaReciboDetalle::create([
                    'recibo_id'       => $recibo->id,
                    'factura_id'      => $factura->id,
                    'cxc_documento_id' => $factura->cxc_documento_id,
                    'monto'           => $apl['monto'],
                    'created_by'      => $usuario->email,
                    'updated_by'      => $usuario->email,
                ]);

                // Aplicar al CxcDocumento de la factura
                if ($factura->cxc_documento_id) {
                    CxcAplicacion::create([
                        'compania_id'         => $companiaId,
                        'cliente_id'          => $data['cliente_id'],
                        'documento_origen_id' => $cobro->id,
                        'documento_destino_id' => $factura->cxc_documento_id,
                        'fecha'               => $data['fecha'],
                        'monto_aplicado'      => $apl['monto'],
                        'created_by'          => $usuario->email,
                    ]);

                    $cxcDoc = $factura->cxcDocumento()->lockForUpdate()->first();
                    if ($cxcDoc) {
                        $nuevoSaldo = round((float) $cxcDoc->saldo - $apl['monto'], 2);
                        $cxcDoc->update([
                            'saldo'      => max(0, $nuevoSaldo),
                            'estado'     => $nuevoSaldo <= 0 ? CxcDocumento::ESTADO_PAGADO : CxcDocumento::ESTADO_PARCIAL,
                            'updated_by' => $usuario->email,
                        ]);
                    }
                }

                // Actualizar VentaFactura
                $nuevoSaldo = round((float) $factura->saldo - $apl['monto'], 2);
                $factura->saldo      = max(0, $nuevoSaldo);
                $factura->estado     = $nuevoSaldo <= 0 ? VentaFactura::ESTADO_PAGADA : VentaFactura::ESTADO_PARCIAL;
                $factura->updated_by = $usuario->email;
                $factura->save();
            }

            // Asiento contable
            $nombreCliente = Contacto::find($data['cliente_id'])?->nombre ?? '';
            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId,
                $data['fecha'],
                "Cobro {$recibo->numero} — {$nombreCliente}",
                $data['referencia'] ?? $recibo->numero,
                [
                    [
                        'cuenta_id'   => (int) $data['cuenta_cobro_id'],
                        'descripcion' => "Cobro {$recibo->numero}",
                        'debito'      => $total,
                        'credito'     => 0,
                    ],
                    [
                        'cuenta_id'   => $cuentaCxcId,
                        'contacto_id' => (int) $data['cliente_id'],
                        'descripcion' => "Cobro {$recibo->numero}",
                        'debito'      => 0,
                        'credito'     => $total,
                    ],
                ],
                'VENTAS',
                'ventas_recibos',
                $recibo->id,
                $usuario,
            );

            $recibo->update(['asiento_id' => $asiento->id]);
            $cobro->update(['asiento_id'  => $asiento->id]);

            return $recibo;
        });

        return redirect()->route('admin.ventas.recibos.show', $recibo)
            ->with('status', "Recibo {$recibo->numero} registrado por B/. " . number_format($total, 2) . '.');
    }

    public function show(Request $request, VentaRecibo $recibo): View
    {
        abort_unless($recibo->compania_id === $this->companiaActivaId($request), 404);

        $recibo->load(['cliente', 'asiento', 'detalle.factura', 'cxcDocumento']);

        return view('admin.ventas.recibos.show', compact('recibo'));
    }

    public function anular(Request $request, VentaRecibo $recibo): RedirectResponse
    {
        abort_unless($recibo->compania_id === $this->companiaActivaId($request), 404);

        if ($recibo->esAnulado()) {
            return back()->withErrors(['recibo' => 'El recibo ya está anulado.']);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($recibo, $usuario) {
            foreach ($recibo->detalle()->with('factura', 'cxcDocumento')->lockForUpdate()->get() as $det) {
                // Restaurar saldo factura
                $factura = $det->factura;
                if ($factura) {
                    $factura->saldo      = round((float) $factura->saldo + (float) $det->monto, 2);
                    $factura->estado     = $factura->saldo > 0
                        ? (round((float) $factura->saldo, 2) < round((float) $factura->total, 2) ? VentaFactura::ESTADO_PARCIAL : VentaFactura::ESTADO_EMITIDA)
                        : VentaFactura::ESTADO_PAGADA;
                    $factura->updated_by = $usuario->email;
                    $factura->save();
                }

                // Restaurar CxcDocumento saldo
                if ($det->cxcDocumento) {
                    $cxcDoc = $det->cxcDocumento;
                    $cxcDoc->saldo      = round((float) $cxcDoc->saldo + (float) $det->monto, 2);
                    $cxcDoc->estado     = CxcDocumento::ESTADO_PARCIAL;
                    $cxcDoc->updated_by = $usuario->email;
                    $cxcDoc->save();
                }
            }

            // Eliminar aplicaciones CxC
            if ($recibo->cxc_documento_id) {
                CxcAplicacion::where('documento_origen_id', $recibo->cxc_documento_id)->delete();
                $recibo->cxcDocumento?->update(['estado' => CxcDocumento::ESTADO_ANULADO, 'updated_by' => $usuario->email]);
            }

            if ($recibo->asiento) {
                app(AsientoAutomatico::class)->anular($recibo->asiento, $usuario);
            }

            $recibo->update(['estado' => VentaRecibo::ESTADO_ANULADO, 'updated_by' => $usuario->email]);
        });

        return redirect()->route('admin.ventas.recibos.show', $recibo)
            ->with('status', "Recibo {$recibo->numero} anulado; saldos restaurados.");
    }

    /**
     * Descarga la plantilla .xlsx para importar cobros de clientes, con un par de
     * cuentas de banco/caja reales de la compañía como ejemplo.
     */
    public function importarPlantilla(Request $request): Response
    {
        abort_unless($request->user()->can('ventas.gestionar'), 403);

        $companiaId = $this->companiaActivaId($request);

        // Cuentas de banco/caja reales (activo disponible que permite movimiento)
        // para la muestra de la plantilla.
        $cuentasBanco = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->where('activa', true)
            ->where('codigo', 'like', '10%') // activo disponible como muestra
            ->orderBy('codigo')
            ->limit(2)
            ->get(['codigo', 'nombre'])
            ->map(fn ($c) => [$c->codigo, $c->nombre])
            ->all();

        return Excel::download(new CobrosPlantillaExport($cuentasBanco), 'plantilla_cobros.xlsx');
    }

    /**
     * Importa cobros de clientes desde un Excel/CSV. Un cobro refiere a una factura
     * de venta existente: NO crea clientes ni facturas; empareja el cliente
     * (RUC→código→nombre) y la factura por número, y aplica el cobro a su saldo.
     * Espejo del importador de pagos de CxP. Cada cobro se registra exactamente
     * como un recibo manual (VentaRecibo + CxcDocumento PAGO + asiento Dr cuenta de
     * cobro / Cr CxC); el movimiento bancario lo refleja BancoSync desde el asiento.
     * Síncrono y con una transacción por cobro.
     */
    public function importar(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('ventas.gestionar'), 403);

        $companiaId = $this->companiaActivaId($request);
        $usuario = $request->user();

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $cuentaCxcId = CuentaDefault::idPara($companiaId, 'CXC');

        if (! $cuentaCxcId) {
            return back()->withErrors(['archivo_cobros' => 'La compañía no tiene configurada la cuenta default CXC (Cuentas por Cobrar).']);
        }

        $import = new CobrosGenericoImport;
        Excel::import($import, $request->file('archivo'));

        if ($import->filas === []) {
            return back()->withErrors(['archivo_cobros' => 'El archivo no tiene filas con datos. La primera fila deben ser los encabezados (cliente, numero, fecha, monto…).']);
        }

        $cuentaBancoDefault = CuentaDefault::idPara($companiaId, 'BANCO_DEFAULT')
            ?? CuentaDefault::idPara($companiaId, 'CAJA_DEFAULT');

        $catalogo = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->get(['id', 'codigo'])
            ->keyBy(fn ($c) => trim((string) $c->codigo));

        // Índice de clientes para emparejar con tolerancia (RUC/código/nombre).
        $indiceClientes = ['ruc' => [], 'codigo' => [], 'nombre' => []];
        foreach (Contacto::where('compania_id', $companiaId)->get(['id', 'codigo', 'nombre', 'identificacion']) as $c) {
            $this->indexarContacto($indiceClientes, $c);
        }

        $errores = [];
        $cobros = []; // clave cliente|fecha|cuenta|referencia => cabecera + aplicaciones

        foreach ($import->filas as $f) {
            $fila = $f['fila'];

            if ($f['cliente'] === '' && $f['ruc'] === '') {
                $errores[] = "Fila {$fila}: falta el cliente (nombre o RUC).";

                continue;
            }
            if ($f['numero'] === '') {
                $errores[] = "Fila {$fila}: falta el número de la factura a cobrar.";

                continue;
            }
            if (! $f['fecha']) {
                $errores[] = "Fila {$fila}: falta la fecha o tiene un formato no reconocido (usa dd/mm/aaaa).";

                continue;
            }
            if ($f['monto'] <= 0) {
                $errores[] = "Fila {$fila}: el monto debe ser mayor que cero.";

                continue;
            }

            $cliente = $this->emparejarCliente($f, $indiceClientes);

            if (! $cliente) {
                $errores[] = "Fila {$fila}: no se encontró el cliente '".($f['cliente'] ?: $f['ruc'])."'. Regístralo primero.";

                continue;
            }

            // Cuenta de cobro (depósito): por código del Excel, o la default de banco/caja.
            $cuentaCobroId = null;
            if ($f['cuenta'] !== '') {
                $cuentaCobroId = $catalogo[$f['cuenta']]->id ?? null;
                if (! $cuentaCobroId) {
                    $errores[] = "Fila {$fila}: la cuenta '{$f['cuenta']}' no existe o no permite movimiento; se usó la cuenta de banco/caja por defecto.";
                }
            }
            $cuentaCobroId ??= $cuentaBancoDefault;

            if (! $cuentaCobroId) {
                $errores[] = "Fila {$fila}: no hay cuenta de cobro (ni en el Excel ni la default BANCO_DEFAULT/CAJA_DEFAULT). Configúralas.";

                continue;
            }

            $clave = $cliente->id.'|'.$f['fecha'].'|'.$cuentaCobroId.'|'.$f['referencia'];
            $cobros[$clave] ??= [
                'cliente'      => $cliente,
                'fecha'        => $f['fecha'],
                'cuenta_cobro' => (int) $cuentaCobroId,
                'referencia'   => $f['referencia'],
                'aplicaciones' => [], // numero_factura => ['monto'=>, 'fila'=>]
            ];
            // Acumula por número de factura (varias filas de la misma factura suman).
            if (isset($cobros[$clave]['aplicaciones'][$f['numero']])) {
                $cobros[$clave]['aplicaciones'][$f['numero']]['monto'] = round($cobros[$clave]['aplicaciones'][$f['numero']]['monto'] + $f['monto'], 2);
            } else {
                $cobros[$clave]['aplicaciones'][$f['numero']] = ['monto' => round($f['monto'], 2), 'fila' => $fila];
            }
        }

        $creados = 0;
        $omitidos = 0;

        foreach ($cobros as $cobro) {
            // Idempotencia: si hay referencia, no recreamos un recibo vigente del mismo
            // cliente con esa misma referencia y fecha (evita doble carga del depósito).
            if ($cobro['referencia'] !== '') {
                $yaExiste = CxcDocumento::where('compania_id', $companiaId)
                    ->where('cliente_id', $cobro['cliente']->id)
                    ->where('tipo_documento', CxcDocumento::TIPO_PAGO)
                    ->where('referencia', $cobro['referencia'])
                    ->whereDate('fecha', $cobro['fecha'])
                    ->where('estado', '!=', CxcDocumento::ESTADO_ANULADO)
                    ->exists();

                if ($yaExiste) {
                    $omitidos++;
                    $errores[] = "Cobro ref. {$cobro['referencia']} de {$cobro['cliente']->nombre}: ya existe; se omitió.";

                    continue;
                }
            }

            try {
                $this->crearCobroImportado($companiaId, $cobro, $cuentaCxcId, $usuario, $errores);
                $creados++;
            } catch (ValidationException $e) {
                $msg = collect($e->errors())->flatten()->first() ?? 'error de validación';
                $ref = $cobro['referencia'] !== '' ? "ref. {$cobro['referencia']}" : 'sin referencia';
                $errores[] = "Cobro {$ref} de {$cobro['cliente']->nombre}: {$msg}";
            } catch (\Throwable $e) {
                $ref = $cobro['referencia'] !== '' ? "ref. {$cobro['referencia']}" : 'sin referencia';
                $errores[] = "Cobro {$ref} de {$cobro['cliente']->nombre}: no se pudo registrar ({$e->getMessage()}).";
            }
        }

        $resumen = "Importación de cobros: {$creados} cobro(s) registrado(s)";
        if ($omitidos > 0) {
            $resumen .= ", {$omitidos} omitido(s) por estar ya registrados";
        }
        $resumen .= '.';

        return redirect()->route('admin.ventas.recibos.index')
            ->with('status', $resumen)
            ->with('import_cobros_errores', array_slice($errores, 0, 50));
    }

    /**
     * Empareja un cliente existente por RUC → código → nombre NORMALIZADO contra el
     * índice. No crea: un cobro refiere a una factura que ya existe. null si no lo
     * encuentra.
     */
    private function emparejarCliente(array $f, array $indice): ?Contacto
    {
        $ruc = $f['ruc'] !== '' ? substr($f['ruc'], 0, 50) : null;

        if ($ruc && isset($indice['ruc'][$ruc])) {
            return $indice['ruc'][$ruc];
        }
        if ($f['cliente'] !== '' && isset($indice['codigo'][$f['cliente']])) {
            return $indice['codigo'][$f['cliente']];
        }

        $norm = $this->normalizarTexto($f['cliente']);

        return $norm !== '' ? ($indice['nombre'][$norm] ?? null) : null;
    }

    /**
     * Registra un cobro importado replicando exactamente el recibo manual
     * (VentaReciboController::store): empareja cada factura por número (con saldo)
     * dentro del cliente, crea el VentaRecibo + CxcDocumento PAGO + detalles +
     * aplicaciones + asiento (Dr cuenta de cobro / Cr CxC) y reduce los saldos de la
     * factura y su CxcDocumento. Todo en una transacción. Las facturas que no se
     * encuentran o no tienen saldo se reportan en $errores y se omiten; si ninguna
     * aplica, no se crea el recibo.
     *
     * @param  array<string, mixed>  $cobro
     * @param  array<int, string>    $errores  (se agregan avisos por factura)
     */
    private function crearCobroImportado(int $companiaId, array $cobro, int $cuentaCxcId, $usuario, array &$errores): void
    {
        $cliente = $cobro['cliente'];

        // Empareja cada factura por número (dentro del cliente) con saldo > 0.
        $facturasPorNumero = VentaFactura::where('compania_id', $companiaId)
            ->where('cliente_id', $cliente->id)
            ->whereIn('estado', [VentaFactura::ESTADO_EMITIDA, VentaFactura::ESTADO_PARCIAL])
            ->where('saldo', '>', 0)
            ->orderBy('fecha')
            ->get()
            ->keyBy(fn ($d) => trim((string) $d->numero));

        $aplicar = []; // [factura => monto]

        foreach ($cobro['aplicaciones'] as $numeroFactura => $apl) {
            $factura = $facturasPorNumero->get(trim((string) $numeroFactura));

            if (! $factura) {
                $errores[] = "Cobro de {$cliente->nombre}: la factura '{$numeroFactura}' no existe, está anulada/pagada o no tiene saldo; se omitió.";

                continue;
            }

            $monto = $apl['monto'];

            if ($monto > round((float) $factura->saldo, 2) + 0.004) {
                $errores[] = "Cobro de {$cliente->nombre}: el monto a {$factura->numero} (B/. ".number_format($monto, 2).') excede su saldo (B/. '.number_format((float) $factura->saldo, 2).'); se ajustó al saldo.';
                $monto = round((float) $factura->saldo, 2);
            }

            if ($monto <= 0) {
                continue;
            }

            $aplicar[] = ['factura' => $factura, 'monto' => $monto];
        }

        if ($aplicar === []) {
            return; // nada que aplicar para este cobro
        }

        $total = round(array_sum(array_column($aplicar, 'monto')), 2);

        DB::transaction(function () use ($companiaId, $cobro, $aplicar, $total, $cuentaCxcId, $usuario) {
            $cliente = $cobro['cliente'];

            $recibo = VentaRecibo::create([
                'compania_id' => $companiaId,
                'cliente_id'  => $cliente->id,
                'numero'      => VentaRecibo::siguienteNumero($companiaId),
                'fecha'       => $cobro['fecha'],
                'metodo_pago' => null,
                'total'       => $total,
                'estado'      => VentaRecibo::ESTADO_APLICADO,
                'created_by'  => $usuario->email,
                'updated_by'  => $usuario->email,
            ]);

            $cobroDoc = CxcDocumento::create([
                'compania_id'    => $companiaId,
                'cliente_id'     => $cliente->id,
                'tipo_documento' => CxcDocumento::TIPO_PAGO,
                'numero'         => CxcDocumento::siguienteNumero($companiaId, CxcDocumento::TIPO_PAGO),
                'referencia'     => $cobro['referencia'] !== '' ? $cobro['referencia'] : null,
                'fecha'          => $cobro['fecha'],
                'subtotal'       => $total,
                'impuesto'       => 0,
                'total'          => $total,
                'saldo'          => 0,
                'estado'         => CxcDocumento::ESTADO_PAGADO,
                'created_by'     => $usuario->email,
            ]);

            $recibo->update(['cxc_documento_id' => $cobroDoc->id]);

            foreach ($aplicar as $a) {
                $factura = VentaFactura::lockForUpdate()->find($a['factura']->id);

                VentaReciboDetalle::create([
                    'recibo_id'        => $recibo->id,
                    'factura_id'       => $factura->id,
                    'cxc_documento_id' => $factura->cxc_documento_id,
                    'monto'            => $a['monto'],
                    'created_by'       => $usuario->email,
                    'updated_by'       => $usuario->email,
                ]);

                // Aplicar al CxcDocumento de la factura.
                if ($factura->cxc_documento_id) {
                    CxcAplicacion::create([
                        'compania_id'          => $companiaId,
                        'cliente_id'           => $cliente->id,
                        'documento_origen_id'  => $cobroDoc->id,
                        'documento_destino_id' => $factura->cxc_documento_id,
                        'fecha'                => $cobro['fecha'],
                        'monto_aplicado'       => $a['monto'],
                        'created_by'           => $usuario->email,
                    ]);

                    $cxcDoc = $factura->cxcDocumento()->lockForUpdate()->first();
                    if ($cxcDoc) {
                        $nuevoSaldo = round((float) $cxcDoc->saldo - $a['monto'], 2);
                        $cxcDoc->update([
                            'saldo'      => max(0, $nuevoSaldo),
                            'estado'     => $nuevoSaldo <= 0 ? CxcDocumento::ESTADO_PAGADO : CxcDocumento::ESTADO_PARCIAL,
                            'updated_by' => $usuario->email,
                        ]);
                    }
                }

                // Actualizar VentaFactura.
                $nuevoSaldo = round((float) $factura->saldo - $a['monto'], 2);
                $factura->saldo      = max(0, $nuevoSaldo);
                $factura->estado     = $nuevoSaldo <= 0 ? VentaFactura::ESTADO_PAGADA : VentaFactura::ESTADO_PARCIAL;
                $factura->updated_by = $usuario->email;
                $factura->save();
            }

            // Asiento: Dr cuenta de cobro (total) / Cr CxC (total, con contacto).
            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId,
                $cobro['fecha'],
                "Cobro {$recibo->numero} — ".$cliente->nombre,
                $cobroDoc->referencia ?? $recibo->numero,
                [
                    [
                        'cuenta_id'   => $cobro['cuenta_cobro'],
                        'descripcion' => "Cobro {$recibo->numero}",
                        'debito'      => $total,
                        'credito'     => 0,
                    ],
                    [
                        'cuenta_id'   => $cuentaCxcId,
                        'contacto_id' => $cliente->id,
                        'descripcion' => "Cobro {$recibo->numero}",
                        'debito'      => 0,
                        'credito'     => $total,
                    ],
                ],
                'VENTAS',
                'ventas_recibos',
                $recibo->id,
                $usuario,
            );

            $recibo->update(['asiento_id' => $asiento->id]);
            $cobroDoc->update(['asiento_id' => $asiento->id]);
        });
    }
}
