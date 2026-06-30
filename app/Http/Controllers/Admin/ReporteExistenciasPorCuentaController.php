<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reporte de existencia por cuenta de mayor.
 *
 * Agrupa los productos por su cuenta de inventario (item.cuenta_inventario_id →
 * cgl_cuentas) y, dentro de cada cuenta, lista por artículo: código,
 * descripción, cantidad, costo unitario (promedio ponderado) y costo total
 * (cantidad × costo promedio). El subtotal de costo de cada cuenta debería
 * conciliar con el saldo de esa cuenta en el mayor (ver Cuadre de Auxiliares).
 *
 * Un producto puede tener existencias en varios almacenes con costo promedio
 * distinto; se consolidan en una sola línea por artículo y el costo unitario
 * mostrado es el ponderado (costo total / cantidad).
 *
 * Foto «a hoy»: las existencias no tienen dimensión de período. Solo lectura,
 * sin efecto contable. Aislado por compañía vía inv_existencias.compania_id
 * (defensa adicional con inv_almacenes.compania_id).
 */
class ReporteExistenciasPorCuentaController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    public function __invoke(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);
        $compania = Compania::find($companiaId);

        $incluirCeros = $request->boolean('incluir_ceros');
        $q = trim((string) $request->query('q', ''));

        $grupos = $this->construirGrupos($companiaId, $q, $incluirCeros);

        $totalCantidad = round(array_sum(array_column($grupos, 'totalCantidad')), 4);
        $totalCosto = round(array_sum(array_column($grupos, 'totalCosto')), 2);

        $datos = [
            'compania'      => $compania,
            'grupos'        => $grupos,
            'totalCantidad' => $totalCantidad,
            'totalCosto'    => $totalCosto,
            'filtros'       => [
                'incluir_ceros' => $incluirCeros,
                'q'             => $q,
            ],
            'generado'      => now(),
            'usuario'       => $request->user()->name ?: $request->user()->email,
        ];

        if ($export = $this->exportarReporte($request, 'admin.exports.existencias-por-cuenta', $datos,
            'existencias_por_cuenta_'.now()->format('Ymd'))) {
            return $export;
        }

        return view('admin.reportes.existencias-por-cuenta', $datos);
    }

    /**
     * Arma los grupos por cuenta de inventario, cada uno con sus líneas de
     * artículo consolidadas entre almacenes.
     *
     * @return array<int,array<string,mixed>>
     */
    private function construirGrupos(int $companiaId, string $q, bool $incluirCeros): array
    {
        $rows = DB::table('inv_existencias as e')
            ->join('inv_almacenes as al', 'al.id', '=', 'e.almacen_id')
            ->join('item_productos_servicios as it', 'it.id', '=', 'e.item_id')
            ->leftJoin('cgl_cuentas as c', 'c.id', '=', 'it.cuenta_inventario_id')
            ->where('e.compania_id', $companiaId)
            ->where('al.compania_id', $companiaId)
            ->when($q !== '', function ($w) use ($q) {
                $like = '%'.mb_strtolower($q).'%';
                $w->where(function ($s) use ($like) {
                    $s->whereRaw('LOWER(it.codigo) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(it.nombre) LIKE ?', [$like]);
                });
            })
            ->selectRaw('it.id as item_id, it.codigo, it.nombre,
                         it.cuenta_inventario_id as cuenta_id,
                         c.codigo as cuenta_codigo, c.nombre as cuenta_nombre,
                         e.cantidad, e.costo_promedio')
            ->get();

        // Consolidar por artículo (sumar cantidad y valor entre almacenes).
        $items = [];
        foreach ($rows as $r) {
            $id = (int) $r->item_id;
            if (! isset($items[$id])) {
                $items[$id] = [
                    'item_id'       => $id,
                    'codigo'        => $r->codigo,
                    'nombre'        => $r->nombre,
                    'cuenta_id'     => $r->cuenta_id !== null ? (int) $r->cuenta_id : null,
                    'cuenta_codigo' => $r->cuenta_codigo,
                    'cuenta_nombre' => $r->cuenta_nombre,
                    'cantidad'      => 0.0,
                    'costo'         => 0.0,
                ];
            }
            $items[$id]['cantidad'] += (float) $r->cantidad;
            $items[$id]['costo'] += (float) $r->cantidad * (float) $r->costo_promedio;
        }

        // Agrupar por cuenta de inventario.
        $grupos = [];
        foreach ($items as $it) {
            // Por defecto se omiten los ítems en cero; los negativos siempre se ven.
            if (! $incluirCeros && abs($it['cantidad']) < 0.00005) {
                continue;
            }

            $cuentaId = $it['cuenta_id'];
            $clave = $cuentaId ?? 0; // 0 = sin cuenta de inventario asignada
            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'cuenta_id'     => $cuentaId,
                    'cuenta_codigo' => $cuentaId ? ($it['cuenta_codigo'] ?? '') : '',
                    'cuenta_nombre' => $cuentaId
                        ? ($it['cuenta_nombre'] ?? ('Cuenta '.$cuentaId))
                        : 'Sin cuenta de inventario asignada',
                    'lineas'        => [],
                    'totalCantidad' => 0.0,
                    'totalCosto'    => 0.0,
                ];
            }

            $costo = round($it['costo'], 2);
            $costoUnit = abs($it['cantidad']) > 0.00005
                ? round($it['costo'] / $it['cantidad'], 4)
                : 0.0;

            $grupos[$clave]['lineas'][] = [
                'item_id'        => $it['item_id'],
                'codigo'         => $it['codigo'],
                'descripcion'    => $it['nombre'],
                'cantidad'       => round($it['cantidad'], 4),
                'costo_unitario' => $costoUnit,
                'costo'          => $costo,
            ];
            $grupos[$clave]['totalCantidad'] += $it['cantidad'];
            $grupos[$clave]['totalCosto'] += $costo;
        }

        // Ordenar líneas por código y redondear subtotales.
        foreach ($grupos as &$g) {
            usort($g['lineas'], fn ($a, $b) => strcmp((string) $a['codigo'], (string) $b['codigo']));
            $g['totalCantidad'] = round($g['totalCantidad'], 4);
            $g['totalCosto'] = round($g['totalCosto'], 2);
        }
        unset($g);

        // Ordenar grupos por código de cuenta; el grupo «sin cuenta» al final.
        uasort($grupos, function ($a, $b) {
            if ($a['cuenta_id'] === null) {
                return 1;
            }
            if ($b['cuenta_id'] === null) {
                return -1;
            }

            return strcmp((string) $a['cuenta_codigo'], (string) $b['cuenta_codigo']);
        });

        return array_values($grupos);
    }
}
