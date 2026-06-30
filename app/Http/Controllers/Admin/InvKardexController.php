<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\InvAlmacen;
use App\Models\ItemProducto;
use App\Services\RecalculadorCostosInventario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvKardexController extends Controller
{
    use ConCompaniaActiva;

    /**
     * El Kardex es una PROYECCIÓN del ledger real de inventario
     * (inv_movimientos + inv_movimientos_detalle), no una tabla propia: así hay
     * una sola fuente de verdad y nunca se desincroniza. Para cada par
     * (item, almacén) se arrastra el saldo y el costo promedio ponderado
     * replicando exactamente la regla del módulo de inventario:
     *   - ENTRADA  → suma cantidad, recalcula costo promedio ponderado.
     *   - SALIDA   → resta cantidad al costo promedio vigente (no lo cambia).
     *   - AJUSTE   → fija cantidad/costo absolutos; la columna entrada/salida
     *                muestra el delta contra el saldo anterior.
     * Las transferencias se registran como SALIDA (origen) + ENTRADA (destino),
     * así que quedan cubiertas por los casos de arriba. Se excluyen los
     * movimientos ANULADO.
     */
    public function index(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);
        $itemId     = $request->query('item_id');
        $almacenId  = $request->query('almacen_id');
        $desde      = $request->query('desde', now()->startOfMonth()->toDateString());
        $hasta      = $request->query('hasta', now()->toDateString());

        // Movimientos vigentes HASTA 'hasta' (se incluye la historia previa a
        // 'desde' para arrastrar el saldo inicial), filtrados por item/almacén.
        $movs = DB::table('inv_movimientos_detalle as d')
            ->join('inv_movimientos as m', 'm.id', '=', 'd.movimiento_id')
            ->where('m.compania_id', $companiaId)
            ->where('m.estado', '!=', 'ANULADO')
            ->when($almacenId, fn ($q) => $q->where('m.almacen_id', $almacenId))
            ->when($itemId, fn ($q) => $q->where('d.item_id', $itemId))
            ->whereDate('m.fecha', '<=', $hasta)
            ->orderBy('m.fecha')->orderBy('m.id')->orderBy('d.id')
            ->get([
                'm.id as mov_id', 'm.fecha', 'm.tipo_movimiento', 'm.documento_origen',
                'm.documento_id', 'm.almacen_id', 'm.asiento_id', 'm.descripcion',
                'd.item_id', 'd.cantidad', 'd.costo_unitario',
            ]);

        // Etiquetas amigables para el origen (la columna guarda el nombre técnico
        // de la tabla/proceso que generó el movimiento). Lo no mapeado se muestra
        // tal cual (defensivo ante orígenes futuros).
        $origenLabels = [
            'cxp_documentos' => 'Compra',
            'ventas_facturas' => 'Venta',
            'TRANSFERENCIA'  => 'Transferencia',
        ];

        // Estado corriente por (item|almacén): cantidad y VALOR total (saldo del
        // costo). El costo promedio NO se arrastra como unitario redondeado, sino
        // que se deriva en cada fila como saldo_costo / saldo_cantidad: así el
        // promedio siempre cuadra exactamente con el valor del inventario y no
        // acumula error de redondeo movimiento a movimiento.
        // Inicio del período visible: lo necesitamos dentro del bucle para
        // capturar el saldo acumulado JUSTO ANTES de 'desde' (saldo inicial).
        $desdeC = Carbon::parse($desde)->startOfDay();

        $estado       = [];
        $filas        = [];
        // Saldo arrastrado por (item|almacén) al cierre del último movimiento
        // anterior a 'desde'. Solo existe la clave si hubo movimientos previos.
        $saldoInicial = [];

        foreach ($movs as $m) {
            $key     = $m->item_id.'|'.$m->almacen_id;
            $st      = $estado[$key] ?? ['qty' => 0.0, 'valor' => 0.0];
            $cant    = (float) $m->cantidad;
            $costo   = (float) $m->costo_unitario;
            $entrada = 0.0;
            $salida  = 0.0;
            // Costo UNITARIO del movimiento: en la ENTRADA es el costo de compra;
            // en la SALIDA es el costo promedio vigente al momento (lo que se
            // descargó). Solo se llena el lado que aplica.
            $costoEntrada = 0.0;
            $costoSalida  = 0.0;

            switch ($m->tipo_movimiento) {
                case 'ENTRADA':
                    $st['qty']   += $cant;
                    $st['valor'] += $cant * $costo;
                    $entrada      = $cant;
                    $costoEntrada = $costo;
                    break;

                case 'SALIDA':
                    // La salida descarga al costo promedio vigente, por lo que el
                    // promedio no cambia: el valor se reduce proporcional a la
                    // cantidad que queda. Si se agota/sobre-vende, el valor cae a 0.
                    $promVigente = $st['qty'] > 0 ? $st['valor'] / $st['qty'] : 0.0;
                    $nuevaQty    = max(0, $st['qty'] - $cant);
                    $st['valor'] = round($promVigente * $nuevaQty, 4);
                    $st['qty']   = $nuevaQty;
                    $salida      = $cant;
                    $costoSalida = $costo;
                    break;

                case 'AJUSTE':
                    $delta = round($cant - $st['qty'], 4);
                    if ($delta >= 0) {
                        $entrada      = $delta;
                        $costoEntrada = $costo;
                    } else {
                        $salida      = abs($delta);
                        $costoSalida = $costo;
                    }
                    // El ajuste fija cantidad y costo absolutos.
                    $st['qty']   = $cant;
                    $st['valor'] = round($cant * $costo, 4);
                    break;

                default:
                    // Defensivo: cualquier otro tipo se trata como entrada positiva.
                    $st['qty']   += $cant;
                    $st['valor'] += $cant * $costo;
                    $entrada      = $cant;
                    $costoEntrada = $costo;
                    break;
            }

            $estado[$key] = $st;

            // Costo promedio derivado del saldo del costo (regla solicitada:
            // costo_promedio = saldo_costo / saldo_cantidad).
            $costoPromedio = $st['qty'] != 0.0 ? round($st['valor'] / $st['qty'], 4) : 0.0;

            $fechaMov = Carbon::parse($m->fecha);

            // Si el movimiento es anterior al período visible, su estado resultante
            // es candidato a saldo inicial (la última iteración previa a 'desde'
            // deja el valor correcto, porque los movimientos vienen ordenados).
            if ($fechaMov->lt($desdeC)) {
                $saldoInicial[$key] = ['qty' => $st['qty'], 'valor' => $st['valor']];
                continue; // no se lista; solo arrastra saldo
            }

            $filas[] = (object) [
                'es_inicial'       => false,
                'fecha'            => $fechaMov,
                'item_id'          => (int) $m->item_id,
                'almacen_id'       => (int) $m->almacen_id,
                'tipo_movimiento'  => $m->tipo_movimiento,
                'documento_origen' => $m->documento_origen
                    ? ($origenLabels[$m->documento_origen] ?? $m->documento_origen)
                        .($m->documento_id ? ' #'.$m->documento_id : '')
                    : null,
                'descripcion'      => $m->descripcion,
                'entrada_cantidad' => $entrada,
                'costo_entrada'    => $costoEntrada,
                'salida_cantidad'  => $salida,
                'costo_salida'     => $costoSalida,
                'saldo_cantidad'   => $st['qty'],
                'saldo_costo'      => round($st['valor'], 4),
                'costo_promedio'   => $costoPromedio,
            ];
        }

        // $filas ya contiene solo los movimientos dentro de [desde, hasta]
        // (los anteriores solo arrastraron saldo, ver bucle).
        $visibles = $filas;

        // Filas de SALDO INICIAL: una por (item|almacén) con movimientos previos
        // a 'desde'. Se muestran cuando hay un ítem filtrado (drill-down típico) o
        // cuando ese par también tiene movimientos visibles en el período, para no
        // ensuciar el listado global con ítems inactivos en el rango.
        $keysVisibles = [];
        foreach ($visibles as $f) {
            $keysVisibles[$f->item_id.'|'.$f->almacen_id] = true;
        }

        $iniciales = [];
        foreach ($saldoInicial as $key => $si) {
            if ($itemId === null && ! isset($keysVisibles[$key])) {
                continue;
            }
            [$iid, $aid] = array_map('intval', explode('|', $key));
            $prom = $si['qty'] != 0.0 ? round($si['valor'] / $si['qty'], 4) : 0.0;
            $iniciales[] = (object) [
                'es_inicial'       => true,
                'fecha'            => $desdeC->copy(),
                'item_id'          => $iid,
                'almacen_id'       => $aid,
                'tipo_movimiento'  => 'SALDO INICIAL',
                'documento_origen' => null,
                'descripcion'      => 'Saldo arrastrado al '.$desdeC->format('d/m/Y'),
                'entrada_cantidad' => 0.0,
                'costo_entrada'    => 0.0,
                'salida_cantidad'  => 0.0,
                'costo_salida'     => 0.0,
                'saldo_cantidad'   => $si['qty'],
                'saldo_costo'      => round($si['valor'], 4),
                'costo_promedio'   => $prom,
            ];
        }
        // Ordenadas por ítem/almacén y antepuestas a los movimientos del período.
        usort($iniciales, fn ($a, $b) => [$a->item_id, $a->almacen_id] <=> [$b->item_id, $b->almacen_id]);
        $visibles = array_merge($iniciales, $visibles);

        // Relaciones para la vista (incluye ítems/almacenes inactivos por si el
        // movimiento histórico los referencia).
        $itemIds    = array_unique(array_map(fn ($f) => $f->item_id, $visibles));
        $almacenIds = array_unique(array_map(fn ($f) => $f->almacen_id, $visibles));
        $itemsMap    = ItemProducto::whereIn('id', $itemIds)->get(['id', 'codigo', 'nombre'])->keyBy('id');
        $almacenesMap = InvAlmacen::whereIn('id', $almacenIds)->get(['id', 'nombre'])->keyBy('id');

        foreach ($visibles as $f) {
            $f->item    = $itemsMap->get($f->item_id);
            $f->almacen = $almacenesMap->get($f->almacen_id);
        }

        // Paginación manual sobre la colección derivada.
        $perPage = 50;
        $page    = LengthAwarePaginator::resolveCurrentPage();
        $slice   = array_slice($visibles, ($page - 1) * $perPage, $perPage);
        $kardex  = new LengthAwarePaginator(
            $slice, count($visibles), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $items     = ItemProducto::where('compania_id', $companiaId)->where('activo', true)->orderBy('codigo')->get();
        $almacenes = InvAlmacen::where('compania_id', $companiaId)->where('activo', true)->orderBy('codigo')->get();

        return view('admin.inventario.kardex.index', compact('kardex', 'items', 'almacenes', 'itemId', 'almacenId', 'desde', 'hasta'));
    }

    /**
     * Previsualiza (SOLO LECTURA) el recálculo de costos por promedio ponderado en
     * orden de fecha para el filtro vigente (ítem/almacén). Devuelve el plan: qué
     * salidas se corregirían, el estado final de existencias y el asiento de ajuste
     * que se postearía. No muta nada.
     */
    public function recalcularPreview(Request $request, RecalculadorCostosInventario $recalc): JsonResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $itemId     = $request->integer('item_id') ?: null;
        $almacenId  = $request->integer('almacen_id') ?: null;

        $plan = $recalc->analizar($companiaId, $itemId, $almacenId);

        return response()->json($this->resumenPlan($plan, $companiaId, $request->user(), $recalc));
    }

    /**
     * Aplica el recálculo: corrige el costo grabado de las salidas, deja las
     * existencias en su estado correcto y postea UN asiento de ajuste por la
     * diferencia (sin tocar los asientos originales). Idempotente.
     */
    public function recalcularAplicar(Request $request, RecalculadorCostosInventario $recalc): JsonResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $itemId     = $request->integer('item_id') ?: null;
        $almacenId  = $request->integer('almacen_id') ?: null;
        $usuario    = $request->user();

        $plan = $recalc->analizar($companiaId, $itemId, $almacenId);
        $noReconciliables = $this->resumenNoReconciliables($plan);
        if ($plan['sinCambios']) {
            return response()->json([
                'sinCambios'       => true,
                'mensaje'          => 'Los costos ya están correctos. No había nada que recalcular.',
                'noReconciliables' => $noReconciliables,
            ]);
        }

        $fecha   = $recalc->fechaAjuste($companiaId, $plan, null, $usuario);
        $asiento = DB::transaction(fn () => $recalc->aplicar($companiaId, $plan, $fecha, $usuario));

        $mensaje = $asiento
            ? "Recalculado. Asiento de ajuste {$asiento->numero} posteado el {$fecha}."
            : 'Recalculado: costos y existencias corregidos (sin asiento, neto cero).';
        if (! empty($noReconciliables)) {
            $mensaje .= ' '.count($noReconciliables).' ítem(s) con historial incompleto no se tocaron (revisar manualmente).';
        }

        return response()->json([
            'sinCambios'       => false,
            'corregidas'       => count($plan['cambios']),
            'asiento'          => $asiento ? ['numero' => $asiento->numero, 'fecha' => $fecha] : null,
            'mensaje'          => $mensaje,
            'noReconciliables' => $noReconciliables,
        ]);
    }

    /**
     * Arma un resumen JSON-amigable del plan de recálculo para la previsualización.
     * Limita las existencias mostradas a los ítems que efectivamente se corrigen.
     */
    private function resumenPlan(array $plan, int $companiaId, $usuario, RecalculadorCostosInventario $recalc): array
    {
        $noReconciliables = $this->resumenNoReconciliables($plan);

        if ($plan['sinCambios']) {
            return [
                'sinCambios'       => true,
                'mensaje'          => 'Los costos ya están correctos. No hay nada que recalcular.',
                'noReconciliables' => $noReconciliables,
            ];
        }

        $itemIds = array_values(array_unique(array_map(fn ($c) => $c->item_id, $plan['cambios'])));
        $nombres = ItemProducto::whereIn('id', $itemIds)->pluck('codigo', 'id');
        $fecha   = $recalc->fechaAjuste($companiaId, $plan, null, $usuario);

        $existencias = array_values(array_filter(
            $plan['existencias'],
            fn ($e) => in_array($e['item_id'], $itemIds, true),
        ));

        return [
            'sinCambios' => false,
            'fecha'      => $fecha,
            'neto'       => round(array_sum($plan['netoPorItem']), 2),
            'cambios'    => array_map(fn ($c) => [
                'fecha'       => substr((string) $c->fecha, 0, 10),
                'documento'   => $c->doc,
                'item'        => $nombres[$c->item_id] ?? (string) $c->item_id,
                'cantidad'    => (float) $c->cantidad,
                'costo_viejo' => (float) $c->costo_viejo,
                'costo_nuevo' => (float) $c->costo_nuevo,
                'delta'       => (float) $c->delta,
            ], array_values($plan['cambios'])),
            'existencias' => array_map(fn ($e) => [
                'item'        => $nombres[$e['item_id']] ?? (string) $e['item_id'],
                'cantidad'    => $e['cantidad'],
                'prom_actual' => $e['costo_promedio_actual'],
                'prom_nuevo'  => $e['costo_promedio'],
            ], $existencias),
            'asiento'    => array_map(fn ($l) => [
                'descripcion' => $l['descripcion'],
                'debito'      => (float) $l['debito'],
                'credito'     => (float) $l['credito'],
            ], array_values($plan['ajusteLineas'])),
            'noReconciliables' => $noReconciliables,
        ];
    }

    /**
     * Resume, con código de ítem legible, los pares (item, almacén) que el
     * recálculo no pudo verificar automáticamente (saldo sin AJUSTE que lo
     * respalde) — ver RecalculadorCostosInventario::analizar().
     */
    private function resumenNoReconciliables(array $plan): array
    {
        $itemIds = array_column($plan['noReconciliables'], 'item_id');
        $nombres = ItemProducto::whereIn('id', $itemIds)->pluck('codigo', 'id');

        return array_map(fn ($n) => [
            'item'               => $nombres[$n['item_id']] ?? (string) $n['item_id'],
            'cantidad_actual'    => $n['cantidad_actual'],
            'cantidad_calculada' => $n['cantidad_calculada'],
        ], $plan['noReconciliables']);
    }
}
