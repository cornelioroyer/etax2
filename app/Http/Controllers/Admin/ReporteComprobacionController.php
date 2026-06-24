<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Balance de Comprobación por rango de fechas (sumas y saldos).
 *
 * Se pide un período «desde / hasta» y, para cada cuenta de movimiento:
 *   - Balance Inicial : saldo acumulado (débito − crédito) de los asientos
 *                       posteados ANTES de la fecha «desde».
 *   - Débito / Crédito: lo registrado dentro del rango pedido.
 *   - Corriente       : débito − crédito del rango.
 *   - Balance Final   : Balance Inicial + Corriente.
 *
 * Los datos provienen de los asientos POSTEADOS (cgl_asientos +
 * cgl_asientos_detalle) para poder cortar por fecha exacta. Las cifras usan
 * la convención débito − crédito (los saldos acreedores se muestran entre
 * paréntesis) y se presentan según la jerarquía del plan de cuentas, con un
 * subtotal «Suma» por grupo. Por partida doble, los totales de Débito y
 * Crédito deben coincidir y los Balances Inicial/Final deben sumar cero.
 */
class ReporteComprobacionController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    public function __invoke(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $validado = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $hasta = ! empty($validado['hasta'])
            ? Carbon::parse($validado['hasta'])->startOfDay()
            : now()->endOfMonth()->startOfDay();
        $desde = ! empty($validado['desde'])
            ? Carbon::parse($validado['desde'])->startOfDay()
            : $hasta->copy()->startOfMonth();

        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta->copy(), $desde->copy()];
        }

        $compania = Compania::find($companiaId);

        $hayAsientos = DB::table('cgl_asientos')
            ->where('compania_id', $companiaId)
            ->where('estado', 'POSTEADO')
            ->exists();

        if (! $hayAsientos) {
            return view('admin.reportes.balance-comprobacion', [
                'sinDatos' => true,
                'desde' => $desde,
                'hasta' => $hasta,
                'filas' => collect(),
                'totales' => $this->totalesVacios(),
            ]);
        }

        // Balance Inicial: saldo (débito − crédito) acumulado ANTES de «desde».
        $inicial = DB::table('cgl_asientos_detalle as d')
            ->join('cgl_asientos as a', 'a.id', '=', 'd.asiento_id')
            ->where('a.compania_id', $companiaId)
            ->where('a.estado', 'POSTEADO')
            ->whereDate('a.fecha', '<', $desde->toDateString())
            ->groupBy('d.cuenta_id')
            ->selectRaw('d.cuenta_id, SUM(d.debito - d.credito) as inicial')
            ->pluck('inicial', 'cuenta_id');

        // Movimientos dentro del rango pedido.
        $movimiento = DB::table('cgl_asientos_detalle as d')
            ->join('cgl_asientos as a', 'a.id', '=', 'd.asiento_id')
            ->where('a.compania_id', $companiaId)
            ->where('a.estado', 'POSTEADO')
            ->whereDate('a.fecha', '>=', $desde->toDateString())
            ->whereDate('a.fecha', '<=', $hasta->toDateString())
            ->groupBy('d.cuenta_id')
            ->selectRaw('d.cuenta_id, SUM(d.debito) as debito, SUM(d.credito) as credito')
            ->get()
            ->keyBy('cuenta_id');

        // Plan de cuentas completo de la compañía (para armar la jerarquía).
        $cuentas = DB::table('cgl_cuentas')
            ->where('compania_id', $companiaId)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'cuenta_padre_id', 'nivel', 'permite_movimiento']);

        $hijos = $cuentas->groupBy('cuenta_padre_id');

        $filas = [];
        $totales = $this->totalesVacios();

        foreach ($cuentas->whereNull('cuenta_padre_id')->sortBy('codigo') as $raiz) {
            $agg = $this->recorrer($raiz, $hijos, $inicial, $movimiento, $filas);

            foreach ($totales as $k => $v) {
                $totales[$k] = round($v + $agg[$k], 2);
            }
        }

        $datos = [
            'compania' => $compania,
            'desde' => $desde,
            'hasta' => $hasta,
            'filas' => collect($filas),
            'totales' => $totales,
            'generado' => now(),
            'usuario' => $request->user()->name ?: $request->user()->email,
        ];

        if ($export = $this->exportarReporte($request, 'admin.exports.balance-comprobacion', $datos,
            'balance_comprobacion_'.$desde->format('Ymd').'_'.$hasta->format('Ymd'))) {
            return $export;
        }

        return view('admin.reportes.balance-comprobacion', array_merge($datos, [
            'sinDatos' => false,
        ]));
    }

    /**
     * Detalle del saldo de una cuenta: parte del balance inicial (antes de
     * «desde») y lista los asientos posteados dentro del rango, con saldo
     * corriente acumulado que termina en el Balance Final mostrado en el
     * reporte. Devuelve JSON para el modal.
     */
    public function detalle(Request $request): Response
    {
        $companiaId = $this->companiaActivaId($request);

        $validado = $request->validate([
            'cuenta' => ['required', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $hasta = ! empty($validado['hasta'])
            ? Carbon::parse($validado['hasta'])->startOfDay()
            : now()->endOfMonth()->startOfDay();
        $desde = ! empty($validado['desde'])
            ? Carbon::parse($validado['desde'])->startOfDay()
            : $hasta->copy()->startOfMonth();

        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta->copy(), $desde->copy()];
        }

        $cuenta = DB::table('cgl_cuentas')
            ->where('compania_id', $companiaId)
            ->where('id', $validado['cuenta'])
            ->first(['id', 'codigo', 'nombre']);

        abort_if($cuenta === null, Response::HTTP_NOT_FOUND);

        // Balance inicial (débito − crédito) acumulado ANTES de «desde».
        $inicial = (float) DB::table('cgl_asientos_detalle as d')
            ->join('cgl_asientos as a', 'a.id', '=', 'd.asiento_id')
            ->where('a.compania_id', $companiaId)
            ->where('a.estado', 'POSTEADO')
            ->where('d.cuenta_id', $cuenta->id)
            ->whereDate('a.fecha', '<', $desde->toDateString())
            ->sum(DB::raw('d.debito - d.credito'));

        // Movimientos dentro del rango, en orden cronológico. Se trae también el
        // origen del asiento para enlazar cada línea a su documento fuente.
        $movimientos = DB::table('cgl_asientos_detalle as d')
            ->join('cgl_asientos as a', 'a.id', '=', 'd.asiento_id')
            ->where('a.compania_id', $companiaId)
            ->where('a.estado', 'POSTEADO')
            ->where('d.cuenta_id', $cuenta->id)
            ->whereDate('a.fecha', '>=', $desde->toDateString())
            ->whereDate('a.fecha', '<=', $hasta->toDateString())
            ->orderBy('a.fecha')
            ->orderBy('a.numero')
            ->orderBy('d.linea')
            ->get([
                'a.id as asiento_id', 'a.fecha', 'a.numero', 'a.descripcion as asiento_desc',
                'a.origen_tabla', 'a.origen_id',
                'd.descripcion as linea_desc', 'd.debito', 'd.credito',
            ]);

        // Resuelve, sin N+1, el enlace al documento fuente de cada movimiento.
        $documentos = $this->resolverDocumentos($movimientos, $companiaId);

        $saldo = round($inicial, 2);
        $filas = [];

        foreach ($movimientos as $m) {
            $deb = round((float) $m->debito, 2);
            $cre = round((float) $m->credito, 2);
            $saldo = round($saldo + $deb - $cre, 2);

            $filas[] = [
                'fecha' => Carbon::parse($m->fecha)->format('d/m/Y'),
                'numero' => $m->numero,
                'descripcion' => $m->linea_desc ?: $m->asiento_desc ?: '',
                'debito' => $deb,
                'credito' => $cre,
                'saldo' => $saldo,
                'documento' => $documentos[$m->asiento_id] ?? null,
            ];
        }

        return response()->json([
            'cuenta' => ['codigo' => $cuenta->codigo, 'nombre' => $cuenta->nombre],
            'periodo' => ['desde' => $desde->format('d/m/Y'), 'hasta' => $hasta->format('d/m/Y')],
            'inicial' => round($inicial, 2),
            'final' => $saldo,
            'movimientos' => $filas,
        ]);
    }

    /**
     * Resuelve, para una colección de movimientos, el enlace al documento
     * fuente de cada asiento (factura, cobro, nota, recibo…). Cuando el asiento
     * no proviene de un documento de negocio mapeable (diario manual, migración)
     * el «fuente» es el propio asiento contable. Las consultas de tipo se hacen
     * en lote para evitar N+1. Devuelve [asiento_id => ['url','label']].
     *
     * @param  \Illuminate\Support\Collection<int,object>  $movimientos
     * @return array<int,array{url:string,label:string}>
     */
    private function resolverDocumentos($movimientos, int $companiaId): array
    {
        // IDs de documentos CxC/CxP/Compras presentes, para resolver su tipo en lote.
        $idsPorTabla = [];
        foreach ($movimientos as $m) {
            if (! empty($m->origen_tabla) && ! empty($m->origen_id)) {
                $idsPorTabla[$m->origen_tabla][$m->origen_id] = true;
            }
        }

        $tipoCxc = $this->mapaTipos('cxc_documentos', $idsPorTabla, $companiaId);
        $tipoCxp = $this->mapaTipos('cxp_documentos', $idsPorTabla, $companiaId);

        // compras_facturas no tiene vista propia: se enlaza a su documento CxP.
        $cxpDeCompra = [];
        if (! empty($idsPorTabla['compras_facturas'])) {
            $cxpDeCompra = DB::table('compras_facturas')
                ->where('compania_id', $companiaId)
                ->whereIn('id', array_keys($idsPorTabla['compras_facturas']))
                ->pluck('cxp_documento_id', 'id')
                ->all();
        }

        $rutaCxc = ['FACTURA' => 'admin.cxc.facturas.show', 'PAGO' => 'admin.cxc.cobros.show', 'NOTA_CREDITO' => 'admin.cxc.notas.show'];
        $rutaCxp = ['FACTURA' => 'admin.cxp.facturas.show', 'PAGO' => 'admin.cxp.pagos.show', 'NOTA_CREDITO' => 'admin.cxp.notas.show'];

        $url = static fn (string $nombre, $param): ?string => Route::has($nombre) ? route($nombre, $param) : null;

        $out = [];
        foreach ($movimientos as $m) {
            $doc = null;
            $tabla = $m->origen_tabla;
            $oid = $m->origen_id;

            if (! empty($tabla) && ! empty($oid)) {
                switch ($tabla) {
                    case 'ventas_facturas':
                        $doc = ['url' => $url('admin.ventas.facturas.show', $oid), 'label' => 'Factura de venta'];
                        break;
                    case 'ventas_recibos':
                        $doc = ['url' => $url('admin.ventas.recibos.show', $oid), 'label' => 'Recibo de cobro'];
                        break;
                    case 'cxc_documentos':
                        $tipo = $tipoCxc[$oid] ?? null;
                        $doc = ['url' => $url($rutaCxc[$tipo] ?? '', $oid), 'label' => 'CxC · '.$this->etiquetaTipo($tipo)];
                        break;
                    case 'cxp_documentos':
                        $tipo = $tipoCxp[$oid] ?? null;
                        $doc = ['url' => $url($rutaCxp[$tipo] ?? '', $oid), 'label' => 'CxP · '.$this->etiquetaTipo($tipo)];
                        break;
                    case 'compras_facturas':
                        $cxpId = $cxpDeCompra[$oid] ?? null;
                        if ($cxpId) {
                            $doc = ['url' => $url('admin.cxp.facturas.show', $cxpId), 'label' => 'Factura de compra'];
                        }
                        break;
                    case 'cgl_asientos':
                        $doc = ['url' => $url('admin.asientos.show', $oid), 'label' => 'Asiento contable'];
                        break;
                }
            }

            // Sin documento de negocio mapeable: el fuente es el propio asiento.
            if ($doc === null || empty($doc['url'])) {
                $doc = ['url' => $url('admin.asientos.show', $m->asiento_id), 'label' => 'Asiento contable'];
            }

            if (! empty($doc['url'])) {
                $out[$m->asiento_id] = $doc;
            }
        }

        return $out;
    }

    /**
     * Mapa [id => tipo_documento] para los ids de una tabla de documentos
     * presentes en $idsPorTabla, acotado por compañía. Una sola consulta.
     *
     * @param  array<string,array<int,bool>>  $idsPorTabla
     * @return array<int,string>
     */
    private function mapaTipos(string $tabla, array $idsPorTabla, int $companiaId): array
    {
        if (empty($idsPorTabla[$tabla])) {
            return [];
        }

        return DB::table($tabla)
            ->where('compania_id', $companiaId)
            ->whereIn('id', array_keys($idsPorTabla[$tabla]))
            ->pluck('tipo_documento', 'id')
            ->all();
    }

    private function etiquetaTipo(?string $tipo): string
    {
        return match ($tipo) {
            'FACTURA' => 'Factura',
            'PAGO' => 'Cobro/Pago',
            'NOTA_CREDITO' => 'Nota de crédito',
            default => 'Documento',
        };
    }

    /**
     * Recorre la jerarquía emitiendo filas (grupos, cuentas y subtotales «Suma»)
     * y devuelve el agregado de la rama. Las ramas sin movimiento se omiten.
     *
     * @param  array<int,array<string,mixed>>  $filas
     * @return array{inicial:float,debito:float,credito:float,corriente:float,final:float}
     */
    private function recorrer(object $cuenta, $hijos, $inicial, $movimiento, array &$filas): array
    {
        $hijosCuenta = ($hijos[$cuenta->id] ?? collect())->sortBy('codigo');

        // Cuenta de detalle (hoja): toma sus saldos.
        if ($hijosCuenta->isEmpty()) {
            $ini = round((float) ($inicial[$cuenta->id] ?? 0), 2);
            $mov = $movimiento[$cuenta->id] ?? null;
            $deb = round((float) ($mov->debito ?? 0), 2);
            $cre = round((float) ($mov->credito ?? 0), 2);
            $cor = round($deb - $cre, 2);
            $fin = round($ini + $cor, 2);

            $agg = ['inicial' => $ini, 'debito' => $deb, 'credito' => $cre, 'corriente' => $cor, 'final' => $fin];

            // Omite cuentas sin saldo ni movimiento.
            if (array_sum(array_map('abs', $agg)) < 0.01) {
                return $this->totalesVacios();
            }

            $filas[] = array_merge([
                'tipo' => 'cuenta',
                'id' => $cuenta->id,
                'nivel' => $cuenta->nivel,
                'codigo' => $cuenta->codigo,
                'nombre' => $cuenta->nombre,
            ], $agg);

            return $agg;
        }

        // Cuenta de grupo: emite encabezado, recorre hijos y cierra con «Suma».
        $cabecera = ['tipo' => 'grupo', 'nivel' => $cuenta->nivel, 'codigo' => $cuenta->codigo, 'nombre' => $cuenta->nombre];
        $filas[] = $cabecera;
        $posCabecera = array_key_last($filas);

        $agg = $this->totalesVacios();
        $algo = false;

        foreach ($hijosCuenta as $hijo) {
            $sub = $this->recorrer($hijo, $hijos, $inicial, $movimiento, $filas);

            if (array_sum(array_map('abs', $sub)) >= 0.01) {
                $algo = true;
            }
            foreach ($agg as $k => $v) {
                $agg[$k] = round($v + $sub[$k], 2);
            }
        }

        // Grupo sin movimiento: quita su encabezado y no aporta subtotal.
        if (! $algo) {
            unset($filas[$posCabecera]);

            return $this->totalesVacios();
        }

        $filas[] = array_merge([
            'tipo' => 'suma',
            'nivel' => $cuenta->nivel,
            'codigo' => '',
            'nombre' => 'Suma '.$cuenta->nombre,
        ], $agg);

        return $agg;
    }

    /** @return array{inicial:float,debito:float,credito:float,corriente:float,final:float} */
    private function totalesVacios(): array
    {
        return ['inicial' => 0.0, 'debito' => 0.0, 'credito' => 0.0, 'corriente' => 0.0, 'final' => 0.0];
    }
}
