<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\PhCuota;
use App\Models\PhEdificio;
use App\Models\PhTipoCuota;
use App\Models\PhUnidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PhCuotaController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('ph.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $edificioId  = $request->input('edificio_id');
        $tipoCuotaId = $request->input('tipo_cuota_id');
        $periodo     = $request->input('periodo');
        $estado      = $request->input('estado');

        $cuotas = PhCuota::where('ph_cuotas.compania_id', $companiaId)
            ->join('ph_unidades', 'ph_unidades.id', '=', 'ph_cuotas.unidad_id')
            ->join('ph_edificios', 'ph_edificios.id', '=', 'ph_unidades.edificio_id')
            ->when($edificioId, fn ($q) => $q->where('ph_edificios.id', $edificioId))
            ->when($tipoCuotaId, fn ($q) => $q->where('ph_cuotas.tipo_cuota_id', $tipoCuotaId))
            ->when($periodo, fn ($q) => $q->where('ph_cuotas.periodo', $periodo))
            ->when($estado, fn ($q) => $q->where('ph_cuotas.estado', $estado))
            ->select('ph_cuotas.*')
            ->with(['unidad.edificio', 'unidad.propietario', 'tipoCuota'])
            ->orderBy('ph_cuotas.periodo', 'desc')
            ->orderBy('ph_edificios.nombre')
            ->orderBy('ph_unidades.numero')
            ->paginate(25)
            ->withQueryString();

        $edificios = PhEdificio::where('compania_id', $companiaId)->orderBy('nombre')->get();
        $tiposCuota = PhTipoCuota::where('compania_id', $companiaId)->orderBy('nombre')->get();

        return view('admin.ph.cuotas.index', compact('cuotas', 'edificios', 'tiposCuota', 'edificioId', 'tipoCuotaId', 'periodo', 'estado'));
    }

    public function generar(Request $request): View
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $edificios  = PhEdificio::where('compania_id', $companiaId)->where('activo', true)->orderBy('nombre')->get();
        $tiposCuota = PhTipoCuota::where('compania_id', $companiaId)->where('activo', true)->orderBy('nombre')->get();

        return view('admin.ph.cuotas.generar', compact('edificios', 'tiposCuota'));
    }

    public function procesarGenerar(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'edificio_id'      => ['required', 'integer'],
            'tipo_cuota_id'    => ['required', 'integer'],
            'periodo'          => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'fecha_emision'    => ['required', 'date'],
            'fecha_vencimiento' => ['required', 'date', 'after_or_equal:fecha_emision'],
            'usar_coeficiente' => ['boolean'],
        ]);

        $edificio = PhEdificio::where('id', $data['edificio_id'])
            ->where('compania_id', $companiaId)->firstOrFail();

        $tipoCuota = PhTipoCuota::where('id', $data['tipo_cuota_id'])
            ->where('compania_id', $companiaId)->firstOrFail();

        $unidades = PhUnidad::where('edificio_id', $edificio->id)
            ->where('activo', true)
            ->get();

        if ($unidades->isEmpty()) {
            return back()->withErrors(['generar' => 'El edificio no tiene unidades activas.']);
        }

        $usarCoeficiente = (bool) ($data['usar_coeficiente'] ?? false);
        $generadas = 0;
        $omitidas  = 0;

        DB::transaction(function () use ($data, $unidades, $tipoCuota, $companiaId, $usarCoeficiente, &$generadas, &$omitidas, $request) {
            foreach ($unidades as $unidad) {
                $yaExiste = PhCuota::where('unidad_id', $unidad->id)
                    ->where('tipo_cuota_id', $tipoCuota->id)
                    ->where('periodo', $data['periodo'])
                    ->exists();

                if ($yaExiste) {
                    $omitidas++;
                    continue;
                }

                $monto = $usarCoeficiente && $unidad->coeficiente > 0
                    ? round((float) $tipoCuota->monto_base * (float) $unidad->coeficiente, 2)
                    : (float) $tipoCuota->monto_base;

                PhCuota::create([
                    'compania_id'       => $companiaId,
                    'unidad_id'         => $unidad->id,
                    'tipo_cuota_id'     => $tipoCuota->id,
                    'periodo'           => $data['periodo'],
                    'fecha_emision'     => $data['fecha_emision'],
                    'fecha_vencimiento' => $data['fecha_vencimiento'],
                    'monto'             => $monto,
                    'monto_pagado'      => 0,
                    'concepto'          => "{$tipoCuota->nombre} — {$data['periodo']}",
                    'estado'            => PhCuota::ESTADO_PENDIENTE,
                    'created_by'        => $request->user()->email,
                ]);

                $generadas++;
            }
        });

        $msg = "Se generaron {$generadas} cuota(s).";
        if ($omitidas > 0) {
            $msg .= " Se omitieron {$omitidas} por ya existir.";
        }

        return redirect()->route('admin.ph.cuotas.index', [
            'edificio_id'   => $edificio->id,
            'tipo_cuota_id' => $tipoCuota->id,
            'periodo'       => $data['periodo'],
        ])->with('status', $msg);
    }

    public function anular(Request $request, PhCuota $cuota): RedirectResponse
    {
        abort_unless($request->user()->can('ph.gestionar'), 403);
        abort_unless($cuota->compania_id === $this->companiaActivaId($request), 404);

        if ($cuota->estado === PhCuota::ESTADO_ANULADO) {
            return back()->withErrors(['anular' => 'La cuota ya está anulada.']);
        }

        if ($cuota->monto_pagado > 0) {
            return back()->withErrors(['anular' => 'No se puede anular: la cuota tiene pagos registrados.']);
        }

        $cuota->update(['estado' => PhCuota::ESTADO_ANULADO, 'updated_by' => $request->user()->email]);

        return back()->with('status', 'Cuota anulada.');
    }
}
