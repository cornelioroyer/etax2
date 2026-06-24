<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\InvAlmacen;
use App\Models\ItemProducto;
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
                'm.documento_id', 'm.almacen_id', 'm.asiento_id',
                'd.item_id', 'd.cantidad', 'd.costo_unitario',
            ]);

        // Estado corriente por (item|almacén): cantidad y costo promedio.
        $estado = [];
        $filas  = [];

        foreach ($movs as $m) {
            $key     = $m->item_id.'|'.$m->almacen_id;
            $st      = $estado[$key] ?? ['qty' => 0.0, 'costo' => 0.0];
            $cant    = (float) $m->cantidad;
            $costo   = (float) $m->costo_unitario;
            $entrada = 0.0;
            $salida  = 0.0;

            switch ($m->tipo_movimiento) {
                case 'ENTRADA':
                    $nuevaQty   = $st['qty'] + $cant;
                    $st['costo'] = $nuevaQty > 0
                        ? round(($st['qty'] * $st['costo'] + $cant * $costo) / $nuevaQty, 4)
                        : $costo;
                    $st['qty']  = $nuevaQty;
                    $entrada    = $cant;
                    break;

                case 'SALIDA':
                    $st['qty'] = max(0, $st['qty'] - $cant);
                    $salida    = $cant;
                    break;

                case 'AJUSTE':
                    $delta = round($cant - $st['qty'], 4);
                    if ($delta >= 0) {
                        $entrada = $delta;
                    } else {
                        $salida = abs($delta);
                    }
                    $st['qty']   = $cant;
                    $st['costo'] = $costo;
                    break;

                default:
                    // Defensivo: cualquier otro tipo se trata como entrada positiva.
                    $st['qty'] += $cant;
                    $entrada    = $cant;
                    break;
            }

            $estado[$key] = $st;

            $filas[] = (object) [
                'fecha'            => Carbon::parse($m->fecha),
                'item_id'          => (int) $m->item_id,
                'almacen_id'       => (int) $m->almacen_id,
                'tipo_movimiento'  => $m->tipo_movimiento,
                'documento_origen' => $m->documento_origen
                    ? $m->documento_origen.($m->documento_id ? ' #'.$m->documento_id : '')
                    : null,
                'entrada_cantidad' => $entrada,
                'salida_cantidad'  => $salida,
                'saldo_cantidad'   => $st['qty'],
                'costo_promedio'   => $st['costo'],
            ];
        }

        // Ventana visible: solo los movimientos dentro de [desde, hasta]. El
        // saldo ya viene arrastrado desde la historia previa.
        $desdeC = Carbon::parse($desde)->startOfDay();
        $visibles = array_values(array_filter(
            $filas,
            fn ($f) => $f->fecha->gte($desdeC)
        ));

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
}
