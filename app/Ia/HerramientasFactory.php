<?php

namespace App\Ia;

use App\Models\BcoCuenta;
use App\Models\Caja;
use App\Models\CompraOrden;
use App\Models\Contacto;
use App\Models\CxcDocumento;
use App\Models\CxpDocumento;
use App\Models\FelDocumento;
use App\Models\PhCuota;
use App\Models\TallerOrden;
use App\Models\VentaFactura;
use Anthropic\Lib\Tools\BetaRunnableTool;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Construye el catálogo de herramientas (function calling) que se le ofrece a
 * Claude para responder consultas en lenguaje natural sobre etax2.
 *
 * Reglas de seguridad del POC (solo lectura):
 *   - La compañía activa se captura en cada closure desde la sesión; Claude
 *     NUNCA recibe ni puede elegir el compania_id.
 *   - Cada herramienta solo se ofrece si el usuario tiene el permiso (spatie)
 *     correspondiente, igual que la UI. Los super-admin (is_admin) ven todo.
 *   - Ninguna herramienta escribe en la base de datos.
 */
class HerramientasFactory
{
    /** @return BetaRunnableTool[] */
    public static function para(int $companiaId, $usuario): array
    {
        $puede = fn (string $permiso): bool => $usuario->is_admin || $usuario->can($permiso);

        $tools = [];

        if ($puede('cxc.ver')) {
            $tools[] = self::cxcEstadoCuenta($companiaId);
            $tools[] = self::cxcSaldosPendientes($companiaId);
        }

        if ($puede('cxp.ver')) {
            $tools[] = self::cxpEstadoCuenta($companiaId);
            $tools[] = self::cxpSaldosPendientes($companiaId);
        }

        if ($puede('ventas.ver')) {
            $tools[] = self::ventasResumen($companiaId);
        }

        if ($puede('bancos.ver')) {
            $tools[] = self::bancosSaldos($companiaId);
        }

        if ($puede('inventario.ver')) {
            $tools[] = self::inventarioExistencias($companiaId);
        }

        if ($puede('compras.ver')) {
            $tools[] = self::comprasOrdenes($companiaId);
        }

        if ($puede('activos.ver')) {
            $tools[] = self::activosFijos($companiaId);
        }

        if ($puede('caja.ver')) {
            $tools[] = self::cajaSaldos($companiaId);
        }

        if ($puede('taller.ver')) {
            $tools[] = self::tallerOrdenes($companiaId);
        }

        if ($puede('ph.ver')) {
            $tools[] = self::phCuotas($companiaId);
        }

        if ($puede('edu.ver')) {
            $tools[] = self::eduResumen($companiaId);
        }

        if ($puede('reportes.ver')) {
            $tools[] = self::balanceComprobacion($companiaId);
            $tools[] = self::estadoResultado($companiaId);
            $tools[] = self::balanceSituacion($companiaId);
            $tools[] = self::liquidacionItbms($companiaId);
        }

        if ($puede('fel.ver')) {
            $tools[] = self::felDocumentos($companiaId);
        }

        return $tools;
    }

