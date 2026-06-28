<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\InvAlmacen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reporte consolidado de existencias por almacén (foto «a hoy»).
 *
 * Matriz: un ítem por fila, un almacén por columna (cantidad), más el total
 * de cantidad, el costo promedio ponderado y el valor total por ítem. El valor
 * se calcula con el costo promedio de CADA almacén (que puede diferir), por lo
 * que el costo promedio mostrado del ítem es el ponderado (valor / cantidad).
 *
 * Solo lectura. Aislado por compañía vía inv_existencias.compania_id (defensa
 * adicional con inv_almacenes.compania_id). No tiene efecto contable.
 */
class InvExistenciasConsolidadoController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    public function __invoke(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);
        $compania = Compania::find($companiaId);

        $incluirCeros = $request->boolean('incluir_ceros');
        $q = trim((string) $request->query('q', ''));

        // Almacenes de la compañía: columnas de la matriz y opciones del filtro.
        $almacenesCompania = InvAlmacen::where('compania_id', $companiaId)
            ->orderBy('codigo')
            ->get();

        $almacenId = $request->integer('almacen_id') ?: null;
        if ($almacenId && ! $almacenesCompania->contains('id', $almacenId)) {
            $almacenId = null; // ignora un almacén de otra compañía
        }

        // Columnas = el almacén filtrado, o todos los de la compañía.
        $columnas = $almacenId
            ? $almacenesCompania->where('id', $almacenId)->values()
            : $almacenesCompania;

        $filas = $this->construirFilas($companiaId, $almacenId, $q, $incluirCeros);

        // Totales por almacén (solo sobre las columnas mostradas) y total general.
        $totalPorAlmacen = [];
        foreach ($columnas as $col) {
            $totalPorAlmacen[$col->id] = 0.0;
        }
        foreach ($filas as $f) {
            foreach ($f['porAlmacen'] as $aid => $cant) {
                if (array_key_exists($aid, $totalPorAlmacen)) {
                    $totalPorAlmacen[$aid] += $cant;
                }
            }
        }
        $totalValor = round(array_sum(array_column($filas, 'valor')), 2);

        $datos = [
            'compania'        => $compania,
            'columnas'        => $columnas,
            'almacenes'       => $almacenesCompania,
            'filas'           => $filas,
            'totalPorAlmacen' => $totalPorAlmacen,
            'totalValor'      => $totalValor,
            'filtros'         => [
                'almacen_id'    => $almacenId,
                'incluir_ceros' => $incluirCeros,
                'q'             => $q,
            ],
            'generado'        => now(),
            'usuario'         => $request->user()->name ?: $request->user()->email,
        ];

        if ($export = $this->exportarReporte($request, 'admin.exports.inventario-existencias', $datos,
            'existencias_consolidado_'.now()->format('Ymd'))) {
            return $export;
        }

        return view('admin.inventario.existencias-consolidado.index', $datos);
    }

    /**
     * Arma las filas agrupadas por ítem con su desglose por almacén.
     *
     * @return array<int,array<string,mixed>>
     */
    private function construirFilas(int $companiaId, ?int $almacenId, string $q, bool $incluirCeros): array
    {
        $rows = DB::table('inv_existencias as e')
            ->join('inv_almacenes as al', 'al.id', '=', 'e.almacen_id')
            ->join('item_productos_servicios as it', 'it.id', '=', 'e.item_id')
            ->leftJoin('item_unidades_medida as um', 'um.id', '=', 'it.unidad_medida_id')
            ->where('e.compania_id', $companiaId)
            ->where('al.compania_id', $companiaId)
            ->when($almacenId, fn ($w) => $w->where('e.almacen_id', $almacenId))
            ->when($q !== '', function ($w) use ($q) {
                $like = '%'.mb_strtolower($q).'%';
                $w->where(function ($s) use ($like) {
                    $s->whereRaw('LOWER(it.codigo) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(it.nombre) LIKE ?', [$like]);
                });
            })
            ->selectRaw('e.item_id, e.almacen_id, e.cantidad, e.costo_promedio,
                         it.codigo, it.nombre, it.tipo, um.codigo as um')
            ->get();

        $items = [];
        foreach ($rows as $r) {
            $id = (int) $r->item_id;
            if (! isset($items[$id])) {
                $items[$id] = [
                    'item_id'       => $id,
                    'codigo'        => $r->codigo,
                    'nombre'        => $r->nombre,
                    'tipo'          => $r->tipo,
                    'um'            => $r->um,
                    'porAlmacen'    => [],
                    'totalCantidad' => 0.0,
                    'valor'         => 0.0,
                ];
            }

            $cant = (float) $r->cantidad;
            $aid = (int) $r->almacen_id;
            $items[$id]['porAlmacen'][$aid] = ($items[$id]['porAlmacen'][$aid] ?? 0.0) + $cant;
            $items[$id]['totalCantidad'] += $cant;
            $items[$id]['valor'] += $cant * (float) $r->costo_promedio;
        }

        $filas = [];
        foreach ($items as $it) {
            // Por defecto se omiten los ítems en cero; los negativos siempre se ven.
            if (! $incluirCeros && abs($it['totalCantidad']) < 0.00005) {
                continue;
            }
            $it['valor'] = round($it['valor'], 2);
            $it['costoProm'] = abs($it['totalCantidad']) > 0.00005
                ? round($it['valor'] / $it['totalCantidad'], 4)
                : 0.0;
            $filas[] = $it;
        }

        usort($filas, fn ($a, $b) => strcmp((string) $a['codigo'], (string) $b['codigo']));

        return $filas;
    }
}
