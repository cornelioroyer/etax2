<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxpDocumento;
use App\Models\CxpDocumentoDetalle;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CxpFacturaController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in(['PENDIENTE', 'PARCIAL', 'PAGADO', 'ANULADO'])],
            'proveedor_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $facturas = CxpDocumento::query()
            ->with('proveedor')
            ->where('compania_id', $companiaId)
            ->where('tipo_documento', CxpDocumento::TIPO_FACTURA)
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['proveedor_id'] ?? null, fn ($q, $proveedor) => $q->where('proveedor_id', $proveedor))
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->when($filtros['q'] ?? null, function ($q, $texto) {
                $busqueda = '%'.mb_strtolower($texto).'%';
                $q->where(function ($q) use ($busqueda) {
                    $q->whereRaw('LOWER(numero) LIKE ?', [$busqueda])
                        ->orWhereHas('proveedor', fn ($c) => $c->whereRaw('LOWER(nombre) LIKE ?', [$busqueda]));
                });
            })
            ->orderByDesc('fecha')
            ->orderByDesc('numero')
            ->paginate(25)
            ->withQueryString();

        $saldoTotal = (float) CxpDocumento::where('compania_id', $companiaId)
            ->where('tipo_documento', CxpDocumento::TIPO_FACTURA)
            ->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)
            ->selectRaw('COALESCE(SUM(saldo), 0) AS saldo')
            ->value('saldo');

        return view('admin.cxp.facturas.index', [
            'facturas' => $facturas,
            'filtros' => $filtros,
            'proveedores' => $this->proveedores($companiaId),
            'saldoTotal' => $saldoTotal,
        ]);
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        return view('admin.cxp.facturas.create', [
            'proveedores' => $this->proveedores($companiaId),
            'cuentas' => CuentaContable::where('compania_id', $companiaId)
                ->where('permite_movimiento', true)
                ->where('activa', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
            'cuentaGastoId' => CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario = $request->user();

        $data = $request->validate([
            'proveedor_id' => [
                'required', 'integer',
                Rule::exists('contact_contactos', 'id')->where('compania_id', $companiaId),
            ],
            'numero' => ['required', 'string', 'max:50'],
            'fecha' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.descripcion' => ['required', 'string', 'max:500'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'lineas.*.precio_unitario' => ['required', 'numeric', 'gte:0', 'max:999999999'],
            'lineas.*.tasa_itbms' => ['required', 'integer', Rule::in(CxcFacturaController::TASAS_ITBMS)],
            'lineas.*.cuenta_id' => [
                'required', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
        ]);

        $duplicada = CxpDocumento::where('compania_id', $companiaId)
            ->where('proveedor_id', $data['proveedor_id'])
            ->where('tipo_documento', CxpDocumento::TIPO_FACTURA)
            ->where('numero', $data['numero'])
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages([
                'numero' => "Ya existe la factura {$data['numero']} de ese proveedor.",
            ]);
        }

        $cuentaCxpId = CuentaDefault::idPara($companiaId, 'CXP');
        $cuentaItbmsId = CuentaDefault::idPara($companiaId, 'ITBMS_CREDITO');

        if (! $cuentaCxpId) {
            throw ValidationException::withMessages([
                'proveedor_id' => 'La compañía no tiene configurada la cuenta default CXP (Cuentas por Pagar). Aplica una plantilla de plan de cuentas o configúrala.',
            ]);
        }

        $lineas = [];
        $subtotal = 0.0;
        $impuesto = 0.0;

        foreach (array_values($data['lineas']) as $i => $linea) {
            $cantidad = round((float) $linea['cantidad'], 4);
            $precio = round((float) $linea['precio_unitario'], 4);
            $base = round($cantidad * $precio, 2);
            $itbms = round($base * ((int) $linea['tasa_itbms']) / 100, 2);

            $subtotal += $base;
            $impuesto += $itbms;

            $lineas[] = [
                'linea' => $i + 1,
                'descripcion' => $linea['descripcion'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'impuesto_monto' => $itbms,
                'total_linea' => round($base + $itbms, 2),
                'cuenta_id' => (int) $linea['cuenta_id'],
            ];
        }

        $subtotal = round($subtotal, 2);
        $impuesto = round($impuesto, 2);
        $total = round($subtotal + $impuesto, 2);

        if ($total <= 0) {
            throw ValidationException::withMessages(['lineas' => 'El total de la factura debe ser mayor que cero.']);
        }

        if ($impuesto > 0 && ! $cuentaItbmsId) {
            throw ValidationException::withMessages([
                'lineas' => 'La compañía no tiene configurada la cuenta default ITBMS_CREDITO; no se puede registrar ITBMS de compras.',
            ]);
        }

        $factura = DB::transaction(function () use ($companiaId, $data, $lineas, $subtotal, $impuesto, $total, $cuentaCxpId, $cuentaItbmsId, $usuario) {
            $factura = CxpDocumento::create([
                'compania_id' => $companiaId,
                'proveedor_id' => $data['proveedor_id'],
                'tipo_documento' => CxpDocumento::TIPO_FACTURA,
                'numero' => $data['numero'],
                'fecha' => $data['fecha'],
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'subtotal' => $subtotal,
                'descuento' => 0,
                'impuesto' => $impuesto,
                'total' => $total,
                'saldo' => $total,
                'estado' => CxpDocumento::ESTADO_PENDIENTE,
                'created_by' => $usuario->email,
            ]);

            foreach ($lineas as $linea) {
                CxpDocumentoDetalle::create($linea + ['documento_id' => $factura->id, 'created_by' => $usuario->email]);
            }

            // Asiento: débito gasto/costo por línea, débito ITBMS crédito fiscal, crédito CXP
            $lineasAsiento = [];

            foreach ($lineas as $linea) {
                $base = round($linea['total_linea'] - $linea['impuesto_monto'], 2);
                $lineasAsiento[] = [
                    'cuenta_id' => $linea['cuenta_id'],
                    'descripcion' => $linea['descripcion'],
                    'debito' => $base,
                    'credito' => 0,
                ];
            }

            if ($impuesto > 0) {
                $lineasAsiento[] = [
                    'cuenta_id' => $cuentaItbmsId,
                    'descripcion' => "ITBMS factura {$factura->numero}",
                    'debito' => $impuesto,
                    'credito' => 0,
                ];
            }

            $lineasAsiento[] = [
                'cuenta_id' => $cuentaCxpId,
                'contacto_id' => (int) $data['proveedor_id'],
                'descripcion' => "Factura {$factura->numero}",
                'debito' => 0,
                'credito' => $total,
            ];

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId,
                $data['fecha'],
                "Factura de compra {$factura->numero} — ".$factura->proveedor->nombre,
                $factura->numero,
                $lineasAsiento,
                'CXP',
                'cxp_documentos',
                $factura->id,
                $usuario,
            );

            $factura->update(['asiento_id' => $asiento->id]);

            return $factura;
        });

        return redirect()->route('admin.cxp.facturas.show', $factura)
            ->with('status', "Factura {$factura->numero} registrada y contabilizada.");
    }

    public function show(Request $request, CxpDocumento $documento): View
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($documento->tipo_documento === CxpDocumento::TIPO_FACTURA, 404);

        $documento->load(['proveedor', 'detalle.cuenta', 'asiento', 'aplicacionesComoDestino.origen']);

        return view('admin.cxp.facturas.show', ['factura' => $documento]);
    }

    public function anular(Request $request, CxpDocumento $documento): RedirectResponse
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($documento->tipo_documento === CxpDocumento::TIPO_FACTURA, 404);

        if ($documento->esAnulado()) {
            return back()->withErrors(['documento' => 'La factura ya está anulada.']);
        }

        if ($documento->aplicacionesComoDestino()->exists()) {
            return back()->withErrors(['documento' => 'La factura tiene pagos aplicados; anula primero los pagos.']);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($documento, $usuario) {
            app(AsientoAutomatico::class)->anular($documento->asiento, $usuario);

            $documento->update([
                'estado' => CxpDocumento::ESTADO_ANULADO,
                'saldo' => 0,
                'updated_by' => $usuario->email,
            ]);
        });

        return redirect()->route('admin.cxp.facturas.show', $documento)
            ->with('status', "Factura {$documento->numero} anulada.");
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
