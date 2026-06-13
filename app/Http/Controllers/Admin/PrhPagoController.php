<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\PrhCuota;
use App\Models\PrhEdificio;
use App\Models\PrhPago;
use App\Models\PrhTipoCuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PrhPagoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('prh.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $edificioId = $request->input('edificio_id');
        $periodo    = $request->input('periodo');

        $pagos = PrhPago::query()
            ->join('prh_cuotas', 'prh_cuotas.id', '=', 'prh_pagos.cuota_id')
            ->join('prh_unidades', 'prh_unidades.id', '=', 'prh_cuotas.unidad_id')
            ->join('prh_edificios', 'prh_edificios.id', '=', 'prh_unidades.edificio_id')
            ->where('prh_cuotas.compania_id', $companiaId)
            ->when($edificioId, fn ($q) => $q->where('prh_edificios.id', $edificioId))
            ->when($periodo, fn ($q) => $q->where('prh_cuotas.periodo', $periodo))
            ->select('prh_pagos.*')
            ->with(['cuota.unidad.edificio', 'cuota.unidad.propietario', 'cuota.tipoCuota'])
            ->orderByDesc('prh_pagos.fecha_pago')
            ->orderByDesc('prh_pagos.created_at')
            ->paginate(25)
            ->withQueryString();

        $edificios  = PrhEdificio::where('compania_id', $companiaId)->orderBy('nombre')->get();

        return view('admin.prh.pagos.index', compact('pagos', 'edificios', 'edificioId', 'periodo'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $cuotaId  = $request->input('cuota_id');
        $cuota    = $cuotaId ? PrhCuota::where('id', $cuotaId)->where('compania_id', $companiaId)
            ->with(['unidad.edificio', 'unidad.propietario', 'tipoCuota'])->first() : null;

        $edificios  = PrhEdificio::where('compania_id', $companiaId)->where('activo', true)->orderBy('nombre')->get();
        $tiposCuota = PrhTipoCuota::where('compania_id', $companiaId)->where('activo', true)->orderBy('nombre')->get();

        return view('admin.prh.pagos.create', compact('cuota', 'edificios', 'tiposCuota'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'cuota_id'   => ['required', 'integer'],
            'fecha_pago' => ['required', 'date'],
            'monto'      => ['required', 'numeric', 'gt:0'],
            'referencia' => ['nullable', 'string', 'max:150'],
            'forma_pago' => ['required', 'in:' . implode(',', PrhPago::FORMAS_PAGO)],
            'notas'      => ['nullable', 'string', 'max:500'],
        ]);

        $cuota = PrhCuota::where('id', $data['cuota_id'])
            ->where('compania_id', $companiaId)->firstOrFail();

        if ($cuota->estado === PrhCuota::ESTADO_ANULADO) {
            return back()->withErrors(['cuota_id' => 'No se puede registrar pago en una cuota anulada.']);
        }

        if ((float) $data['monto'] > $cuota->saldoPendiente()) {
            return back()->withErrors(['monto' => 'El monto supera el saldo pendiente de B/. ' . number_format($cuota->saldoPendiente(), 2) . '.']);
        }

        DB::transaction(function () use ($data, $cuota, $request) {
            PrhPago::create([
                ...$data,
                'created_by' => $request->user()->email,
            ]);

            $nuevoPagado = round((float) $cuota->monto_pagado + (float) $data['monto'], 2);
            $cuota->monto_pagado = $nuevoPagado;
            $cuota->recalcularEstado();
            $cuota->updated_by = $request->user()->email;
            $cuota->save();
        });

        return redirect()->route('admin.prh.cuotas.index', [
            'edificio_id' => $cuota->unidad->edificio_id ?? null,
            'periodo'     => $cuota->periodo,
        ])->with('status', 'Pago registrado: B/. ' . number_format((float) $data['monto'], 2) . '.');
    }

    public function destroy(Request $request, PrhPago $pago): RedirectResponse
    {
        abort_unless($request->user()->can('prh.gestionar'), 403);

        $cuota = $pago->cuota;
        abort_unless($cuota->compania_id === $this->companiaActivaId($request), 404);

        DB::transaction(function () use ($pago, $cuota, $request) {
            $cuota->monto_pagado = round((float) $cuota->monto_pagado - (float) $pago->monto, 2);
            $cuota->recalcularEstado();
            $cuota->updated_by = $request->user()->email;
            $cuota->save();

            $pago->delete();
        });

        return back()->with('status', 'Pago eliminado y cuota ajustada.');
    }
}