    /** Estado de cuenta de un cliente: cargos, abonos y saldo en un rango. */
    private static function cxcEstadoCuenta(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'cxc_estado_cuenta',
                'description' => 'Estado de cuenta de un cliente en Cuentas por Cobrar: '
                    .'lista facturas (cargos), cobros y notas (abonos) con saldo corrido '
                    .'en un rango de fechas. Úsala cuando pregunten cuánto debe un cliente '
                    .'o su movimiento de cuenta.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'cliente' => ['type' => 'string', 'description' => 'Nombre o código del cliente'],
                        'desde' => ['type' => 'string', 'description' => 'Fecha inicial YYYY-MM-DD (opcional, default inicio de año)'],
                        'hasta' => ['type' => 'string', 'description' => 'Fecha final YYYY-MM-DD (opcional, default hoy)'],
                    ],
                    'required' => ['cliente'],
                ],
            ],
            run: function (array $input) use ($companiaId): string {
                $cliente = Contacto::where('compania_id', $companiaId)
                    ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
                    ->where(fn ($q) => $q
                        ->where('nombre', 'ilike', '%'.($input['cliente'] ?? '').'%')
                        ->orWhere('codigo', $input['cliente'] ?? ''))
                    ->orderBy('nombre')
                    ->first();

                if (! $cliente) {
                    return "No encontré ningún cliente que coincida con «{$input['cliente']}».";
                }

                $desde = ! empty($input['desde']) ? Carbon::parse($input['desde']) : now()->startOfYear();
                $hasta = ! empty($input['hasta']) ? Carbon::parse($input['hasta']) : now();
                if ($hasta->lt($desde)) {
                    [$desde, $hasta] = [$hasta, $desde];
                }

                $base = CxcDocumento::where('compania_id', $companiaId)
                    ->where('cliente_id', $cliente->id)
                    ->where('estado', '!=', CxcDocumento::ESTADO_ANULADO);

                // Saldo inicial: cargos − abonos anteriores al rango.
                $previos = (clone $base)->whereDate('fecha', '<', $desde->toDateString())->get();
                $saldo = 0.0;
                foreach ($previos as $d) {
                    $saldo += $d->esCargo() ? (float) $d->total : -(float) $d->total;
                }
                $saldoInicial = $saldo;

                $docs = (clone $base)
                    ->whereDate('fecha', '>=', $desde->toDateString())
                    ->whereDate('fecha', '<=', $hasta->toDateString())
                    ->orderBy('fecha')->orderBy('id')
                    ->get();

                $cargos = 0.0;
                $abonos = 0.0;
                $lineas = [];
                foreach ($docs as $d) {
                    $esCargo = $d->esCargo();
                    $saldo += $esCargo ? (float) $d->total : -(float) $d->total;
                    $esCargo ? $cargos += (float) $d->total : $abonos += (float) $d->total;
                    $lineas[] = sprintf(
                        '%s  %-12s  %-12s  %12s  saldo: %s',
                        $d->fecha->format('Y-m-d'),
                        $d->tipo_documento,
                        $d->numero,
                        number_format((float) $d->total, 2),
                        number_format($saldo, 2)
                    );
                }

                return "Cliente: {$cliente->nombre} ({$cliente->codigo})\n"
                    ."Periodo: {$desde->format('Y-m-d')} a {$hasta->format('Y-m-d')}\n"
                    .'Saldo inicial: '.number_format($saldoInicial, 2)."\n"
                    .'Cargos: '.number_format($cargos, 2).'   Abonos: '.number_format($abonos, 2)."\n"
                    .'Saldo final: '.number_format($saldo, 2)." (en balboas)\n\n"
                    .($lineas ? implode("\n", $lineas) : 'Sin movimientos en el periodo.');
            },
        );
    }

    /** Cartera total por cobrar y los principales deudores. */
    private static function cxcSaldosPendientes(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'cxc_saldos_pendientes',
                'description' => 'Resumen de la cartera por cobrar: total pendiente y los '
                    .'clientes con mayor saldo. Úsala cuando pregunten cuánto le deben en '
                    .'total, la cartera, o quiénes son los principales deudores.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'limite' => ['type' => 'integer', 'description' => 'Cuántos clientes listar (default 10)'],
                    ],
                ],
            ],
            run: function (array $input) use ($companiaId): string {
                $limite = max(1, min(50, (int) ($input['limite'] ?? 10)));

                $rows = CxcDocumento::query()
                    ->where('compania_id', $companiaId)
                    ->whereIn('tipo_documento', CxcDocumento::tiposCobrables())
                    ->whereIn('estado', [CxcDocumento::ESTADO_PENDIENTE, CxcDocumento::ESTADO_PARCIAL])
                    ->where('saldo', '>', 0)
                    ->selectRaw('cliente_id, SUM(saldo) as saldo, COUNT(*) as docs')
                    ->groupBy('cliente_id')
                    ->orderByDesc('saldo')
                    ->get();

                if ($rows->isEmpty()) {
                    return 'No hay saldos pendientes por cobrar en esta compañía.';
                }

                $total = (float) $rows->sum('saldo');
                $nombres = Contacto::whereIn('id', $rows->pluck('cliente_id'))->pluck('nombre', 'id');

                $lineas = [];
                foreach ($rows->take($limite) as $r) {
                    $lineas[] = sprintf(
                        '%-40s  %12s  (%d doc.)',
                        mb_substr($nombres[$r->cliente_id] ?? ('Cliente #'.$r->cliente_id), 0, 40),
                        number_format((float) $r->saldo, 2),
                        $r->docs
                    );
                }

                return 'Cartera total por cobrar: '.number_format($total, 2).' balboas'
                    ." en {$rows->count()} cliente(s).\n\n"
                    ."Principales deudores:\n".implode("\n", $lineas);
            },
        );
    }

    /** Balance de comprobación (sumas y saldos) por rango, sobre asientos posteados. */
    private static function balanceComprobacion(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'balance_comprobacion',
                'description' => 'Balance de comprobación contable (saldos por cuenta) en un '
                    .'rango de fechas, sobre asientos POSTEADOS. Devuelve totales de débito y '
                    .'crédito y las cuentas con mayor saldo. Úsala para preguntas sobre saldos '
                    .'contables o el balance de comprobación de un período.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'desde' => ['type' => 'string', 'description' => 'Fecha inicial YYYY-MM-DD (opcional, default inicio del mes de "hasta")'],
                        'hasta' => ['type' => 'string', 'description' => 'Fecha final YYYY-MM-DD (opcional, default fin de mes actual)'],
                        'limite' => ['type' => 'integer', 'description' => 'Cuántas cuentas listar (default 15)'],
                    ],
                ],
            ],
            run: function (array $input) use ($companiaId): string {
                $hasta = ! empty($input['hasta']) ? Carbon::parse($input['hasta']) : now()->endOfMonth();
                $desde = ! empty($input['desde']) ? Carbon::parse($input['desde']) : $hasta->copy()->startOfMonth();
                if ($desde->gt($hasta)) {
                    [$desde, $hasta] = [$hasta->copy(), $desde->copy()];
                }
                $limite = max(1, min(60, (int) ($input['limite'] ?? 15)));

                $mov = DB::table('cgl_asientos_detalle as d')
                    ->join('cgl_asientos as a', 'a.id', '=', 'd.asiento_id')
                    ->where('a.compania_id', $companiaId)
                    ->where('a.estado', 'POSTEADO')
                    ->whereDate('a.fecha', '>=', $desde->toDateString())
                    ->whereDate('a.fecha', '<=', $hasta->toDateString())
                    ->groupBy('d.cuenta_id')
                    ->selectRaw('d.cuenta_id, SUM(d.debito) as debito, SUM(d.credito) as credito')
                    ->get();

                if ($mov->isEmpty()) {
                    return "No hay asientos posteados entre {$desde->format('Y-m-d')} y {$hasta->format('Y-m-d')}.";
                }

                $cuentas = DB::table('cgl_cuentas')
                    ->where('compania_id', $companiaId)
                    ->pluck(DB::raw("codigo || ' ' || nombre"), 'id');

                $totalDebito = (float) $mov->sum('debito');
                $totalCredito = (float) $mov->sum('credito');

                $ordenado = $mov->sortByDesc(fn ($r) => abs((float) $r->debito - (float) $r->credito))->take($limite);

                $lineas = [];
                foreach ($ordenado as $r) {
                    $saldo = (float) $r->debito - (float) $r->credito;
                    $lineas[] = sprintf(
                        '%-45s  D:%13s  C:%13s  saldo:%14s',
                        mb_substr($cuentas[$r->cuenta_id] ?? ('Cuenta #'.$r->cuenta_id), 0, 45),
                        number_format((float) $r->debito, 2),
                        number_format((float) $r->credito, 2),
                        number_format($saldo, 2)
                    );
                }

                return "Balance de comprobación {$desde->format('Y-m-d')} a {$hasta->format('Y-m-d')} (balboas)\n"
                    .'Total débito: '.number_format($totalDebito, 2)
                    .'   Total crédito: '.number_format($totalCredito, 2)
                    .'   (diferencia: '.number_format($totalDebito - $totalCredito, 2).")\n\n"
                    ."Cuentas con mayor saldo:\n".implode("\n", $lineas);
            },
        );
    }

    /** Documentos FEL emitidos, con filtro por estado. */
    private static function felDocumentos(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'fel_documentos',
                'description' => 'Documentos de Factura Electrónica (FEL) emitidos: resumen por '
                    .'estado y listado reciente. Úsala para preguntas sobre facturas '
                    .'electrónicas pendientes, autorizadas, rechazadas o anuladas.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'estado' => ['type' => 'string', 'description' => 'Filtro de estado FEL: PENDIENTE, AUTORIZADO, RECHAZADO o ANULADO (opcional)'],
                        'limite' => ['type' => 'integer', 'description' => 'Cuántos documentos listar (default 15)'],
                    ],
                ],
            ],
            run: function (array $input) use ($companiaId): string {
                $limite = max(1, min(50, (int) ($input['limite'] ?? 15)));

                $resumen = FelDocumento::where('compania_id', $companiaId)
                    ->selectRaw('estado_fel, COUNT(*) as n, SUM(total) as total')
                    ->groupBy('estado_fel')
                    ->get();

                if ($resumen->isEmpty()) {
                    return 'No hay documentos FEL emitidos en esta compañía.';
                }

                $q = FelDocumento::with('cliente')->where('compania_id', $companiaId);
                if (! empty($input['estado'])) {
                    $q->where('estado_fel', strtoupper($input['estado']));
                }
                $docs = $q->orderByDesc('id')->limit($limite)->get();

                $totalLinea = $resumen
                    ->map(fn ($r) => "{$r->estado_fel}: {$r->n} doc., ".number_format((float) $r->total, 2))
                    ->implode('  |  ');

                $lineas = [];
                foreach ($docs as $d) {
                    $lineas[] = sprintf(
                        '%s  %-10s  %-11s  %-40s  %12s',
                        $d->fecha->format('Y-m-d'),
                        $d->estado_fel,
                        $d->numero,
                        mb_substr($d->cliente->nombre ?? 'Consumidor final', 0, 40),
                        number_format((float) $d->total, 2)
                    );
                }

                return "Resumen FEL por estado: {$totalLinea}\n\n"
                    .'Documentos'.(! empty($input['estado']) ? ' '.strtoupper($input['estado']) : '')." recientes:\n"
                    .implode("\n", $lineas);
            },
        );
    }

    /** Estado de cuenta de un proveedor (CxP): cargos, abonos y saldo en un rango. */
    private static function cxpEstadoCuenta(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'cxp_estado_cuenta',
                'description' => 'Estado de cuenta de un proveedor en Cuentas por Pagar: '
                    .'facturas (cargos), pagos y notas (abonos) con saldo corrido en un rango '
                    .'de fechas. Úsala cuando pregunten cuánto se le debe a un proveedor.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'proveedor' => ['type' => 'string', 'description' => 'Nombre o código del proveedor'],
                        'desde' => ['type' => 'string', 'description' => 'Fecha inicial YYYY-MM-DD (opcional, default inicio de año)'],
                        'hasta' => ['type' => 'string', 'description' => 'Fecha final YYYY-MM-DD (opcional, default hoy)'],
                    ],
                    'required' => ['proveedor'],
                ],
            ],
            run: function (array $input) use ($companiaId): string {
                $prov = Contacto::where('compania_id', $companiaId)
                    ->whereHas('tipos', fn ($q) => $q->where('codigo', 'PROVEEDOR'))
                    ->where(fn ($q) => $q
                        ->where('nombre', 'ilike', '%'.($input['proveedor'] ?? '').'%')
                        ->orWhere('codigo', $input['proveedor'] ?? ''))
                    ->orderBy('nombre')
                    ->first();

                if (! $prov) {
                    return "No encontré ningún proveedor que coincida con «{$input['proveedor']}».";
                }

                $desde = ! empty($input['desde']) ? Carbon::parse($input['desde']) : now()->startOfYear();
                $hasta = ! empty($input['hasta']) ? Carbon::parse($input['hasta']) : now();
                if ($hasta->lt($desde)) {
                    [$desde, $hasta] = [$hasta, $desde];
                }

                $base = CxpDocumento::where('compania_id', $companiaId)
                    ->where('proveedor_id', $prov->id)
                    ->where('estado', '!=', CxpDocumento::ESTADO_ANULADO);

                $saldo = 0.0;
                foreach ((clone $base)->whereDate('fecha', '<', $desde->toDateString())->get() as $d) {
                    $saldo += $d->esCargo() ? (float) $d->total : -(float) $d->total;
                }
                $saldoInicial = $saldo;

                $docs = (clone $base)
                    ->whereDate('fecha', '>=', $desde->toDateString())
                    ->whereDate('fecha', '<=', $hasta->toDateString())
                    ->orderBy('fecha')->orderBy('id')->get();

                $cargos = 0.0;
                $abonos = 0.0;
                $lineas = [];
                foreach ($docs as $d) {
                    $esCargo = $d->esCargo();
                    $saldo += $esCargo ? (float) $d->total : -(float) $d->total;
                    $esCargo ? $cargos += (float) $d->total : $abonos += (float) $d->total;
                    $lineas[] = sprintf('%s  %-12s  %-12s  %12s  saldo: %s',
                        $d->fecha->format('Y-m-d'), $d->tipo_documento, $d->numero,
                        number_format((float) $d->total, 2), number_format($saldo, 2));
                }

                return "Proveedor: {$prov->nombre} ({$prov->codigo})\n"
                    ."Periodo: {$desde->format('Y-m-d')} a {$hasta->format('Y-m-d')}\n"
                    .'Saldo inicial: '.number_format($saldoInicial, 2)."\n"
                    .'Cargos: '.number_format($cargos, 2).'   Abonos: '.number_format($abonos, 2)."\n"
                    .'Saldo final: '.number_format($saldo, 2)." (en balboas)\n\n"
                    .($lineas ? implode("\n", $lineas) : 'Sin movimientos en el periodo.');
            },
        );
    }

    /** Deuda total a proveedores y principales acreedores (CxP). */
    private static function cxpSaldosPendientes(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'cxp_saldos_pendientes',
                'description' => 'Resumen de Cuentas por Pagar: total adeudado a proveedores y '
                    .'los proveedores con mayor saldo. Úsala para preguntas sobre cuánto debes '
                    .'en total a proveedores o quiénes son tus principales acreedores.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'limite' => ['type' => 'integer', 'description' => 'Cuántos proveedores listar (default 10)'],
                    ],
                ],
            ],
            run: function (array $input) use ($companiaId): string {
                $limite = max(1, min(50, (int) ($input['limite'] ?? 10)));

                $rows = CxpDocumento::query()
                    ->where('compania_id', $companiaId)
                    ->whereIn('tipo_documento', CxpDocumento::tiposPagables())
                    ->whereIn('estado', [CxpDocumento::ESTADO_PENDIENTE, CxpDocumento::ESTADO_PARCIAL])
                    ->where('saldo', '>', 0)
                    ->selectRaw('proveedor_id, SUM(saldo) as saldo, COUNT(*) as docs')
                    ->groupBy('proveedor_id')
                    ->orderByDesc('saldo')
                    ->get();

                if ($rows->isEmpty()) {
                    return 'No hay saldos pendientes por pagar en esta compañía.';
                }

                $total = (float) $rows->sum('saldo');
                $nombres = Contacto::whereIn('id', $rows->pluck('proveedor_id'))->pluck('nombre', 'id');

                $lineas = [];
                foreach ($rows->take($limite) as $r) {
                    $lineas[] = sprintf('%-40s  %12s  (%d doc.)',
                        mb_substr($nombres[$r->proveedor_id] ?? ('Proveedor #'.$r->proveedor_id), 0, 40),
                        number_format((float) $r->saldo, 2), $r->docs);
                }

                return 'Deuda total a proveedores: '.number_format($total, 2).' balboas'
                    ." en {$rows->count()} proveedor(es).\n\n"
                    ."Principales acreedores:\n".implode("\n", $lineas);
            },
        );
    }

    /** Resumen de ventas facturadas en un rango (excluye borradores y anuladas). */
    private static function ventasResumen(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'ventas_resumen',
                'description' => 'Resumen de facturas de venta en un rango de fechas: total '
                    .'facturado, cantidad de facturas y los clientes que más compraron. Excluye '
                    .'borradores y anuladas. Úsala para preguntas sobre ventas o facturación de un período.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'desde' => ['type' => 'string', 'description' => 'Fecha inicial YYYY-MM-DD (opcional, default inicio del mes)'],
                        'hasta' => ['type' => 'string', 'description' => 'Fecha final YYYY-MM-DD (opcional, default fin de mes actual)'],
                        'limite' => ['type' => 'integer', 'description' => 'Cuántos clientes listar (default 10)'],
                    ],
                ],
            ],
            run: function (array $input) use ($companiaId): string {
                $hasta = ! empty($input['hasta']) ? Carbon::parse($input['hasta']) : now()->endOfMonth();
                $desde = ! empty($input['desde']) ? Carbon::parse($input['desde']) : $hasta->copy()->startOfMonth();
                if ($desde->gt($hasta)) {
                    [$desde, $hasta] = [$hasta->copy(), $desde->copy()];
                }
                $limite = max(1, min(50, (int) ($input['limite'] ?? 10)));

                $base = VentaFactura::where('compania_id', $companiaId)
                    ->whereNotIn('estado', [VentaFactura::ESTADO_BORRADOR, VentaFactura::ESTADO_ANULADA])
                    ->whereDate('fecha', '>=', $desde->toDateString())
                    ->whereDate('fecha', '<=', $hasta->toDateString());

                $totalFacturado = (float) (clone $base)->sum('total');
                $cantidad = (clone $base)->count();

                if ($cantidad === 0) {
                    return "No hay facturas de venta entre {$desde->format('Y-m-d')} y {$hasta->format('Y-m-d')}.";
                }

                $porCliente = (clone $base)
                    ->selectRaw('cliente_id, SUM(total) as total, COUNT(*) as docs')
                    ->groupBy('cliente_id')->orderByDesc('total')->get();
                $nombres = Contacto::whereIn('id', $porCliente->pluck('cliente_id'))->pluck('nombre', 'id');

                $lineas = [];
                foreach ($porCliente->take($limite) as $r) {
                    $lineas[] = sprintf('%-40s  %12s  (%d fact.)',
                        mb_substr($nombres[$r->cliente_id] ?? 'Consumidor final', 0, 40),
                        number_format((float) $r->total, 2), $r->docs);
                }

                return "Ventas {$desde->format('Y-m-d')} a {$hasta->format('Y-m-d')}:\n"
                    .'Total facturado: '.number_format($totalFacturado, 2).' balboas en '.$cantidad." factura(s).\n\n"
                    ."Clientes que más compraron:\n".implode("\n", $lineas);
            },
        );
    }

    /** Saldos actuales de las cuentas bancarias. */
    private static function bancosSaldos(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'bancos_saldos',
                'description' => 'Saldos actuales de las cuentas bancarias (saldo inicial más '
                    .'créditos menos débitos de sus movimientos). Úsala para preguntas sobre '
                    .'cuánto dinero hay en bancos o el saldo de una cuenta.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            run: function (array $input) use ($companiaId): string {
                $cuentas = BcoCuenta::with('banco')
                    ->where('compania_id', $companiaId)
                    ->where('activa', true)
                    ->orderBy('nombre')
                    ->get();

                if ($cuentas->isEmpty()) {
                    return 'No hay cuentas bancarias activas en esta compañía.';
                }

                $total = 0.0;
                $lineas = [];
                foreach ($cuentas as $c) {
                    $saldo = $c->saldo_actual; // accessor: saldo_inicial + créditos - débitos
                    $total += $saldo;
                    $lineas[] = sprintf('%-32s  %-18s  %14s',
                        mb_substr(($c->banco->nombre ?? '').' — '.$c->nombre, 0, 32),
                        $c->numero_cuenta,
                        number_format($saldo, 2));
                }

                return 'Saldo total en bancos: '.number_format($total, 2).' balboas en '
                    .$cuentas->count()." cuenta(s).\n\n".implode("\n", $lineas);
            },
        );
    }

    /** Existencias de inventario: por producto o los de mayor stock. */
    private static function inventarioExistencias(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'inventario_existencias',
                'description' => 'Existencias de inventario: stock de un producto por almacén, o '
                    .'los productos con mayor stock si no se especifica. Úsala para preguntas '
                    .'sobre cuántas unidades hay de un producto o el inventario disponible.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'producto' => ['type' => 'string', 'description' => 'Nombre o código del producto (opcional)'],
                        'limite' => ['type' => 'integer', 'description' => 'Cuántas filas listar (default 15)'],
                    ],
                ],
            ],
            run: function (array $input) use ($companiaId): string {
                $limite = max(1, min(60, (int) ($input['limite'] ?? 15)));

                if (! empty($input['producto'])) {
                    $filas = DB::table('inv_existencias as e')
                        ->join('item_productos_servicios as i', 'i.id', '=', 'e.item_id')
                        ->leftJoin('inv_almacenes as a', 'a.id', '=', 'e.almacen_id')
                        ->where('i.compania_id', $companiaId)
                        ->where(fn ($q) => $q
                            ->where('i.nombre', 'ilike', '%'.$input['producto'].'%')
                            ->orWhere('i.codigo', $input['producto']))
                        ->orderBy('i.nombre')->orderBy('a.nombre')
                        ->limit($limite)
                        ->get(['i.codigo', 'i.nombre', 'a.nombre as almacen', 'e.cantidad', 'e.costo_promedio']);

                    if ($filas->isEmpty()) {
                        return "No encontré existencias para «{$input['producto']}».";
                    }

                    $lineas = [];
                    $totalUnidades = 0.0;
                    foreach ($filas as $f) {
                        $totalUnidades += (float) $f->cantidad;
                        $lineas[] = sprintf('%-12s %-32s  %-18s  %12s u.  costo prom: %s',
                            $f->codigo, mb_substr($f->nombre, 0, 32),
                            mb_substr($f->almacen ?? 'sin almacén', 0, 18),
                            number_format((float) $f->cantidad, 2),
                            number_format((float) $f->costo_promedio, 2));
                    }

                    return 'Existencias para «'.$input['producto']."»:\n".implode("\n", $lineas)
                        ."\n\nTotal: ".number_format($totalUnidades, 2).' unidades.';
                }

                // Sin filtro: productos con mayor stock total (sumando almacenes).
                $filas = DB::table('inv_existencias as e')
                    ->join('item_productos_servicios as i', 'i.id', '=', 'e.item_id')
                    ->where('i.compania_id', $companiaId)
                    ->where('e.cantidad', '>', 0)
                    ->groupBy('i.codigo', 'i.nombre')
                    ->selectRaw('i.codigo, i.nombre, SUM(e.cantidad) as cantidad, SUM(e.cantidad * e.costo_promedio) as valor')
                    ->orderByDesc('cantidad')
                    ->limit($limite)
                    ->get();

                if ($filas->isEmpty()) {
                    return 'No hay existencias registradas en esta compañía.';
                }

                $valorTotal = (float) $filas->sum('valor');
                $lineas = [];
                foreach ($filas as $f) {
                    $lineas[] = sprintf('%-12s %-38s  %12s u.  valor: %s',
                        $f->codigo, mb_substr($f->nombre, 0, 38),
                        number_format((float) $f->cantidad, 2),
                        number_format((float) $f->valor, 2));
                }

                return "Productos con mayor stock:\n".implode("\n", $lineas)
                    ."\n\nValor de inventario (estos productos): ".number_format($valorTotal, 2).' balboas.';
            },
        );
    }

    /** Órdenes de compra: resumen por estado y pendientes. */
    private static function comprasOrdenes(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'compras_ordenes',
                'description' => 'Órdenes de compra: resumen por estado, pendientes de recibir '
                    .'y pendientes de facturar. Úsala para preguntas sobre órdenes de compra o '
                    .'qué compras están en proceso.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            run: function (array $input) use ($companiaId): string {
                $resumen = CompraOrden::where('compania_id', $companiaId)
                    ->selectRaw('estado, COUNT(*) as n, SUM(total) as total')
                    ->groupBy('estado')->get();

                if ($resumen->isEmpty()) {
                    return 'No hay órdenes de compra registradas en esta compañía.';
                }

                $porRecibir = CompraOrden::where('compania_id', $companiaId)
                    ->whereIn('estado', [CompraOrden::ESTADO_APROBADA, CompraOrden::ESTADO_RECIBIDA_PARCIAL]);
                $porFacturar = CompraOrden::where('compania_id', $companiaId)
                    ->whereIn('estado', [CompraOrden::ESTADO_APROBADA, CompraOrden::ESTADO_RECIBIDA_PARCIAL, CompraOrden::ESTADO_RECIBIDA])
                    ->whereNull('cxp_documento_id');

                $lineas = $resumen
                    ->map(fn ($r) => sprintf('%-18s  %3d orden(es)  %14s', $r->estado, $r->n, number_format((float) $r->total, 2)))
                    ->implode("\n");

                return "Órdenes de compra por estado (balboas):\n".$lineas."\n\n"
                    .'Pendientes de recibir: '.$porRecibir->count().' ('.number_format((float) $porRecibir->sum('total'), 2).")\n"
                    .'Pendientes de facturar: '.$porFacturar->count().' ('.number_format((float) $porFacturar->sum('total'), 2).')';
            },
        );
    }

    /** Activos fijos: cantidad, costo, depreciación acumulada y valor en libros. */
    private static function activosFijos(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'activos_fijos',
                'description' => 'Resumen de activos fijos: cantidad de activos, costo de '
                    .'adquisición, depreciación acumulada y valor en libros. Úsala para '
                    .'preguntas sobre activos fijos, su valor o depreciación.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            run: function (array $input) use ($companiaId): string {
                $base = DB::table('afi_activos')->where('compania_id', $companiaId);
                $activos = (clone $base)->where('estado', 'ACTIVO');

                $count = (clone $activos)->count();
                if ($count === 0 && (clone $base)->count() === 0) {
                    return 'No hay activos fijos registrados en esta compañía.';
                }

                $costo = (float) (clone $activos)->sum('valor_compra');
                $baja = (clone $base)->where('estado', 'DADO_DE_BAJA')->count();

                $depAcum = (float) DB::table('afi_depreciaciones as d')
                    ->join('afi_activos as a', 'a.id', '=', 'd.activo_id')
                    ->where('a.compania_id', $companiaId)
                    ->where('a.estado', 'ACTIVO')
                    ->where('d.estado', 'POSTEADA')
                    ->sum('d.monto');

                $valorLibros = round($costo - $depAcum, 2);

                return "Activos fijos:\n"
                    ."Activos en uso: {$count}".($baja ? "   Dados de baja: {$baja}" : '')."\n"
                    .'Costo de adquisición: '.number_format($costo, 2)." balboas\n"
                    .'Depreciación acumulada: '.number_format($depAcum, 2)." balboas\n"
                    .'Valor en libros: '.number_format($valorLibros, 2).' balboas';
            },
        );
    }

    /** Estado de resultado (utilidad) del año hasta un mes, desde cgl_saldos. */
    private static function estadoResultado(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'estado_resultado',
                'description' => 'Estado de resultado: ingresos, costos, gastos, utilidad bruta y '
                    .'utilidad neta del año fiscal acumulados hasta un mes (y la columna del mes). '
                    .'Úsala para preguntas sobre utilidad, ganancia, pérdida o resultados.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'anio' => ['type' => 'integer', 'description' => 'Año fiscal (opcional, default el más reciente con datos)'],
                        'mes' => ['type' => 'integer', 'description' => 'Mes de corte 1-12 (opcional, default el más reciente)'],
                    ],
                ],
            ],
            run: function (array $input) use ($companiaId): string {
                $per = DB::table('cgl_saldos as s')->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
                    ->where('s.compania_id', $companiaId)->where('p.mes', '<=', 12)
                    ->orderByDesc('p.anio')->orderByDesc('p.mes')->first(['p.anio', 'p.mes']);

                if (! $per) {
                    return 'No hay saldos contables registrados (cgl_saldos) para generar el estado de resultado.';
                }

                $anio = (int) ($input['anio'] ?? $per->anio);
                $mes = (int) ($input['mes'] ?? $per->mes);
                $mes = max(1, min(12, $mes));

                $rows = DB::table('cgl_saldos as s')
                    ->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
                    ->join('cgl_cuentas as c', 'c.id', '=', 's.cuenta_id')
                    ->join('cgl_tipos_cuenta as t', 't.id', '=', 'c.tipo_cuenta_id')
                    ->where('s.compania_id', $companiaId)
                    ->where('p.anio', $anio)->where('p.mes', '<=', $mes)
                    ->whereIn('t.codigo', ['INGRESO', 'COSTO', 'GASTO'])
                    ->groupBy('t.codigo')
                    ->selectRaw('t.codigo as tipo, SUM(s.debito - s.credito) as ytd, SUM(CASE WHEN p.mes = '.$mes.' THEN s.debito - s.credito ELSE 0 END) as mes')
                    ->get()->keyBy('tipo');

                $ytd = fn ($t, $signo) => round($signo * (float) ($rows[$t]->ytd ?? 0), 2);
                $m = fn ($t, $signo) => round($signo * (float) ($rows[$t]->mes ?? 0), 2);

                $ingY = $ytd('INGRESO', -1); $cosY = $ytd('COSTO', 1); $gasY = $ytd('GASTO', 1);
                $ingM = $m('INGRESO', -1);   $cosM = $m('COSTO', 1);   $gasM = $m('GASTO', 1);
                $brutaY = round($ingY - $cosY, 2); $netaY = round($brutaY - $gasY, 2);
                $brutaM = round($ingM - $cosM, 2); $netaM = round($brutaM - $gasM, 2);

                $meses = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
                $f = fn ($v) => number_format($v, 2);

                return "Estado de resultado — {$anio}, acumulado a {$meses[$mes]} (YTD) | columna del mes:\n"
                    ."Ingresos:        ".$f($ingY)."  |  ".$f($ingM)."\n"
                    ."Costos:          ".$f($cosY)."  |  ".$f($cosM)."\n"
                    ."Utilidad bruta:  ".$f($brutaY)."  |  ".$f($brutaM)."\n"
                    ."Gastos:          ".$f($gasY)."  |  ".$f($gasM)."\n"
                    ."UTILIDAD NETA:   ".$f($netaY)."  |  ".$f($netaM)."  (balboas)";
            },
        );
    }

    /** Balance de situación (activo, pasivo, patrimonio) a un corte, desde cgl_saldos. */
    private static function balanceSituacion(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'balance_situacion',
                'description' => 'Balance de situación (estado de situación financiera): total de '
                    .'activos, pasivos y patrimonio (incluida la utilidad del período) a una fecha '
                    .'de corte. Úsala para preguntas sobre la situación financiera, activos, '
                    .'pasivos o patrimonio de la empresa.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'anio' => ['type' => 'integer', 'description' => 'Año de corte (opcional, default el más reciente)'],
                        'mes' => ['type' => 'integer', 'description' => 'Mes de corte 1-12 (opcional, default el más reciente)'],
                    ],
                ],
            ],
            run: function (array $input) use ($companiaId): string {
                $per = DB::table('cgl_saldos as s')->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
                    ->where('s.compania_id', $companiaId)
                    ->orderByDesc('p.anio')->orderByDesc('p.mes')->first(['p.anio', 'p.mes']);

                if (! $per) {
                    return 'No hay saldos contables registrados (cgl_saldos) para generar el balance de situación.';
                }

                $anio = (int) ($input['anio'] ?? $per->anio);
                $mes = (int) ($input['mes'] ?? $per->mes);
                $mes = max(1, min(12, $mes));

                $rows = DB::table('cgl_saldos as s')
                    ->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
                    ->join('cgl_cuentas as c', 'c.id', '=', 's.cuenta_id')
                    ->join('cgl_tipos_cuenta as t', 't.id', '=', 'c.tipo_cuenta_id')
                    ->where('s.compania_id', $companiaId)
                    ->where(fn ($q) => $q->where('p.anio', '<', $anio)
                        ->orWhere(fn ($q) => $q->where('p.anio', $anio)->where('p.mes', '<=', $mes)))
                    ->groupBy('t.codigo')
                    ->selectRaw('t.codigo as tipo, SUM(s.debito - s.credito) as total')
                    ->pluck('total', 'tipo');

                $g = fn ($t) => (float) ($rows[$t] ?? 0);
                $totalActivos = round($g('ACTIVO'), 2);
                $totalPasivos = round(-$g('PASIVO'), 2);
                $patrimonioCuentas = round(-$g('PATRIMONIO'), 2);
                $utilidad = round(-($g('INGRESO') + $g('COSTO') + $g('GASTO')), 2);
                $totalPatrimonio = round($patrimonioCuentas + $utilidad, 2);
                $pasMasPat = round($totalPasivos + $totalPatrimonio, 2);
                $f = fn ($v) => number_format($v, 2);

                $meses = [1=>'ene',2=>'feb',3=>'mar',4=>'abr',5=>'may',6=>'jun',7=>'jul',8=>'ago',9=>'sep',10=>'oct',11=>'nov',12=>'dic'];

                return "Balance de situación al cierre de {$meses[$mes]} {$anio} (balboas):\n"
                    ."Total Activos:              ".$f($totalActivos)."\n"
                    ."Total Pasivos:              ".$f($totalPasivos)."\n"
                    ."Patrimonio (cuentas):       ".$f($patrimonioCuentas)."\n"
                    ."  + Utilidad del período:   ".$f($utilidad)."\n"
                    ."Total Patrimonio:           ".$f($totalPatrimonio)."\n"
                    ."Pasivo + Patrimonio:        ".$f($pasMasPat)
                    .(abs($totalActivos - $pasMasPat) < 0.01 ? "  (cuadra ✓)" : "  (descuadre: ".$f($totalActivos - $pasMasPat).')');
            },
        );
    }

    /** Liquidación de ITBMS: cobrado en ventas vs. crédito en compras, por año. */
    private static function liquidacionItbms(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'liquidacion_itbms',
                'description' => 'Liquidación mensual de ITBMS de un año: ITBMS cobrado en ventas, '
                    .'ITBMS crédito en compras y el neto a pagar o a favor. Úsala para preguntas '
                    .'sobre ITBMS, impuesto a pagar a la DGI o liquidación de impuestos.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'anio' => ['type' => 'integer', 'description' => 'Año (opcional, default el actual)'],
                    ],
                ],
            ],
            run: function (array $input) use ($companiaId): string {
                $anio = (int) ($input['anio'] ?? now()->year);

                $ventas = DB::table('cxc_documentos')->where('compania_id', $companiaId)->where('estado', '!=', 'ANULADO')->whereYear('fecha', $anio);
                $cobrado = (float) (clone $ventas)->whereIn('tipo_documento', ['FACTURA', 'NOTA_DEBITO'])->sum('impuesto')
                    - (float) (clone $ventas)->where('tipo_documento', 'NOTA_CREDITO')->sum('impuesto');
                $baseVentas = (float) (clone $ventas)->whereIn('tipo_documento', ['FACTURA', 'NOTA_DEBITO'])->sum('subtotal');

                $itbmsCompras = DB::table('cxp_documentos')->where('compania_id', $companiaId)->where('estado', '!=', 'ANULADO')->whereYear('fecha', $anio);
                $credito = (float) (clone $itbmsCompras)->whereIn('tipo_documento', ['FACTURA', 'NOTA_DEBITO'])->sum('impuesto')
                    - (float) (clone $itbmsCompras)->where('tipo_documento', 'NOTA_CREDITO')->sum('impuesto');
                $baseCompras = (float) (clone $itbmsCompras)->whereIn('tipo_documento', ['FACTURA', 'NOTA_DEBITO'])->sum('subtotal');

                $neto = round($cobrado - $credito, 2);
                $f = fn ($v) => number_format($v, 2);

                if (abs($cobrado) < 0.01 && abs($credito) < 0.01) {
                    return "No hay ITBMS registrado en {$anio}.";
                }

                $etiqueta = $neto > 0 ? 'A PAGAR a la DGI' : ($neto < 0 ? 'A FAVOR (crédito a arrastrar)' : 'en cero');

                return "Liquidación de ITBMS {$anio} (balboas):\n"
                    ."Ventas — base: ".$f($baseVentas)."   ITBMS cobrado: ".$f($cobrado)."\n"
                    ."Compras — base: ".$f($baseCompras)."   ITBMS crédito: ".$f($credito)."\n"
                    ."Neto del año: ".$f($neto)."  ({$etiqueta})\n"
                    ."Nota: la DGI se liquida mes a mes; este es el consolidado anual.";
            },
        );
    }

    /** Saldos en efectivo de las cajas menudas activas. */
    private static function cajaSaldos(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'caja_saldos',
                'description' => 'Saldo en efectivo de las cajas menudas (caja chica) activas: '
                    .'reembolsos e ingresos menos egresos y vales pendientes. Úsala para '
                    .'preguntas sobre cuánto efectivo hay en caja chica.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            run: function (array $input) use ($companiaId): string {
                $cajas = Caja::where('compania_id', $companiaId)->where('activa', true)->orderBy('nombre')->get();

                if ($cajas->isEmpty()) {
                    return 'No hay cajas menudas activas en esta compañía.';
                }

                $total = 0.0;
                $lineas = [];
                foreach ($cajas as $c) {
                    $saldo = $c->saldoSistema();
                    $total += $saldo;
                    $lineas[] = sprintf('%-36s  %14s', mb_substr($c->nombre, 0, 36), number_format($saldo, 2));
                }

                return 'Efectivo total en cajas menudas: '.number_format($total, 2).' balboas en '
                    .$cajas->count()." caja(s).\n\n".implode("\n", $lineas);
            },
        );
    }

    /** Órdenes de taller: resumen por estado y saldo pendiente. */
    private static function tallerOrdenes(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'taller_ordenes',
                'description' => 'Órdenes de trabajo del taller: resumen por estado, total '
                    .'facturable y saldo pendiente de cobro. Úsala para preguntas sobre órdenes '
                    .'de taller, reparaciones en proceso o pendientes de cobro.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            run: function (array $input) use ($companiaId): string {
                $resumen = TallerOrden::where('compania_id', $companiaId)
                    ->selectRaw('estado, COUNT(*) as n, SUM(total) as total, SUM(saldo) as saldo')
                    ->groupBy('estado')->orderByDesc('n')->get();

                if ($resumen->isEmpty()) {
                    return 'No hay órdenes de taller registradas en esta compañía.';
                }

                $totalOrdenes = (int) $resumen->sum('n');
                $saldoPend = (float) TallerOrden::where('compania_id', $companiaId)
                    ->where('estado', '!=', 'cancelada')->where('saldo', '>', 0)->sum('saldo');

                $lineas = $resumen
                    ->map(fn ($r) => sprintf('%-22s  %3d orden(es)  total %12s  saldo %12s',
                        $r->estado, $r->n, number_format((float) $r->total, 2), number_format((float) $r->saldo, 2)))
                    ->implode("\n");

                return "Órdenes de taller ({$totalOrdenes} en total) por estado (balboas):\n".$lineas
                    ."\n\nSaldo pendiente de cobro: ".number_format($saldoPend, 2).' balboas.';
            },
        );
    }

    /** Cuotas de propiedad horizontal: emitido, cobrado y morosidad. */
    private static function phCuotas(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'ph_cuotas',
                'description' => 'Cuotas de propiedad horizontal: total emitido, cobrado y '
                    .'pendiente (morosidad). Úsala para preguntas sobre cuotas de mantenimiento, '
                    .'cobros de PH o morosidad de propietarios.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            run: function (array $input) use ($companiaId): string {
                $base = PhCuota::where('compania_id', $companiaId)
                    ->where('estado', '!=', PhCuota::ESTADO_ANULADO);

                $totalCuotas = (clone $base)->count();
                if ($totalCuotas === 0) {
                    return 'No hay cuotas de PH registradas en esta compañía.';
                }

                $emitido = (float) (clone $base)->sum('monto');
                $pagado = (float) (clone $base)->sum('monto_pagado');
                $pendiente = round($emitido - $pagado, 2);

                $morosas = (clone $base)
                    ->whereIn('estado', [PhCuota::ESTADO_PENDIENTE, PhCuota::ESTADO_VENCIDO])
                    ->whereRaw('monto > monto_pagado')->count();
                $vencidas = (clone $base)->where('estado', PhCuota::ESTADO_VENCIDO)->count();

                return "Cuotas de propiedad horizontal ({$totalCuotas} cuotas, balboas):\n"
                    .'Total emitido: '.number_format($emitido, 2)."\n"
                    .'Total cobrado: '.number_format($pagado, 2)."\n"
                    .'Pendiente (morosidad): '.number_format($pendiente, 2)."\n"
                    ."Cuotas con saldo: {$morosas}".($vencidas ? "   Vencidas: {$vencidas}" : '');
            },
        );
    }

    /** Resumen de estudiantes (administración educativa). */
    private static function eduResumen(int $companiaId): BetaRunnableTool
    {
        return new BetaRunnableTool(
            definition: [
                'name' => 'edu_resumen',
                'description' => 'Resumen de administración educativa: estudiantes por estado '
                    .'(activos, retirados, etc.). Úsala para preguntas sobre cantidad de '
                    .'estudiantes o matrícula de la institución.',
                'input_schema' => ['type' => 'object', 'properties' => (object) []],
            ],
            run: function (array $input) use ($companiaId): string {
                $rows = DB::table('edu_estudiantes as e')
                    ->join('edu_instituciones as i', 'i.id', '=', 'e.institucion_id')
                    ->where('i.compania_id', $companiaId)
                    ->groupBy('e.estado')
                    ->selectRaw('e.estado, COUNT(*) as n')
                    ->orderByDesc('n')
                    ->get();

                if ($rows->isEmpty()) {
                    return 'No hay estudiantes registrados en esta compañía.';
                }

                $total = (int) $rows->sum('n');
                $lineas = $rows->map(fn ($r) => sprintf('%-20s  %4d', $r->estado ?? 'sin estado', $r->n))->implode("\n");

                return "Estudiantes ({$total} en total) por estado:\n".$lineas;
            },
        );
    }
}
