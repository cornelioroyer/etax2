<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxpAplicacion;
use App\Models\CxpDocumento;
use App\Models\TaxImpuesto;
use App\Services\AsientoAutomatico;
use App\Services\InventarioVentas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notas de crédito y débito de CxP.
 *
 * - Nota de crédito (NC): el proveedor nos abona; reduce lo que le
 *   debemos. Se aplica a una factura de proveedor reduciendo su saldo.
 *   Contablemente: Dr CXP; Cr cuenta contrapartida (gasto/inventario) +
 *   Cr ITBMS crédito (reversa).
 * - Nota de débito (ND): el proveedor nos cobra de más; aumenta lo que
 *   le debemos. Documento con su propio saldo pagable. Contablemente:
 *   Dr cuenta contrapartida + Dr ITBMS crédito; Cr CXP.
 */
class CxpNotaController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    public const TASAS_ITBMS = TaxImpuesto::PORCENTAJES_ITBMS;

    private const TIPOS = [
        'credito' => CxpDocumento::TIPO_NOTA_CREDITO,
        'debito' => CxpDocumento::TIPO_NOTA_DEBITO,
    ];

    public function index(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'tipo' => ['nullable', Rule::in(['credito', 'debito'])],
            'proveedor_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $consulta = CxpDocumento::query()
            ->with('proveedor')
            ->where('compania_id', $companiaId)
            ->whereIn('tipo_documento', [CxpDocumento::TIPO_NOTA_CREDITO, CxpDocumento::TIPO_NOTA_DEBITO])
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo_documento', self::TIPOS[$tipo]))
            ->when($filtros['proveedor_id'] ?? null, fn ($q, $prov) => $q->where('proveedor_id', $prov))
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('fecha')
            ->orderByDesc('numero');

        if ($request->query('export')) {
            $todas = (clone $consulta)->get();

            if ($export = $this->exportarReporte($request, 'admin.exports.listado', [
                'titulo' => 'Notas de crédito/débito — CxP',
                'compania' => Compania::find($companiaId)?->nombre ?? '',
                'subtitulo' => 'Listado al '.now()->format('d/m/Y').' — '.$todas->count().' notas',
                'encabezados' => [
                    ['titulo' => 'Número'], ['titulo' => 'Tipo'], ['titulo' => 'Fecha'],
                    ['titulo' => 'Proveedor'], ['titulo' => 'Total', 'num' => true],
                    ['titulo' => 'Saldo', 'num' => true], ['titulo' => 'Estado'],
                ],
                'filas' => $todas->map(fn ($n) => [
                    $n->numero,
                    $n->tipo_documento === CxpDocumento::TIPO_NOTA_CREDITO ? 'Crédito' : 'Débito',
                    $n->fecha->format('d/m/Y'), $n->proveedor->nombre ?? '',
                    number_format((float) $n->total, 2), number_format((float) $n->saldo, 2),
                    ucfirst(strtolower($n->estado)),
                ])->all(),
            ], 'notas_cxp_'.now()->format('Y-m-d'))) {
                return $export;
            }
        }

        $notas = $consulta->paginate(25)->withQueryString();

        return view('admin.cxp.notas.index', [
            'notas' => $notas,
            'filtros' => $filtros,
            'proveedores' => $this->proveedores($companiaId),
        ]);
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $tipo = $request->validate(['tipo' => ['required', Rule::in(['credito', 'debito'])]])['tipo'];
        $esCredito = $tipo === 'credito';

        $proveedorId = $request->integer('proveedor_id') ?: null;

        $facturas = ($esCredito && $proveedorId)
            ? CxpDocumento::where('compania_id', $companiaId)
                ->whereIn('tipo_documento', CxpDocumento::tiposPagables())
                ->where('proveedor_id', $proveedorId)
                ->whereIn('estado', [CxpDocumento::ESTADO_PENDIENTE, CxpDocumento::ESTADO_PARCIAL])
                ->where('saldo', '>', 0)
                ->orderBy('fecha')
                ->get()
            : collect();

        return view('admin.cxp.notas.create', [
            'tipo' => $tipo,
            'esCredito' => $esCredito,
            'proveedorId' => $proveedorId,
            'facturas' => $facturas,
            'proveedores' => $this->proveedores($companiaId),
            'cuentas' => CuentaContable::where('compania_id', $companiaId)
                ->where('permite_movimiento', true)
                ->where('activa', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
            'cuentaSugeridaId' => CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario = $request->user();

        $tipo = $request->validate(['tipo' => ['required', Rule::in(['credito', 'debito'])]])['tipo'];
        $esCredito = $tipo === 'credito';

        $data = $request->validate([
            'proveedor_id' => [
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
            'factura_id' => [
                Rule::requiredIf($esCredito), 'nullable', 'integer',
                Rule::exists('cxp_documentos', 'id')->where('compania_id', $companiaId)->where('proveedor_id', $request->integer('proveedor_id')),
            ],
        ]);

        $base = round((float) $data['monto'], 2);
        $itbms = round($base * ((int) $data['tasa_itbms']) / 100, 2);
        $total = round($base + $itbms, 2);

        $cuentaCxpId = CuentaDefault::idPara($companiaId, 'CXP');
        $cuentaItbmsId = CuentaDefault::idPara($companiaId, 'ITBMS_CREDITO');

        if (! $cuentaCxpId) {
            throw ValidationException::withMessages(['proveedor_id' => 'La compañía no tiene configurada la cuenta default CXP.']);
        }

        if ($itbms > 0 && ! $cuentaItbmsId) {
            throw ValidationException::withMessages(['tasa_itbms' => 'La compañía no tiene configurada la cuenta default ITBMS_CREDITO.']);
        }

        $factura = null;

        if ($esCredito) {
            $factura = CxpDocumento::where('compania_id', $companiaId)
                ->where('proveedor_id', $data['proveedor_id'])
                ->whereIn('tipo_documento', CxpDocumento::tiposPagables())
                ->where('id', $data['factura_id'])
                ->lockForUpdate()
                ->first();

            if (! $factura) {
                throw ValidationException::withMessages(['factura_id' => 'La factura seleccionada no pertenece al proveedor.']);
            }

            if ($total > round((float) $factura->saldo, 2) + 0.004) {
                throw ValidationException::withMessages([
                    'monto' => "El total de la nota (B/. ".number_format($total, 2).") excede el saldo de {$factura->numero} (B/. ".number_format((float) $factura->saldo, 2).').',
                ]);
            }
        }

        $nota = DB::transaction(function () use ($companiaId, $data, $tipo, $esCredito, $base, $itbms, $total, $cuentaCxpId, $cuentaItbmsId, $factura, $usuario) {
            $tipoDoc = self::TIPOS[$tipo];

            $nota = CxpDocumento::create([
                'compania_id' => $companiaId,
                'proveedor_id' => $data['proveedor_id'],
                'tipo_documento' => $tipoDoc,
                'numero' => CxpDocumento::siguienteNumeroNota($companiaId, $tipoDoc),
                'fecha' => $data['fecha'],
                'subtotal' => $base,
                'descuento' => 0,
                'impuesto' => $itbms,
                'total' => $total,
                'saldo' => $esCredito ? 0 : $total,
                'estado' => $esCredito ? CxpDocumento::ESTADO_PAGADO : CxpDocumento::ESTADO_PENDIENTE,
                'created_by' => $usuario->email,
            ]);

            if ($esCredito) {
                // Dr CXP (total); Cr contrapartida (base) + Cr ITBMS crédito
                $lineas = [[
                    'cuenta_id' => $cuentaCxpId,
                    'contacto_id' => (int) $data['proveedor_id'],
                    'descripcion' => "Nota de crédito {$nota->numero}",
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

                CxpAplicacion::create([
                    'compania_id' => $companiaId,
                    'proveedor_id' => $data['proveedor_id'],
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

                $descAsiento = "Nota de crédito {$nota->numero} — ".$nota->proveedor->nombre." (factura {$factura->numero})";
            } else {
                // Dr contrapartida (base) + Dr ITBMS crédito; Cr CXP (total)
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
                    'cuenta_id' => $cuentaCxpId,
                    'contacto_id' => (int) $data['proveedor_id'],
                    'descripcion' => "Nota de débito {$nota->numero}",
                    'debito' => 0,
                    'credito' => $total,
                ];

                $descAsiento = "Nota de débito {$nota->numero} — ".$nota->proveedor->nombre;
            }

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId,
                $data['fecha'],
                $descAsiento,
                $nota->numero,
                $lineas,
                'CXP',
                'cxp_documentos',
                $nota->id,
                $usuario,
            );

            $nota->update(['asiento_id' => $asiento->id]);

            return $nota;
        });

        return redirect()->route('admin.cxp.notas.show', $nota)
            ->with('status', ($esCredito ? 'Nota de crédito ' : 'Nota de débito ')."{$nota->numero} registrada y contabilizada.");
    }

    public function show(Request $request, CxpDocumento $documento): View
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless(in_array($documento->tipo_documento, [CxpDocumento::TIPO_NOTA_CREDITO, CxpDocumento::TIPO_NOTA_DEBITO], true), 404);

        $documento->load(['proveedor', 'detalle.cuenta', 'asiento.detalle.cuenta', 'aplicacionesComoOrigen.destino']);

        return view('admin.cxp.notas.show', ['nota' => $documento]);
    }

    /**
     * Contabiliza una nota en borrador (típicamente importada de la DGI),
     * posteando su asiento a partir de las líneas de detalle. La NC queda como
     * crédito disponible del proveedor; la ND queda con saldo pagable.
     */
    public function contabilizar(Request $request, CxpDocumento $documento): RedirectResponse
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless(in_array($documento->tipo_documento, [CxpDocumento::TIPO_NOTA_CREDITO, CxpDocumento::TIPO_NOTA_DEBITO], true), 404);

        if (! $documento->esBorrador()) {
            return back()->withErrors(['documento' => 'La nota ya está contabilizada o anulada.']);
        }

        $usuario = $request->user();
        $documento->load(['proveedor', 'detalle']);

        DB::transaction(function () use ($documento, $usuario) {
            $this->contabilizarNota($documento, $usuario);
        });

        return redirect()->route('admin.cxp.notas.show', $documento)
            ->with('status', "Nota {$documento->numero} contabilizada. Asiento {$documento->fresh()->asiento->numero}.");
    }

    /** Elimina una nota en borrador (no contabilizada). */
    public function destroy(Request $request, CxpDocumento $documento): RedirectResponse
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless(in_array($documento->tipo_documento, [CxpDocumento::TIPO_NOTA_CREDITO, CxpDocumento::TIPO_NOTA_DEBITO], true), 404);

        if (! $documento->esBorrador()) {
            return back()->withErrors(['documento' => 'Solo se pueden eliminar notas en borrador. Una nota contabilizada debe anularse.']);
        }

        $numero = $documento->numero;

        DB::transaction(function () use ($documento) {
            $documento->detalle()->delete();
            $documento->delete();
        });

        return redirect()->route('admin.cxp.notas.index')
            ->with('status', "Borrador {$numero} eliminado.");
    }

    /**
     * Postea el asiento de una nota en borrador a partir de su detalle.
     * Debe llamarse dentro de una transacción.
     */
    private function contabilizarNota(CxpDocumento $nota, $usuario): void
    {
        $companiaId = $nota->compania_id;
        $esCredito = $nota->tipo_documento === CxpDocumento::TIPO_NOTA_CREDITO;
        $impuesto = round((float) $nota->impuesto, 2);
        $total = round((float) $nota->total, 2);

        $cuentaCxpId = CuentaDefault::idPara($companiaId, 'CXP');
        $cuentaItbmsId = CuentaDefault::idPara($companiaId, 'ITBMS_CREDITO');

        if (! $cuentaCxpId) {
            throw ValidationException::withMessages([
                'documento' => 'La compañía no tiene configurada la cuenta default CXP (Cuentas por Pagar).',
            ]);
        }

        if ($impuesto > 0 && ! $cuentaItbmsId) {
            throw ValidationException::withMessages([
                'documento' => 'La compañía no tiene configurada la cuenta default ITBMS_CREDITO.',
            ]);
        }

        $lineasAsiento = [];

        if ($esCredito) {
            // Dr CXP (total); Cr contrapartida por línea (base); Cr ITBMS crédito.
            $lineasAsiento[] = [
                'cuenta_id' => $cuentaCxpId,
                'contacto_id' => $nota->proveedor_id,
                'descripcion' => "Nota de crédito {$nota->numero}",
                'debito' => $total,
                'credito' => 0,
            ];
            foreach ($nota->detalle as $linea) {
                $base = round((float) $linea->total_linea - (float) $linea->impuesto_monto, 2);
                $lineasAsiento[] = [
                    'cuenta_id' => $linea->cuenta_id,
                    'descripcion' => $linea->descripcion,
                    'debito' => 0,
                    'credito' => $base,
                ];
            }
            if ($impuesto > 0) {
                $lineasAsiento[] = [
                    'cuenta_id' => $cuentaItbmsId,
                    'descripcion' => "ITBMS nota {$nota->numero}",
                    'debito' => 0,
                    'credito' => $impuesto,
                ];
            }
            $descAsiento = "Nota de crédito {$nota->numero} — ".$nota->proveedor->nombre;
        } else {
            // Dr contrapartida por línea (base) + Dr ITBMS crédito; Cr CXP (total).
            foreach ($nota->detalle as $linea) {
                $base = round((float) $linea->total_linea - (float) $linea->impuesto_monto, 2);
                $lineasAsiento[] = [
                    'cuenta_id' => $linea->cuenta_id,
                    'descripcion' => $linea->descripcion,
                    'debito' => $base,
                    'credito' => 0,
                ];
            }
            if ($impuesto > 0) {
                $lineasAsiento[] = [
                    'cuenta_id' => $cuentaItbmsId,
                    'descripcion' => "ITBMS nota {$nota->numero}",
                    'debito' => $impuesto,
                    'credito' => 0,
                ];
            }
            $lineasAsiento[] = [
                'cuenta_id' => $cuentaCxpId,
                'contacto_id' => $nota->proveedor_id,
                'descripcion' => "Nota de débito {$nota->numero}",
                'debito' => 0,
                'credito' => $total,
            ];
            $descAsiento = "Nota de débito {$nota->numero} — ".$nota->proveedor->nombre;
        }

        $asiento = app(AsientoAutomatico::class)->postear(
            $companiaId,
            $nota->fecha->format('Y-m-d'),
            $descAsiento,
            $nota->numero,
            $lineasAsiento,
            'CXP',
            'cxp_documentos',
            $nota->id,
            $usuario,
        );

        $nota->update([
            'asiento_id' => $asiento->id,
            'estado' => CxpDocumento::ESTADO_PENDIENTE,
            'saldo' => $total,
            'updated_by' => $usuario->email,
        ]);
    }

    public function anular(Request $request, CxpDocumento $documento): RedirectResponse
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless(in_array($documento->tipo_documento, [CxpDocumento::TIPO_NOTA_CREDITO, CxpDocumento::TIPO_NOTA_DEBITO], true), 404);

        if ($documento->esAnulado()) {
            return back()->withErrors(['documento' => 'La nota ya está anulada.']);
        }

        if ($documento->tipo_documento === CxpDocumento::TIPO_NOTA_DEBITO
            && $documento->aplicacionesComoDestino()->exists()) {
            return back()->withErrors(['documento' => 'La nota de débito tiene pagos aplicados; anúlalos primero.']);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($documento, $usuario) {
            foreach ($documento->aplicacionesComoOrigen()->with('destino')->lockForUpdate()->get() as $aplicacion) {
                $factura = $aplicacion->destino;
                $factura->saldo = round((float) $factura->saldo + (float) $aplicacion->monto_aplicado, 2);
                $factura->estado = $factura->estadoSegunSaldo();
                $factura->updated_by = $usuario->email;
                $factura->save();

                $aplicacion->delete();
            }

            app(AsientoAutomatico::class)->anular($documento->asiento, $usuario);

            // Si la nota provino de una DEVOLUCIÓN de compra, movió inventario hacia
            // afuera (SALIDA enlazada a esta nota). Anularla repone ese stock. Para
            // las NC normales (sin movimiento de inventario) es un no-op.
            app(InventarioVentas::class)->reversarPorDocumento('cxp_documentos', $documento->id, $usuario);

            $documento->update([
                'estado' => CxpDocumento::ESTADO_ANULADO,
                'saldo' => 0,
                'updated_by' => $usuario->email,
            ]);
        });

        return redirect()->route('admin.cxp.notas.show', $documento)
            ->with('status', "Nota {$documento->numero} anulada.");
    }

    private function proveedores(int $companiaId)
    {
        return Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'PROVEEDOR'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);
    }
}
