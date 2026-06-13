<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\PhCuota;
use App\Models\PhEdificio;
use App\Models\PhPago;
use App\Models\PhTipoCuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PhPagoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('ph.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $edificioId = $request->input('edificio_id');
        $periodo    = $request->input('periodo');

        $pagos = PhPago::query()
            ->join('ph_cuotas', 'ph_cuotas.id', '=', 'ph_pagos.cuota_id')
            ->join('ph_unidades', 'ph_unidades.id', '=', 'ph_cuotas.unidad_id')
            ->join('ph_edificios', 'ph_edificios.id', '=', 'ph_unidades.edificio_id')
            ->where('ph_cuotas.compania_id', $companiaId)
            ->when($edificioId, fn ($q) => $q->where('ph_edificios.id', $edificioId))
            ->when($periodo, fn ($q) => $q->where('ph_cuotas.periodo', $periodo))
            ->select('ph_pagos.*')
            ->with(['cuota.unidad.edificio', 'cuota.unidad.propietario', 'cuota.tipoCuota'])
            ->orderByDesc('ph_pagos.fecha_pago')
            ->orderByDesc('ph_pagos.created_at')
            ->paginate(25)
            ->withQueryString();

        $edificios  = PhEdificio::where('compania_id', $companiaId)->orderBy('nombre')->get();

        return view('admin.ph.pagos.index', compact('pagos', 'edificios', 'edificioId', 'periodo'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $cuotaId  = $request->input('cuota_id');
        $cuota    = $cuotaId ? PhCuota::where('id', $cuotaId)->where('compania_id', $companiaId)
            ->with(['unidad.edificio', 'unidad.propietario', 'tipoCuota'])->first() : null;

        $edificios  = PhEdificio::where('compania_id', $companiaId)->where('activo', true)->orderBy('nombre')->get();
        $tiposCuota = PhTipoCuota::where('compania_id', $companiaId)->where('activo', true)->orderBy('nombre')->get();

        return view('admin.ph.pagos.create', compact('cuota', 'edificios', 'tiposCuota'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'cuota_id'   => ['required', 'integer'],
            'fecha_pago' => ['required', 'date'],
            'monto'      => ['required', 'numeric', 'gt:0'],
            'referencia' => ['nullable', 'string', 'max:150'],
            'forma_pago' => ['required', 'in:' . implode(',', PhPago::FORMAS_PAGO)],
            'notas'      => ['nullable', 'string', 'max:500'],
        ]);

        $cuota = PhCuota::where('id', $data['cuota_id'])
            ->where('compania_id', $companiaId)->firstOrFail();

        if ($cuota->estado === PhCuota::ESTADO_ANULADO) {
            return back()->withErrors(['cuota_id' => 'No se puede registrar pago en una cuota anulada.']);
        }

        if ((float) $data['monto'] > $cuota->saldoPendiente()) {
            return back()->withErrors(['monto' => 'El monto supera el saldo pendiente de B/. ' . number_format($cuota->saldoPendiente(), 2) . '.']);
        }

        DB::transaction(function () use ($data, $cuota, $request) {
            PhPago::create([
                ...$data,
                'created_by' => $request->user()->email,
            ]);

            $nuevoPagado = round((float) $cuota->monto_pagado + (float) $data['monto'], 2);
            $cuota->monto_pagado = $nuevoPagado;
            $cuota->recalcularEstado();
            $cuota->updated_by = $request->user()->email;
            $cuota->save();
        });

        return redirect()->route('admin.ph.cuotas.index', [
            'edificio_id' => $cuota->unidad->edificio_id ?? null,
            'periodo'     => $cuota->periodo,
        ])->with('status', 'Pago registrado: B/. ' . number_format((float) $data['monto'], 2) . '.');
    }

    public function destroy(Request $request, PhPago $pago): RedirectResponse
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);

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
