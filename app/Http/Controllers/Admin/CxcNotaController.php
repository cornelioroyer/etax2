<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxcAplicacion;
use App\Models\CxcDocumento;
use App\Models\TaxImpuesto;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notas de crédito y débito de CxC.
 *
 * - Nota de crédito (NC): reduce la deuda del cliente. Se aplica a una
 *   factura existente reduciendo su saldo. Contablemente: Dr cuenta
 *   contrapartida (devoluciones/descuento) + Dr ITBMS por pagar; Cr CXC.
 * - Nota de débito (ND): aumenta la deuda del cliente. Documento con su
 *   propio saldo cobrable. Contablemente: Dr CXC; Cr cuenta contrapartida
 *   (ingreso) + Cr ITBMS por pagar.
 */
class CxcNotaController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    public const TASAS_ITBMS = TaxImpuesto::PORCENTAJES_ITBMS;

    private const TIPOS = [
        'credito' => CxcDocumento::TIPO_NOTA_CREDITO,
        'debito' => CxcDocumento::TIPO_NOTA_DEBITO,
    ];

    public function index(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'tipo' => ['nullable', Rule::in(['credito', 'debito'])],
            'cliente_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $consulta = CxcDocumento::query()
            ->with('cliente')
            ->where('compania_id', $companiaId)
            ->whereIn('tipo_documento', [CxcDocumento::TIPO_NOTA_CREDITO, CxcDocumento::TIPO_NOTA_DEBITO])
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo_documento', self::TIPOS[$tipo]))
            ->when($filtros['cliente_id'] ?? null, fn ($q, $cliente) => $q->where('cliente_id', $cliente))
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('fecha')
            ->orderByDesc('numero');

        if ($request->query('export')) {
            $todas = (clone $consulta)->get();

            if ($export = $this->exportarReporte($request, 'admin.exports.listado', [
                'titulo' => 'Notas de crédito/débito — CxC',
                'compania' => Compania::find($companiaId)?->nombre ?? '',
                'subtitulo' => 'Listado al '.now()->format('d/m/Y').' — '.$todas->count().' notas',
                'encabezados' => [
                    ['titulo' => 'Número'], ['titulo' => 'Tipo'], ['titulo' => 'Fecha'],
                    ['titulo' => 'Cliente'], ['titulo' => 'Total', 'num' => true],
                    ['titulo' => 'Saldo', 'num' => true], ['titulo' => 'Estado'],
                ],
                'filas' => $todas->map(fn ($n) => [
                    $n->numero,
                    $n->tipo_documento === CxcDocumento::TIPO_NOTA_CREDITO ? 'Crédito' : 'Débito',
                    $n->fecha->format('d/m/Y'), $n->cliente->nombre ?? '',
                    number_format((float) $n->total, 2), number_format((float) $n->saldo, 2),
                    ucfirst(strtolower($n->estado)),
                ])->all(),
            ], 'notas_cxc_'.now()->format('Y-m-d'))) {
                return $export;
            }
        }

        $notas = $consulta->paginate(25)->withQueryString();

        return view('admin.cxc.notas.index', [
            'notas' => $notas,
            'filtros' => $filtros,
            'clientes' => $this->clientes($companiaId),
        ]);
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $tipo = $request->validate(['tipo' => ['required', Rule::in(['credito', 'debito'])]])['tipo'];
        $esCredito = $tipo === 'credito';

        $clienteId = $request->integer('cliente_id') ?: null;

        // Para NC: facturas con saldo a las que aplicar.
        $facturas = ($esCredito && $clienteId)
            ? CxcDocumento::where('compania_id', $companiaId)
                ->whereIn('tipo_documento', CxcDocumento::tiposCobrables())
                ->where('cliente_id', $clienteId)
                ->whereIn('estado', [CxcDocumento::ESTADO_PENDIENTE, CxcDocumento::ESTADO_PARCIAL])
                ->where('saldo', '>', 0)
                ->orderBy('fecha')
                ->get()
            : collect();

        return view('admin.cxc.notas.create', [
            'tipo' => $tipo,
            'esCredito' => $esCredito,
            'clienteId' => $clienteId,
            'facturas' => $facturas,
            'clientes' => $this->clientes($companiaId),
            'cuentas' => CuentaContable::where('compania_id', $companiaId)
                ->where('permite_movimiento', true)
                ->where('activa', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
            'cuentaSugeridaId' => $esCredito
                ? CuentaDefault::idPara($companiaId, 'DESCUENTOS_VENTA')
                : CuentaDefault::idPara($companiaId, 'VENTAS'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario = $request->user();

        $tipo = $request->validate(['tipo' => ['required', Rule::in(['credito', 'debito'])]])['tipo'];
        $esCredito = $tipo === 'credito';

        $data = $request->validate([
            'cliente_id' => [
                'required', 'integer',
                Rule::exists('contact_contactos', 'id')->where('compania_id', $companiaId),
            ],
            'fecha' => ['required', 'date'],
            'concepto' => ['required', 'string', 'max:500'],
            'cuenta_id' => [
                'required', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
            'monto' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'tasa_itbms' => ['required', 'integer', Rule::in(self::TASAS_ITBMS)],
            // Solo para NC: factura a la que se aplica.
            'factura_id' => [
                Rule::requiredIf($esCredito), 'nullable', 'integer',
                Rule::exists('cxc_documentos', 'id')->where('compania_id', $companiaId)->where('cliente_id', $request->integer('cliente_id')),
            ],
        ]);

        $base = round((float) $data['monto'], 2);
        $itbms = round($base * ((int) $data['tasa_itbms']) / 100, 2);
        $total = round($base + $itbms, 2);

        $cuentaCxcId = CuentaDefault::idPara($companiaId, 'CXC');
        $cuentaItbmsId = CuentaDefault::idPara($companiaId, 'ITBMS_POR_PAGAR');

        if (! $cuentaCxcId) {
            throw ValidationException::withMessages(['cliente_id' => 'La compañía no tiene configurada la cuenta default CXC.']);
        }

        if ($itbms > 0 && ! $cuentaItbmsId) {
            throw ValidationException::withMessages(['tasa_itbms' => 'La compañía no tiene configurada la cuenta default ITBMS_POR_PAGAR.']);
        }

        $factura = null;

        if ($esCredito) {
            $factura = CxcDocumento::where('compania_id', $companiaId)
                ->where('cliente_id', $data['cliente_id'])
                ->whereIn('tipo_documento', CxcDocumento::tiposCobrables())
                ->where('id', $data['factura_id'])
                ->lockForUpdate()
                ->first();

            if (! $factura) {
                throw ValidationException::withMessages(['factura_id' => 'La factura seleccionada no pertenece al cliente.']);
            }

            if ($total > round((float) $factura->saldo, 2) + 0.004) {
                throw ValidationException::withMessages([
                    'monto' => "El total de la nota (B/. ".number_format($total, 2).") excede el saldo de {$factura->numero} (B/. ".number_format((float) $factura->saldo, 2).').',
                ]);
            }
        }

        $nota = DB::transaction(function () use ($companiaId, $data, $tipo, $esCredito, $base, $itbms, $total, $cuentaCxcId, $cuentaItbmsId, $factura, $usuario) {
            $tipoDoc = self::TIPOS[$tipo];

            $nota = CxcDocumento::create([
                'compania_id' => $companiaId,
                'cliente_id' => $data['cliente_id'],
                'tipo_documento' => $tipoDoc,
                'numero' => CxcDocumento::siguienteNumero($companiaId, $tipoDoc),
                'fecha' => $data['fecha'],
                'subtotal' => $base,
                'descuento' => 0,
                'impuesto' => $itbms,
                'total' => $total,
                // NC: se aplica a la factura → saldo 0. ND: saldo cobrable.
                'saldo' => $esCredito ? 0 : $total,
                'estado' => $esCredito ? CxcDocumento::ESTADO_PAGADO : CxcDocumento::ESTADO_PENDIENTE,
                'created_by' => $usuario->email,
            ]);

            if ($esCredito) {
                // Dr contrapartida (base) + Dr ITBMS; Cr CXC (total)
                $lineas = [[
                    'cuenta_id' => (int) $data['cuenta_id'],
                    'descripcion' => $data['concepto'],
                    'debito' => $base,
                    'credito' => 0,
                ]];

                if ($itbms > 0) {
                    $lineas[] = [
                        'cuenta_id' => $cuentaItbmsId,
                        'descripcion' => "ITBMS nota {$nota->numero}",
                        'debito' => $itbms,
                        'credito' => 0,
                    ];
                }

                $lineas[] = [
                    'cuenta_id' => $cuentaCxcId,
                    'contacto_id' => (int) $data['cliente_id'],
                    'descripcion' => "Nota de crédito {$nota->numero}",
                    'debito' => 0,
                    'credito' => $total,
                ];

                // Aplicar a la factura: reduce su saldo.
                CxcAplicacion::create([
                    'compania_id' => $companiaId,
                    'cliente_id' => $data['cliente_id'],
                    'documento_origen_id' => $nota->id,
                    'documento_destino_id' => $factura->id,
                    'fecha' => $data['fecha'],
                    'monto_aplicado' => $total,
                    'created_by' => $usuario->email,
                ]);

                $factura->saldo = round((float) $factura->saldo - $total, 2);
                $factura->estado = $factura->estadoSegunSaldo();
                $factura->updated_by = $usuario->email;
                $factura->save();

                $descAsiento = "Nota de crédito {$nota->numero} — ".$nota->cliente->nombre." (factura {$factura->numero})";
            } else {
                // Dr CXC (total); Cr contrapartida (base) + Cr ITBMS
                $lineas = [[
                    'cuenta_id' => $cuentaCxcId,
                    'contacto_id' => (int) $data['cliente_id'],
                    'descripcion' => "Nota de débito {$nota->numero}",
                    'debito' => $total,
                    'credito' => 0,
                ], [
                    'cuenta_id' => (int) $data['cuenta_id'],
                    'descripcion' => $data['concepto'],
                    'debito' => 0,
                    'credito' => $base,
                ]];

                if ($itbms > 0) {
                    $lineas[] = [
                        'cuenta_id' => $cuentaItbmsId,
                        'descripcion' => "ITBMS nota {$nota->numero}",
                        'debito' => 0,
                        'credito' => $itbms,
                    ];
                }

                $descAsiento = "Nota de débito {$nota->numero} — ".$nota->cliente->nombre;
            }

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId,
                $data['fecha'],
                $descAsiento,
                $nota->numero,
                $lineas,
                'CXC',
                'cxc_documentos',
                $nota->id,
                $usuario,
            );

            $nota->update(['asiento_id' => $asiento->id]);

            return $nota;
        });

        return redirect()->route('admin.cxc.notas.show', $nota)
            ->with('status', ($esCredito ? 'Nota de crédito ' : 'Nota de débito ')."{$nota->numero} registrada y contabilizada.");
    }

    public function show(Request $request, CxcDocumento $documento): View
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless(in_array($documento->tipo_documento, [CxcDocumento::TIPO_NOTA_CREDITO, CxcDocumento::TIPO_NOTA_DEBITO], true), 404);

        $documento->load(['cliente', 'asiento.detalle.cuenta', 'aplicacionesComoOrigen.destino']);

        return view('admin.cxc.notas.show', ['nota' => $documento]);
    }

    public function anular(Request $request, CxcDocumento $documento): RedirectResponse
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless(in_array($documento->tipo_documento, [CxcDocumento::TIPO_NOTA_CREDITO, CxcDocumento::TIPO_NOTA_DEBITO], true), 404);

        if ($documento->esAnulado()) {
            return back()->withErrors(['documento' => 'La nota ya está anulada.']);
        }

        if ($documento->tipo_documento === CxcDocumento::TIPO_NOTA_DEBITO
            && $documento->aplicacionesComoDestino()->exists()) {
            return back()->withErrors(['documento' => 'La nota de débito tiene cobros aplicados; anúlalos primero.']);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($documento, $usuario) {
            // NC: devolver el saldo a la factura aplicada.
            foreach ($documento->aplicacionesComoOrigen()->with('destino')->lockForUpdate()->get() as $aplicacion) {
                $factura = $aplicacion->destino;
                $factura->saldo = round((float) $factura->saldo + (float) $aplicacion->monto_aplicado, 2);
                $factura->estado = $factura->estadoSegunSaldo();
                $factura->updated_by = $usuario->email;
                $factura->save();

                $aplicacion->delete();
            }

            app(AsientoAutomatico::class)->anular($documento->asiento, $usuario);

            $documento->update([
                'estado' => CxcDocumento::ESTADO_ANULADO,
                'saldo' => 0,
                'updated_by' => $usuario->email,
            ]);
        });

        return redirect()->route('admin.cxc.notas.show', $documento)
            ->with('status', "Nota {$documento->numero} anulada.");
    }

    /**
     * "Editar" una nota ya contabilizada. La nota postea de inmediato (no tiene
     * borrador), así que corregir = anularla (reversa del asiento y, en NC,
     * restauración del saldo de la factura aplicada, igual que anular) y reabrir
     * el formulario de captura pre-llenado (tipo, cliente, factura, concepto,
     * cuenta, monto e ITBMS), para registrar la corrección como una nota nueva.
     * El original queda ANULADO en el historial.
     */
    public function corregir(Request $request, CxcDocumento $documento): RedirectResponse
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless(in_array($documento->tipo_documento, [CxcDocumento::TIPO_NOTA_CREDITO, CxcDocumento::TIPO_NOTA_DEBITO], true), 404);

        if ($documento->esAnulado()) {
            return back()->withErrors(['documento' => 'La nota ya está anulada.']);
        }

        if ($documento->tipo_documento === CxcDocumento::TIPO_NOTA_DEBITO
            && $documento->aplicacionesComoDestino()->exists()) {
            return back()->withErrors(['documento' => 'La nota de débito tiene cobros aplicados; anúlalos primero, luego corrígela.']);
        }

        $companiaId = $this->companiaActivaId($request);
        $usuario = $request->user();
        $documento->load('asiento.detalle');

        $esCredito = $documento->tipo_documento === CxcDocumento::TIPO_NOTA_CREDITO;
        $tipo = $esCredito ? 'credito' : 'debito';

        // Factura a la que se aplicó (solo NC) y línea de contrapartida del
        // asiento (la que no es CXC ni ITBMS) para recuperar cuenta y concepto.
        $facturaId = $esCredito
            ? $documento->aplicacionesComoOrigen()->value('documento_destino_id')
            : null;

        $cxcId = CuentaDefault::idPara($companiaId, 'CXC');
        $itbmsId = CuentaDefault::idPara($companiaId, 'ITBMS_POR_PAGAR');
        $contra = $documento->asiento?->detalle
            ->first(fn ($d) => $d->cuenta_id !== $cxcId && $d->cuenta_id !== $itbmsId);

        $base = round((float) $documento->subtotal, 2);
        $tasa = $base > 0 ? (int) round(((float) $documento->impuesto) / $base * 100) : 0;
        if (! in_array($tasa, self::TASAS_ITBMS, true)) {
            $tasa = 0;
        }

        DB::transaction(function () use ($documento, $usuario) {
            foreach ($documento->aplicacionesComoOrigen()->with('destino')->lockForUpdate()->get() as $aplicacion) {
                $factura = $aplicacion->destino;
                $factura->saldo = round((float) $factura->saldo + (float) $aplicacion->monto_aplicado, 2);
                $factura->estado = $factura->estadoSegunSaldo();
                $factura->updated_by = $usuario->email;
                $factura->save();

                $aplicacion->delete();
            }

            if ($documento->asiento) {
                app(AsientoAutomatico::class)->anular($documento->asiento, $usuario);
            }

            $documento->update([
                'estado' => CxcDocumento::ESTADO_ANULADO,
                'saldo' => 0,
                'updated_by' => $usuario->email,
            ]);
        });

        return redirect()->route('admin.cxc.notas.create', ['tipo' => $tipo, 'cliente_id' => $documento->cliente_id])
            ->withInput([
                'cliente_id' => $documento->cliente_id,
                'factura_id' => $facturaId,
                'fecha' => $documento->fecha->format('Y-m-d'),
                'concepto' => $contra?->descripcion,
                'cuenta_id' => $contra?->cuenta_id,
                'monto' => number_format($base, 2, '.', ''),
                'tasa_itbms' => $tasa,
            ])
            ->with('status', ($esCredito ? 'Nota de crédito ' : 'Nota de débito ')."{$documento->numero} anulada para corrección. Ajusta los datos y registra de nuevo (se asignará un número nuevo; el original queda anulado en el historial).");
    }

    private function clientes(int $companiaId)
    {
        return Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);
    }
}
