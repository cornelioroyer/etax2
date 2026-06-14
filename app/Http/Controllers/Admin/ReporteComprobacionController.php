<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Balance de Comprobación (sumas y saldos) acumulado a una fecha de corte
 * (fin de mes), desde cgl_saldos (asientos POSTEADOS).
 *
 * Por cada cuenta muestra las sumas de débito y crédito acumuladas y el
 * saldo resultante (deudor si débito > crédito, acreedor en caso contrario).
 * Como toda la contabilidad es de partida doble, las columnas deben cuadrar:
 * Σ débitos = Σ créditos  y  Σ saldo deudor = Σ saldo acreedor.
 */
class ReporteComprobacionController extends Controller
{
    public function __invoke(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $periodos = DB::table('cgl_saldos as s')
            ->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
            ->where('s.compania_id', $companiaId)
            ->selectRaw('DISTINCT p.anio, p.mes')
            ->orderByDesc('p.anio')
            ->orderByDesc('p.mes')
            ->get();

        if ($periodos->isEmpty()) {
            return view('admin.reportes.balance-comprobacion', [
                'sinDatos' => true, 'periodos' => $periodos,
                'anio' => now()->year, 'mes' => now()->month, 'corte' => now(),
                'cuentas' => collect(),
                'totalDebito' => 0.0, 'totalCredito' => 0.0,
                'totalDeudor' => 0.0, 'totalAcreedor' => 0.0,
            ]);
        }

        $anio = (int) $request->input('anio', $periodos->first()->anio);
        $mes = (int) $request->input('mes', 0);

        if (! $periodos->contains(fn ($p) => $p->anio == $anio && $p->mes == $mes)) {
            $delAnio = $periodos->firstWhere('anio', $anio) ?? $periodos->first();
            $anio = (int) $delAnio->anio;
            $mes = (int) $delAnio->mes;
        }

        $corte = Carbon::create($anio, $mes, 1)->endOfMonth();

        $filas = DB::table('cgl_saldos as s')
            ->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
            ->join('cgl_cuentas as c', 'c.id', '=', 's.cuenta_id')
            ->where('s.compania_id', $companiaId)
            ->where(fn ($q) => $q->where('p.anio', '<', $anio)
                ->orWhere(fn ($q) => $q->where('p.anio', $anio)->where('p.mes', '<=', $mes)))
            ->groupBy('c.codigo', 'c.nombre')
            ->select([
                'c.codigo',
                'c.nombre',
                DB::raw('SUM(s.debito) as debito'),
                DB::raw('SUM(s.credito) as credito'),
            ])
            ->get();

        $cuentas = $filas
            ->map(function ($f) {
                $debito = round((float) $f->debito, 2);
                $credito = round((float) $f->credito, 2);
                $saldo = round($debito - $credito, 2);

                return [
                    'codigo' => $f->codigo,
                    'nombre' => $f->nombre,
                    'debito' => $debito,
                    'credito' => $credito,
                    'deudor' => $saldo > 0 ? $saldo : 0.0,
                    'acreedor' => $saldo < 0 ? -$saldo : 0.0,
                ];
            })
            ->filter(fn ($c) => abs($c['debito']) >= 0.01 || abs($c['credito']) >= 0.01)
            ->sortBy('codigo')
            ->values();

        return view('admin.reportes.balance-comprobacion', [
            'sinDatos' => false,
            'periodos' => $periodos,
            'anio' => $anio,
            'mes' => $mes,
            'corte' => $corte,
            'cuentas' => $cuentas,
            'totalDebito' => round((float) $cuentas->sum('debito'), 2),
            'totalCredito' => round((float) $cuentas->sum('credito'), 2),
            'totalDeudor' => round((float) $cuentas->sum('deudor'), 2),
            'totalAcreedor' => round((float) $cuentas->sum('acreedor'), 2),
        ]);
    }

    private function companiaActivaId(Request $request): int
    {
        $companiaId = session('compania_activa_id');

        abort_if(! $companiaId, 404, 'No hay compañía activa.');
        abort_unless(
            $request->user()->is_admin || $request->user()->companiasAccesibles()->contains('id', $companiaId),
            403
        );

        return (int) $companiaId;
    }
}
