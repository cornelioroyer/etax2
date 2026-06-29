<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxcDocumento;
use App\Models\ItemProducto;
use App\Models\VentaFactura;
use App\Models\VentaNotaCredito;
use App\Services\AsientoAutomatico;
use App\Services\InventarioVentas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VentaNotaCreditoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'cliente_id' => ['nullable', 'integer'],
            'desde'      => ['nullable', 'date'],
            'hasta'      => ['nullable', 'date'],
            'estado'     => ['nullable', 'string'],
        ]);

        $notas = VentaNotaCredito::with('cliente')
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

        return view('admin.ventas.notas-credito.index', compact('notas', 'filtros', 'clientes'));
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);
        $clienteId  = $request->integer('cliente_id') ?: null;
        $facturaId  = $request->integer('factura_id') ?: null;

        $facturas = $clienteId
            ? VentaFactura::where('compania_id', $companiaId)
                ->where('cliente_id', $clienteId)
                ->whereNotIn('estado', [VentaFactura::ESTADO_ANULADA])
                ->orderBy('fecha')
                ->get(['id', 'numero', 'fecha', 'total', 'saldo'])
            : collect();

        // Líneas de mercancía devolvibles de la factura seleccionada (productos que
        // salieron por inventario, con su costo de salida y lo aún no devuelto).
        $devolvibles = [];
        if ($facturaId) {
            $factura = VentaFactura::where('compania_id', $companiaId)->find($facturaId);
            if ($factura) {
                $devolvibles = $this->lineasDevolvibles($factura);
            }
        }

        $clientes = Contacto::where('compania_id', $companiaId)
            ->where('activo', true)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        $cuentasVenta = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        // Cuenta sugerida: Devoluciones y Descuentos en Ventas si está configurada;
        // si no, la de Ventas. Cuando hay devolución de mercancía se fuerza la de
        // devoluciones en el store().
        $devolucionesId = CuentaDefault::idPara($companiaId, 'DESCUENTOS_VENTA');
        $cuentaVentaId  = $devolucionesId ?? CuentaDefault::idPara($companiaId, 'VENTAS');

        return view('admin.ventas.notas-credito.create', compact(
            'clientes', 'clienteId', 'facturas', 'facturaId', 'devolvibles',
            'cuentasVenta', 'cuentaVentaId'
        ));
    }

    /**
     * Productos de una factura que aún pueden devolverse a inventario: los que
     * salieron por movimiento de inventario, con su costo de salida y la cantidad
     * disponible (vendida − ya devuelta por NCs previas no anuladas).
     *
     * @return array<int,array{item_id:int,codigo:string,nombre:string,almacen_id:int,vendida:float,devuelta:float,disponible:float,costo_unitario:float}>
     */
    private function lineasDevolvibles(VentaFactura $factura): array
    {
        $inv     = app(InventarioVentas::class);
        $costos  = $inv->costosDeSalidaPorFactura($factura->id);
        if (empty($costos)) {
            return [];
        }

        // NCs (no anuladas) que ya devolvieron contra esta factura.
        $notaIds = VentaNotaCredito::where('compania_id', $factura->compania_id)
            ->where('estado', '!=', VentaNotaCredito::ESTADO_ANULADA)
            ->where('extra->factura_id', $factura->id)
            ->pluck('id')
            ->all();
        $devuelto = $inv->devueltoPorNotas($notaIds);

        $items = ItemProducto::whereIn('id', array_keys($costos))
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy('id');

        $lineas = [];
        foreach ($costos as $itemId => $c) {
            $vendida    = (float) $c['cantidad'];
            $yaDevuelta = (float) ($devuelto[$itemId] ?? 0);
            $disponible = round($vendida - $yaDevuelta, 4);
            if ($disponible <= 0) {
                continue;
            }
            $item = $items[$itemId] ?? null;
            $lineas[] = [
                'item_id'        => (int) $itemId,
                'codigo'         => $item->codigo ?? '',
                'nombre'         => $item->nombre ?? ('Ítem '.$itemId),
                'almacen_id'     => (int) $c['almacen_id'],
                'vendida'        => $vendida,
                'devuelta'       => $yaDevuelta,
                'disponible'     => $disponible,
                'costo_unitario' => round((float) $c['costo_unitario'], 4),
            ];
        }

        return $lineas;
    }

    /**
     * Valida y arma la devolución de mercancía a partir del input `devolucion`
     * (item_id => cantidad). Devuelve un arreglo con el detalle a reingresar (costo
     * de salida + cuentas resueltas), el almacén y la cuenta de ingreso forzada
     * (Devoluciones); o un RedirectResponse con el error si algo no cuadra.
     *
     * @return array{detalle: array<int,array>, almacenId: int|null, cuentaIngresoId: int}|RedirectResponse
     */
    private function prepararDevolucion(Request $request, int $companiaId, ?int $facturaId, array $data): array|RedirectResponse
    {
        $input = array_filter(
            (array) ($data['devolucion'] ?? []),
            fn ($c) => (float) $c > 0,
        );

        // Sin mercancía: NC financiera normal, conserva la cuenta elegida por el usuario.
        if (empty($input)) {
            return ['detalle' => [], 'almacenId' => null, 'cuentaIngresoId' => (int) $data['cuenta_id']];
        }

        if (! $facturaId) {
            return back()->withInput()->withErrors(['devolucion' => 'Para devolver mercancía a inventario debes seleccionar la factura de origen.']);
        }
        $factura = VentaFactura::where('compania_id', $companiaId)->find($facturaId);
        if (! $factura) {
            return back()->withInput()->withErrors(['factura_id' => 'La factura seleccionada no existe.']);
        }

        $devolvibles = collect($this->lineasDevolvibles($factura))->keyBy('item_id');

        $items = ItemProducto::whereIn('id', array_keys($input))
            ->where('compania_id', $companiaId)
            ->get(['id', 'nombre', 'cuenta_inventario_id', 'cuenta_costo_venta_id'])
            ->keyBy('id');
        $invDefault   = CuentaDefault::idPara($companiaId, 'INVENTARIO');
        $costoDefault = CuentaDefault::idPara($companiaId, 'COSTO_VENTAS');

        $detalle   = [];
        $almacenId = null;
        foreach ($input as $itemId => $cant) {
            $itemId = (int) $itemId;
            $cant   = round((float) $cant, 4);
            $linea  = $devolvibles[$itemId] ?? null;
            if (! $linea) {
                return back()->withInput()->withErrors(['devolucion' => "El ítem #{$itemId} no pertenece a la factura o ya fue devuelto por completo."]);
            }
            if ($cant > $linea['disponible'] + 0.0001) {
                return back()->withInput()->withErrors(['devolucion' => "No puedes devolver {$cant} de «{$linea['nombre']}»: disponible {$linea['disponible']}."]);
            }
            $item        = $items[$itemId] ?? null;
            $cuentaInv   = $item->cuenta_inventario_id ?? $invDefault;
            $cuentaCosto = $item->cuenta_costo_venta_id ?? $costoDefault;
            if (! $cuentaInv || ! $cuentaCosto) {
                return back()->withInput()->withErrors(['devolucion' => "El ítem «{$linea['nombre']}» no tiene cuentas de inventario/costo configuradas."]);
            }

            $detalle[] = [
                'item_id'              => $itemId,
                'cantidad'             => $cant,
                'costo_unitario'       => $linea['costo_unitario'],
                'descripcion'          => $linea['nombre'],
                'cuenta_inventario_id' => (int) $cuentaInv,
                'cuenta_costo_id'      => (int) $cuentaCosto,
            ];
            $almacenId = $linea['almacen_id'];
        }

        // El lado de ingreso de una devolución de mercancía se fuerza a la cuenta de
        // Devoluciones (DESCUENTOS_VENTA). Debe estar configurada en la compañía.
        $devolucionesId = CuentaDefault::idPara($companiaId, 'DESCUENTOS_VENTA');
        if (! $devolucionesId) {
            return back()->withInput()->withErrors(['cuenta_id' => 'Configura la cuenta por defecto «Devoluciones y Descuentos en Ventas» (DESCUENTOS_VENTA) para registrar devoluciones de mercancía.']);
        }

        return ['detalle' => $detalle, 'almacenId' => $almacenId, 'cuentaIngresoId' => (int) $devolucionesId];
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario    = $request->user();

        $data = $request->validate([
            'cliente_id'     => ['required', 'integer'],
            'fecha'          => ['required', 'date'],
            'motivo'         => ['required', 'string', 'max:500'],
            'total'          => ['required', 'numeric', 'min:0.01'],
            'cuenta_id'      => ['required', 'integer', 'exists:cgl_cuentas,id'],
            'factura_id'     => ['nullable', 'integer'],
            'tipo_fel'       => ['nullable', 'in:04,06'],
            'devolucion'     => ['nullable', 'array'],
            'devolucion.*'   => ['nullable', 'numeric', 'min:0'],
        ]);

        $total       = round((float) $data['total'], 2);
        $cuentaCxcId = CuentaDefault::idPara($companiaId, 'CXC');
        $facturaId   = ! empty($data['factura_id']) ? (int) $data['factura_id'] : null;
        // Código DGI: 04 si referencia una factura, 06 (genérica) si no.
        $tipoFel     = $data['tipo_fel'] ?? ($facturaId ? '04' : '06');

        // ── Devolución de mercancía (opcional): reingresa al inventario al MISMO
        //    costo con que salió la venta. Se valida ANTES de la transacción. ──
        $retorno = $this->prepararDevolucion($request, $companiaId, $facturaId, $data);
        if ($retorno instanceof RedirectResponse) {
            return $retorno; // error de validación de la devolución
        }
        // Si hay mercancía, el lado de ingreso se fuerza a la cuenta de Devoluciones.
        if (! empty($retorno['detalle'])) {
            $data['cuenta_id'] = $retorno['cuentaIngresoId'];
        }

        $nota = DB::transaction(function () use ($companiaId, $data, $total, $cuentaCxcId, $tipoFel, $usuario, $facturaId, $retorno) {
            // Crear CxcDocumento de nota crédito
            $cxcNota = CxcDocumento::create([
                'compania_id'    => $companiaId,
                'cliente_id'     => $data['cliente_id'],
                'tipo_documento' => CxcDocumento::TIPO_NOTA_CREDITO,
                'numero'         => CxcDocumento::siguienteNumero($companiaId, CxcDocumento::TIPO_NOTA_CREDITO),
                'fecha'          => $data['fecha'],
                'subtotal'       => $total,
                'impuesto'       => 0,
                'total'          => $total,
                'saldo'          => $total,
                'estado'         => CxcDocumento::ESTADO_PENDIENTE,
                'created_by'     => $usuario->email,
            ]);

            // El enlace a la factura origen se guarda como STRING en extra para que
            // la consulta JSON (extra->factura_id) sea robusta en PostgreSQL y SQLite.
            $extra = ['tipo_fel' => $tipoFel];
            if ($facturaId) {
                $extra['factura_id'] = (string) $facturaId;
            }

            $nota = VentaNotaCredito::create([
                'compania_id'    => $companiaId,
                'cliente_id'     => $data['cliente_id'],
                'numero'         => VentaNotaCredito::siguienteNumero($companiaId),
                'fecha'          => $data['fecha'],
                'motivo'         => $data['motivo'],
                'total'          => $total,
                'cxc_documento_id' => $cxcNota->id,
                'estado'         => VentaNotaCredito::ESTADO_EMITIDA,
                'extra'          => $extra,
                'created_by'     => $usuario->email,
                'updated_by'     => $usuario->email,
            ]);

            // Si se vincula a una factura, aplicar automáticamente
            if (! empty($data['factura_id'])) {
                $factura = VentaFactura::where('compania_id', $companiaId)
                    ->where('id', $data['factura_id'])
                    ->lockForUpdate()->first();

                if ($factura && $factura->saldo > 0) {
                    $montoAplicar = min($total, (float) $factura->saldo);

                    if ($factura->cxc_documento_id) {
                        $cxcFactura = $factura->cxcDocumento()->lockForUpdate()->first();
                        if ($cxcFactura) {
                            $nuevoSaldo = round((float) $cxcFactura->saldo - $montoAplicar, 2);
                            $cxcFactura->update([
                                'saldo'      => max(0, $nuevoSaldo),
                                'estado'     => $nuevoSaldo <= 0 ? CxcDocumento::ESTADO_PAGADO : CxcDocumento::ESTADO_PARCIAL,
                                'updated_by' => $usuario->email,
                            ]);
                        }
                    }

                    $nuevoSaldo = round((float) $factura->saldo - $montoAplicar, 2);
                    $factura->saldo      = max(0, $nuevoSaldo);
                    $factura->estado     = $nuevoSaldo <= 0 ? VentaFactura::ESTADO_PAGADA : VentaFactura::ESTADO_PARCIAL;
                    $factura->updated_by = $usuario->email;
                    $factura->save();

                    // Reducir el saldo de la nota (ya aplicada)
                    $saldoNC = round($total - $montoAplicar, 2);
                    $cxcNota->update([
                        'saldo'  => $saldoNC,
                        'estado' => $saldoNC <= 0 ? CxcDocumento::ESTADO_PAGADO : CxcDocumento::ESTADO_PENDIENTE,
                    ]);
                    $nota->update(['estado' => $saldoNC <= 0
                        ? VentaNotaCredito::ESTADO_APLICADA
                        : VentaNotaCredito::ESTADO_EMITIDA]);
                }
            }

            // Asiento: Dr Devoluciones/Ventas (reversa ingreso), Cr CxC (reduce deuda).
            $nombreCliente = Contacto::find($data['cliente_id'])?->nombre ?? '';
            $lineasAsiento = [
                [
                    'cuenta_id'   => (int) $data['cuenta_id'],
                    'descripcion' => "Nota crédito {$nota->numero}",
                    'debito'      => $total,
                    'credito'     => 0,
                ],
                [
                    'cuenta_id'   => $cuentaCxcId,
                    'contacto_id' => (int) $data['cliente_id'],
                    'descripcion' => "Nota crédito {$nota->numero}",
                    'debito'      => 0,
                    'credito'     => $total,
                ],
            ];

            // Devolución de mercancía: Dr Inventario / Cr Costo de Ventas al costo de
            // salida (par independiente del de ingreso; el asiento sigue cuadrado).
            foreach ($retorno['detalle'] as $d) {
                $valor = round($d['cantidad'] * $d['costo_unitario'], 2);
                $lineasAsiento[] = ['cuenta_id' => $d['cuenta_inventario_id'], 'descripcion' => 'Inventario (devolución): '.$d['descripcion'], 'debito' => $valor, 'credito' => 0];
                $lineasAsiento[] = ['cuenta_id' => $d['cuenta_costo_id'],       'descripcion' => 'Costo (devolución): '.$d['descripcion'],     'debito' => 0, 'credito' => $valor];
            }

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId,
                $data['fecha'],
                "NC Ventas {$nota->numero} — {$nombreCliente}",
                $nota->numero,
                $lineasAsiento,
                'VENTAS',
                'ventas_facturas',
                $nota->id,
                $usuario,
            );

            $nota->update(['asiento_id' => $asiento->id]);

            // Reingreso físico de inventario (movimiento ENTRADA al costo de salida).
            if (! empty($retorno['detalle'])) {
                app(InventarioVentas::class)->registrarEntradaDevolucion(
                    $companiaId, $retorno['almacenId'], $data['fecha'],
                    $retorno['detalle'], $asiento->id, $nota->id, $usuario,
                );
            }

            return $nota;
        });

        return redirect()->route('admin.ventas.notas-credito.show', $nota)
            ->with('status', "Nota de crédito {$nota->numero} emitida por B/. " . number_format($total, 2) . '.');
    }

    public function show(Request $request, VentaNotaCredito $notaCredito): View
    {
        abort_unless($notaCredito->compania_id === $this->companiaActivaId($request), 404);

        $notaCredito->load(['cliente', 'asiento', 'cxcDocumento']);

        return view('admin.ventas.notas-credito.show', ['nota' => $notaCredito]);
    }

    public function anular(Request $request, VentaNotaCredito $notaCredito): RedirectResponse
    {
        abort_unless($notaCredito->compania_id === $this->companiaActivaId($request), 404);

        if ($notaCredito->esAnulada()) {
            return back()->withErrors(['nota' => 'La nota de crédito ya está anulada.']);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($notaCredito, $usuario) {
            if ($notaCredito->asiento) {
                app(AsientoAutomatico::class)->anular($notaCredito->asiento, $usuario);
            }

            // Revertir la entrada de inventario por devolución (si la hubo): vuelve a
            // descontar la mercancía reingresada al costo con que entró.
            app(InventarioVentas::class)->reversarEntradaPorDocumento(
                InventarioVentas::ORIGEN_DEVOLUCION, $notaCredito->id, $usuario,
            );

            if ($notaCredito->cxcDocumento) {
                $notaCredito->cxcDocumento->update([
                    'estado'     => CxcDocumento::ESTADO_ANULADO,
                    'saldo'      => 0,
                    'updated_by' => $usuario->email,
                ]);
            }

            $notaCredito->update([
                'estado'     => VentaNotaCredito::ESTADO_ANULADA,
                'updated_by' => $usuario->email,
            ]);
        });

        return redirect()->route('admin.ventas.notas-credito.show', $notaCredito)
            ->with('status', "Nota {$notaCredito->numero} anulada.");
    }
}
