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
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CxpPagoController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    public function index(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'proveedor_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $consulta = CxpDocumento::query()
            ->with('proveedor')
            ->where('compania_id', $companiaId)
            ->where('tipo_documento', CxpDocumento::TIPO_PAGO)
            ->when($filtros['proveedor_id'] ?? null, fn ($q, $proveedor) => $q->where('proveedor_id', $proveedor))
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('fecha')
            ->orderByDesc('numero');

        if ($request->query('export')) {
            $todos = (clone $consulta)->get();

            if ($export = $this->exportarReporte($request, 'admin.exports.listado', [
                'titulo' => 'Pagos — CxP',
                'compania' => Compania::find($companiaId)?->nombre ?? '',
                'subtitulo' => 'Listado al '.now()->format('d/m/Y').' — '.$todos->count().' pagos',
                'encabezados' => [
                    ['titulo' => 'Número'], ['titulo' => 'Fecha'], ['titulo' => 'Proveedor'],
                    ['titulo' => 'Total', 'num' => true], ['titulo' => 'Estado'],
                ],
                'filas' => $todos->map(fn ($p) => [
                    $p->numero, $p->fecha->format('d/m/Y'), $p->proveedor->nombre ?? '',
                    number_format((float) $p->total, 2), ucfirst(strtolower($p->estado)),
                ])->all(),
                'totales' => ['TOTAL', '', '', number_format((float) $todos->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)->sum('total'), 2), ''],
            ], 'pagos_cxp_'.now()->format('Y-m-d'))) {
                return $export;
            }
        }

        $pagos = $consulta->paginate(25)->withQueryString();

        $proveedores = Contacto::where('compania_id', $companiaId)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'PROVEEDOR'))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return view('admin.cxp.pagos.index', compact('pagos', 'filtros', 'proveedores'));
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $proveedorId = $request->integer('proveedor_id') ?: null;

        $facturas = $proveedorId
            ? CxpDocumento::where('compania_id', $companiaId)
                ->whereIn('tipo_documento', CxpDocumento::tiposPagables())
                ->where('proveedor_id', $proveedorId)
                ->whereIn('estado', [CxpDocumento::ESTADO_PENDIENTE, CxpDocumento::ESTADO_PARCIAL])
                ->where('saldo', '>', 0)
                ->orderBy('fecha')
                ->get()
            : collect();

        return view('admin.cxp.pagos.create', [
            'proveedores' => Contacto::where('compania_id', $companiaId)
                ->where('activo', true)
                ->whereHas('tipos', fn ($q) => $q->where('codigo', 'PROVEEDOR'))
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
            'proveedorId' => $proveedorId,
            'facturas' => $facturas,
            'cuentasPago' => CuentaContable::where('compania_id', $companiaId)
                ->where('permite_movimiento', true)
                ->where('activa', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
            'cuentaBancoId' => CuentaDefault::idPara($companiaId, 'BANCO_DEFAULT')
                ?? CuentaDefault::idPara($companiaId, 'CAJA_DEFAULT'),
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
            'fecha' => ['required', 'date'],
            'cuenta_pago_id' => [
                'required', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
            'referencia' => ['nullable', 'string', 'max:100'],
            'aplicaciones' => ['required', 'array', 'min:1'],
            'aplicaciones.*.documento_id' => ['required', 'integer'],
            'aplicaciones.*.monto' => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
        ]);

        $cuentaCxpId = CuentaDefault::idPara($companiaId, 'CXP');

        if (! $cuentaCxpId) {
            throw ValidationException::withMessages([
                'proveedor_id' => 'La compañía no tiene configurada la cuenta default CXP (Cuentas por Pagar).',
            ]);
        }

        $aplicar = collect($data['aplicaciones'])
            ->map(fn ($a) => ['documento_id' => (int) $a['documento_id'], 'monto' => round((float) ($a['monto'] ?? 0), 2)])
            ->filter(fn ($a) => $a['monto'] > 0)
            ->values();

        if ($aplicar->isEmpty()) {
            throw ValidationException::withMessages(['aplicaciones' => 'Indica el monto a pagar en al menos una factura.']);
        }

        $facturas = CxpDocumento::where('compania_id', $companiaId)
            ->where('proveedor_id', $data['proveedor_id'])
            ->whereIn('tipo_documento', CxpDocumento::tiposPagables())
            ->whereIn('id', $aplicar->pluck('documento_id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($aplicar as $apl) {
            $factura = $facturas->get($apl['documento_id']);

            if (! $factura) {
                throw ValidationException::withMessages(['aplicaciones' => 'Una de las facturas no pertenece al proveedor seleccionado.']);
            }

            if ($apl['monto'] > round((float) $factura->saldo, 2) + 0.004) {
                throw ValidationException::withMessages([
                    'aplicaciones' => "El monto aplicado a {$factura->numero} (B/. {$apl['monto']}) excede su saldo (B/. {$factura->saldo}).",
                ]);
            }
        }

        $total = round($aplicar->sum('monto'), 2);

        $pago = DB::transaction(function () use ($companiaId, $data, $aplicar, $facturas, $total, $cuentaCxpId, $usuario) {
            $pago = CxpDocumento::create([
                'compania_id' => $companiaId,
                'proveedor_id' => $data['proveedor_id'],
                'tipo_documento' => CxpDocumento::TIPO_PAGO,
                'numero' => CxpDocumento::siguienteNumeroPago($companiaId),
                'fecha' => $data['fecha'],
                'subtotal' => $total,
                'impuesto' => 0,
                'total' => $total,
                'saldo' => 0,
                'estado' => CxpDocumento::ESTADO_PAGADO,
                'created_by' => $usuario->email,
            ]);

            foreach ($aplicar as $apl) {
                $factura = $facturas->get($apl['documento_id']);

                CxpAplicacion::create([
                    'compania_id' => $companiaId,
                    'proveedor_id' => $data['proveedor_id'],
                    'documento_origen_id' => $pago->id,
                    'documento_destino_id' => $factura->id,
                    'fecha' => $data['fecha'],
                    'monto_aplicado' => $apl['monto'],
                    'created_by' => $usuario->email,
                ]);

                $factura->saldo = round((float) $factura->saldo - $apl['monto'], 2);
                $factura->estado = $factura->estadoSegunSaldo();
                $factura->updated_by = $usuario->email;
                $factura->save();
            }

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId,
                $data['fecha'],
                "Pago {$pago->numero} — ".$pago->proveedor->nombre,
                $data['referencia'] ?? $pago->numero,
                [
                    [
                        'cuenta_id' => $cuentaCxpId,
                        'contacto_id' => (int) $data['proveedor_id'],
                        'descripcion' => "Pago {$pago->numero}",
                        'debito' => $total,
                        'credito' => 0,
                    ],
                    [
                        'cuenta_id' => (int) $data['cuenta_pago_id'],
                        'descripcion' => "Pago {$pago->numero}",
                        'debito' => 0,
                        'credito' => $total,
                    ],
                ],
                'CXP',
                'cxp_documentos',
                $pago->id,
                $usuario,
            );

            $pago->update(['asiento_id' => $asiento->id]);

            return $pago;
        });

        return redirect()->route('admin.cxp.pagos.show', $pago)
            ->with('status', "Pago {$pago->numero} registrado por B/. ".number_format($total, 2).'.');
    }

    public function show(Request $request, CxpDocumento $documento): View
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($documento->tipo_documento === CxpDocumento::TIPO_PAGO, 404);

        $documento->load(['proveedor', 'asiento', 'aplicacionesComoOrigen.destino']);

        return view('admin.cxp.pagos.show', ['pago' => $documento]);
    }

    public function anular(Request $request, CxpDocumento $documento): RedirectResponse
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($documento->tipo_documento === CxpDocumento::TIPO_PAGO, 404);

        if ($documento->esAnulado()) {
            return back()->withErrors(['documento' => 'El pago ya está anulado.']);
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

            $documento->update([
                'estado' => CxpDocumento::ESTADO_ANULADO,
                'saldo' => 0,
                'updated_by' => $usuario->email,
            ]);
        });

        return redirect()->route('admin.cxp.pagos.show', $documento)
            ->with('status', "Pago {$documento->numero} anulado; los saldos de las facturas fueron restaurados.");
    }
}
