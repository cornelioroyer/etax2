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

/**
 * Anticipos a proveedores (pagos por adelantado, antes de la factura).
 *
 * - Registrar: el dinero sale del banco hacia un activo "Anticipos a
 *   proveedores". Dr ANTICIPO_PROVEEDOR; Cr Banco/Caja. El anticipo queda con
 *   un saldo DISPONIBLE (a favor) para aplicar a facturas futuras.
 * - Aplicar: cuando llega la factura, se consume el anticipo contra la deuda.
 *   Dr CXP; Cr ANTICIPO_PROVEEDOR. Reduce el saldo de la factura y el
 *   disponible del anticipo.
 * - Anular: revierte las aplicaciones (restaura saldos) y el asiento de origen.
 *
 * Reusa cxp_documentos (tipo ANTICIPO, signo -1) y cxp_aplicaciones (el
 * anticipo es el documento_origen, como un pago/nota de crédito).
 */
class CxpAnticipoController extends Controller
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
            ->where('tipo_documento', CxpDocumento::TIPO_ANTICIPO)
            ->when($filtros['proveedor_id'] ?? null, fn ($q, $p) => $q->where('proveedor_id', $p))
            ->when($filtros['desde'] ?? null, fn ($q, $d) => $q->whereDate('fecha', '>=', $d))
            ->when($filtros['hasta'] ?? null, fn ($q, $h) => $q->whereDate('fecha', '<=', $h))
            ->orderByDesc('fecha')
            ->orderByDesc('numero');

        if ($request->query('export')) {
            $todos = (clone $consulta)->get();

            if ($export = $this->exportarReporte($request, 'admin.exports.listado', [
                'titulo' => 'Anticipos a proveedores',
                'compania' => Compania::find($companiaId)?->nombre ?? '',
                'subtitulo' => 'Listado al '.now()->format('d/m/Y').' — '.$todos->count().' anticipos',
                'encabezados' => [
                    ['titulo' => 'Número'], ['titulo' => 'Fecha'], ['titulo' => 'Proveedor'],
                    ['titulo' => 'Monto', 'num' => true], ['titulo' => 'Disponible', 'num' => true],
                    ['titulo' => 'Estado'],
                ],
                'filas' => $todos->map(fn ($a) => [
                    $a->numero, $a->fecha->format('d/m/Y'), $a->proveedor->nombre ?? '',
                    number_format((float) $a->total, 2), number_format((float) $a->saldo, 2),
                    ucfirst(strtolower($a->estado)),
                ])->all(),
                'totales' => ['TOTAL', '', '',
                    number_format((float) $todos->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)->sum('total'), 2),
                    number_format((float) $todos->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)->sum('saldo'), 2), ''],
            ], 'anticipos_cxp_'.now()->format('Y-m-d'))) {
                return $export;
            }
        }

        $anticipos = $consulta->paginate(25)->withQueryString();

        return view('admin.cxp.anticipos.index', [
            'anticipos' => $anticipos,
            'filtros' => $filtros,
            'proveedores' => $this->proveedores($companiaId),
        ]);
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        return view('admin.cxp.anticipos.create', [
            'proveedores' => $this->proveedores($companiaId),
            'proveedorId' => $request->integer('proveedor_id') ?: null,
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
            'monto' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'concepto' => ['nullable', 'string', 'max:500'],
        ]);

        $cuentaAnticipoId = CuentaDefault::idPara($companiaId, 'ANTICIPO_PROVEEDOR');

        if (! $cuentaAnticipoId) {
            throw ValidationException::withMessages([
                'proveedor_id' => 'La compañía no tiene configurada la cuenta default ANTICIPO_PROVEEDOR (Anticipos a proveedores).',
            ]);
        }

        $monto = round((float) $data['monto'], 2);

        $anticipo = DB::transaction(function () use ($companiaId, $data, $monto, $cuentaAnticipoId, $usuario) {
            $anticipo = CxpDocumento::create([
                'compania_id' => $companiaId,
                'proveedor_id' => $data['proveedor_id'],
                'tipo_documento' => CxpDocumento::TIPO_ANTICIPO,
                'numero' => CxpDocumento::siguienteNumeroTipo($companiaId, CxpDocumento::TIPO_ANTICIPO),
                'fecha' => $data['fecha'],
                'subtotal' => $monto,
                'impuesto' => 0,
                'total' => $monto,
                'saldo' => $monto, // disponible para aplicar
                'estado' => CxpDocumento::ESTADO_PENDIENTE,
                'created_by' => $usuario->email,
            ]);

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId,
                $data['fecha'],
                'Anticipo a proveedor '.$anticipo->numero.' — '.$anticipo->proveedor->nombre,
                $data['referencia'] ?? $anticipo->numero,
                [
                    [
                        'cuenta_id' => $cuentaAnticipoId,
                        'contacto_id' => (int) $data['proveedor_id'],
                        'descripcion' => $data['concepto'] ?? "Anticipo {$anticipo->numero}",
                        'debito' => $monto,
                        'credito' => 0,
                    ],
                    [
                        'cuenta_id' => (int) $data['cuenta_pago_id'],
                        'descripcion' => "Anticipo {$anticipo->numero}",
                        'debito' => 0,
                        'credito' => $monto,
                    ],
                ],
                'CXP',
                'cxp_documentos',
                $anticipo->id,
                $usuario,
            );

            $anticipo->update(['asiento_id' => $asiento->id]);

            return $anticipo;
        });

        return redirect()->route('admin.cxp.anticipos.show', $anticipo)
            ->with('status', "Anticipo {$anticipo->numero} registrado por B/. ".number_format($monto, 2).'.');
    }

    public function show(Request $request, CxpDocumento $documento): View
    {
        $this->autorizar($request, $documento);

        $documento->load(['proveedor', 'asiento', 'aplicacionesComoOrigen.destino']);

        $companiaId = $documento->compania_id;

        // Facturas con saldo del mismo proveedor, para aplicar el disponible.
        $facturas = ($documento->estado !== CxpDocumento::ESTADO_ANULADO && (float) $documento->saldo > 0)
            ? CxpDocumento::where('compania_id', $companiaId)
                ->where('proveedor_id', $documento->proveedor_id)
                ->whereIn('tipo_documento', CxpDocumento::tiposPagables())
                ->whereIn('estado', [CxpDocumento::ESTADO_PENDIENTE, CxpDocumento::ESTADO_PARCIAL])
                ->where('saldo', '>', 0)
                ->orderBy('fecha')
                ->get()
            : collect();

        return view('admin.cxp.anticipos.show', [
            'anticipo' => $documento,
            'facturas' => $facturas,
        ]);
    }

    /** Aplica el disponible del anticipo a una o varias facturas del proveedor. */
    public function aplicar(Request $request, CxpDocumento $documento): RedirectResponse
    {
        $this->autorizar($request, $documento);

        $companiaId = $documento->compania_id;
        $usuario = $request->user();

        if ($documento->esAnulado()) {
            return back()->withErrors(['documento' => 'El anticipo está anulado.']);
        }

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'aplicaciones' => ['required', 'array', 'min:1'],
            'aplicaciones.*.documento_id' => ['required', 'integer'],
            'aplicaciones.*.monto' => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
        ]);

        $cuentaCxpId = CuentaDefault::idPara($companiaId, 'CXP');
        $cuentaAnticipoId = CuentaDefault::idPara($companiaId, 'ANTICIPO_PROVEEDOR');

        if (! $cuentaCxpId || ! $cuentaAnticipoId) {
            throw ValidationException::withMessages([
                'documento' => 'Faltan cuentas default CXP o ANTICIPO_PROVEEDOR para aplicar el anticipo.',
            ]);
        }

        $aplicar = collect($data['aplicaciones'])
            ->map(fn ($a) => ['documento_id' => (int) $a['documento_id'], 'monto' => round((float) ($a['monto'] ?? 0), 2)])
            ->filter(fn ($a) => $a['monto'] > 0)
            ->values();

        if ($aplicar->isEmpty()) {
            throw ValidationException::withMessages(['aplicaciones' => 'Indica el monto a aplicar en al menos una factura.']);
        }

        $aplicado = DB::transaction(function () use ($companiaId, $data, $aplicar, $documento, $cuentaCxpId, $cuentaAnticipoId, $usuario) {
            // Bloquea el anticipo y relee su disponible.
            $anticipo = CxpDocumento::whereKey($documento->id)->lockForUpdate()->firstOrFail();
            $disponible = round((float) $anticipo->saldo, 2);

            $facturas = CxpDocumento::where('compania_id', $companiaId)
                ->where('proveedor_id', $anticipo->proveedor_id)
                ->whereIn('tipo_documento', CxpDocumento::tiposPagables())
                ->whereIn('id', $aplicar->pluck('documento_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $totalAplicar = round($aplicar->sum('monto'), 2);

            if ($totalAplicar > $disponible + 0.004) {
                throw ValidationException::withMessages([
                    'aplicaciones' => 'El total a aplicar (B/. '.number_format($totalAplicar, 2).') excede el disponible del anticipo (B/. '.number_format($disponible, 2).').',
                ]);
            }

            $lineasAsiento = [[
                'cuenta_id' => $cuentaCxpId,
                'contacto_id' => $anticipo->proveedor_id,
                'descripcion' => "Aplicación anticipo {$anticipo->numero}",
                'debito' => $totalAplicar,
                'credito' => 0,
            ], [
                'cuenta_id' => $cuentaAnticipoId,
                'contacto_id' => $anticipo->proveedor_id,
                'descripcion' => "Aplicación anticipo {$anticipo->numero}",
                'debito' => 0,
                'credito' => $totalAplicar,
            ]];

            foreach ($aplicar as $apl) {
                $factura = $facturas->get($apl['documento_id']);

                if (! $factura) {
                    throw ValidationException::withMessages(['aplicaciones' => 'Una de las facturas no pertenece al proveedor del anticipo.']);
                }

                if ($apl['monto'] > round((float) $factura->saldo, 2) + 0.004) {
                    throw ValidationException::withMessages([
                        'aplicaciones' => "El monto aplicado a {$factura->numero} excede su saldo (B/. ".number_format((float) $factura->saldo, 2).').',
                    ]);
                }
            }

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId,
                $data['fecha'],
                "Aplicación de anticipo {$anticipo->numero} — ".$anticipo->proveedor->nombre,
                $anticipo->numero,
                $lineasAsiento,
                'CXP',
                'cxp_documentos',
                $anticipo->id,
                $usuario,
            );

            foreach ($aplicar as $apl) {
                $factura = $facturas->get($apl['documento_id']);

                CxpAplicacion::create([
                    'compania_id' => $companiaId,
                    'proveedor_id' => $anticipo->proveedor_id,
                    'documento_origen_id' => $anticipo->id,
                    'documento_destino_id' => $factura->id,
                    'fecha' => $data['fecha'],
                    'monto_aplicado' => $apl['monto'],
                    'asiento_id' => $asiento->id,
                    'created_by' => $usuario->email,
                ]);

                $factura->saldo = round((float) $factura->saldo - $apl['monto'], 2);
                $factura->estado = $factura->estadoSegunSaldo();
                $factura->updated_by = $usuario->email;
                $factura->save();
            }

            $anticipo->saldo = round((float) $anticipo->saldo - $totalAplicar, 2);
            $anticipo->estado = $anticipo->saldo <= 0.0
                ? CxpDocumento::ESTADO_PAGADO
                : CxpDocumento::ESTADO_PARCIAL;
            $anticipo->updated_by = $usuario->email;
            $anticipo->save();

            return $totalAplicar;
        });

        return redirect()->route('admin.cxp.anticipos.show', $documento)
            ->with('status', 'Anticipo aplicado por B/. '.number_format($aplicado, 2).'.');
    }

    public function anular(Request $request, CxpDocumento $documento): RedirectResponse
    {
        $this->autorizar($request, $documento);

        if ($documento->esAnulado()) {
            return back()->withErrors(['documento' => 'El anticipo ya está anulado.']);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($documento, $usuario) {
            // Revierte cada aplicación: restaura saldo de la factura y anula el
            // asiento de la aplicación (uno por acción, compartido entre líneas).
            $asientosAplicacion = [];

            foreach ($documento->aplicacionesComoOrigen()->with('destino')->lockForUpdate()->get() as $aplicacion) {
                $factura = $aplicacion->destino;
                $factura->saldo = round((float) $factura->saldo + (float) $aplicacion->monto_aplicado, 2);
                $factura->estado = $factura->estadoSegunSaldo();
                $factura->updated_by = $usuario->email;
                $factura->save();

                if ($aplicacion->asiento_id) {
                    $asientosAplicacion[$aplicacion->asiento_id] = true;
                }

                $aplicacion->delete();
            }

            foreach (array_keys($asientosAplicacion) as $asientoId) {
                app(AsientoAutomatico::class)->anular(\App\Models\Asiento::find($asientoId), $usuario);
            }

            // Anula el asiento de origen (Dr anticipo / Cr banco).
            app(AsientoAutomatico::class)->anular($documento->asiento, $usuario);

            $documento->update([
                'estado' => CxpDocumento::ESTADO_ANULADO,
                'saldo' => 0,
                'updated_by' => $usuario->email,
            ]);
        });

        return redirect()->route('admin.cxp.anticipos.show', $documento)
            ->with('status', "Anticipo {$documento->numero} anulado; se revirtieron sus aplicaciones y asientos.");
    }

    private function autorizar(Request $request, CxpDocumento $documento): void
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($documento->tipo_documento === CxpDocumento::TIPO_ANTICIPO, 404);
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
