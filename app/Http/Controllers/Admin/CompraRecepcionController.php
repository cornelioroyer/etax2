<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\CompraOrden;
use App\Models\CompraRecepcion;
use App\Models\CompraRecepcionDetalle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompraRecepcionController extends Controller
{
    use ConCompaniaActiva;

    /**
     * Registra una recepción de mercancía contra una orden de compra.
     * Recibe cantidades por línea; valida que no exceda lo pendiente y
     * recalcula el estado de recepción de la orden.
     */
    public function store(Request $request, CompraOrden $orden): RedirectResponse
    {
        abort_unless($request->user()->can('compras.gestionar'), 403);
        abort_unless($orden->compania_id === $this->companiaActivaId($request), 404);

        if (! $orden->esRecibible()) {
            return back()->withErrors(['recepcion' => 'La orden debe estar aprobada (y no completamente recibida) para registrar una recepción.']);
        }

        $data = $request->validate([
            'fecha'               => ['required', 'date'],
            'lineas'              => ['required', 'array', 'min:1'],
            'lineas.*.orden_detalle_id' => ['required', 'integer'],
            'lineas.*.cantidad'   => ['required', 'numeric', 'gte:0', 'max:999999999'],
        ]);

        $orden->load(['detalle', 'recepciones']);
        $detallePorId = $orden->detalle->keyBy('id');

        // Cantidad ya recibida por línea.
        $recibido = CompraRecepcionDetalle::query()
            ->whereIn('recepcion_id', $orden->recepciones->pluck('id'))
            ->selectRaw('orden_detalle_id, SUM(cantidad) AS recibido')
            ->groupBy('orden_detalle_id')
            ->pluck('recibido', 'orden_detalle_id');

        $aRecibir = [];

        foreach ($data['lineas'] as $linea) {
            $detId = (int) $linea['orden_detalle_id'];
            $cant  = round((float) $linea['cantidad'], 4);

            if ($cant <= 0) {
                continue;
            }

            $det = $detallePorId->get($detId);
            if (! $det) {
                throw ValidationException::withMessages(['lineas' => 'Una línea no pertenece a esta orden.']);
            }

            $pendiente = round((float) $det->cantidad - (float) ($recibido[$detId] ?? 0), 4);
            if ($cant - 0.0001 > $pendiente) {
                throw ValidationException::withMessages([
                    'lineas' => "La cantidad recibida de «{$det->descripcion}» excede lo pendiente ({$pendiente}).",
                ]);
            }

            $aRecibir[] = ['det' => $det, 'cantidad' => $cant];
        }

        if (empty($aRecibir)) {
            throw ValidationException::withMessages(['lineas' => 'Indica al menos una cantidad a recibir.']);
        }

        $recepcion = DB::transaction(function () use ($orden, $data, $aRecibir, $request) {
            $recepcion = CompraRecepcion::create([
                'compania_id'  => $orden->compania_id,
                'orden_id'     => $orden->id,
                'proveedor_id' => $orden->proveedor_id,
                'numero'       => CompraRecepcion::siguienteNumero($orden->compania_id),
                'fecha'        => $data['fecha'],
                'estado'       => CompraRecepcion::ESTADO_RECIBIDO,
                'created_by'   => $request->user()->email,
            ]);

            foreach ($aRecibir as $item) {
                CompraRecepcionDetalle::create([
                    'recepcion_id'     => $recepcion->id,
                    'orden_detalle_id' => $item['det']->id,
                    'descripcion'      => $item['det']->descripcion,
                    'cantidad'         => $item['cantidad'],
                    'costo'            => $item['det']->precio_unitario,
                    'created_by'       => $request->user()->email,
                ]);
            }

            $orden->refresh();
            $orden->refrescarEstadoRecepcion();

            return $recepcion;
        });

        return redirect()->route('admin.compras.ordenes.show', $orden)
            ->with('status', "Recepción {$recepcion->numero} registrada.");
    }
}
