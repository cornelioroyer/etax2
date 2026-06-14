<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Balance de Comprobación con formato de sumas y saldos por período.
 *
 * Para el mes seleccionado, cada cuenta de movimiento muestra:
 *   - Balance Inicial : saldo acumulado (débito − crédito) ANTES del período.
 *   - Débito / Crédito: movimientos del período.
 *   - Corriente       : débito − crédito del período.
 *   - Balance Final   : Balance Inicial + Corriente.
 *
 * Las cifras usan la convención débito − crédito (los saldos acreedores se
 * muestran entre paréntesis). Las cuentas se presentan según la jerarquía del
 * plan de cuentas (grupos de nivel 1 y 2) con un subtotal «Suma» por grupo.
 * Por partida doble, los totales generales de Débito y Crédito deben coincidir
 * y los Balances Inicial/Final deben sumar cero.
 */
class ReporteComprobacionController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    public function __invoke(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $periodos = DB::table('cgl_periodos')
            ->where('compania_id', $companiaId)
            ->selectRaw('DISTINCT anio, mes')
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->get();

        $compania = Compania::find($companiaId);

        if ($periodos->isEmpty()) {
            return view('admin.reportes.balance-comprobacion', [
                'sinDatos' => true,
                'periodos' => $periodos,
                'anio' => now()->year,
                'mes' => now()->month,
                'corte' => now(),
                'filas' => collect(),
                'totales' => $this->totalesVacios(),
            ]);
        }

        $anio = (int) $request->input('anio', $periodos->first()->anio);
        $mes = (int) $request->input('mes', $periodos->firstWhere('anio', $anio)->mes ?? $periodos->first()->mes);

        if (! $periodos->contains(fn ($p) => $p->anio == $anio && $p->mes == $mes)) {
            $delAnio = $periodos->firstWhere('anio', $anio) ?? $periodos->first();
            $anio = (int) $delAnio->anio;
            $mes = (int) $delAnio->mes;
        }

        $corte = Carbon::create($anio, $mes, 1)->endOfMonth();

        // Saldo acumulado (débito − crédito) ANTES del período → Balance Inicial.
        $inicial = DB::table('cgl_saldos as s')
            ->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
            ->where('s.compania_id', $companiaId)
            ->where(fn ($q) => $q->where('p.anio', '<', $anio)
                ->orWhere(fn ($q) => $q->where('p.anio', $anio)->where('p.mes', '<', $mes)))
            ->groupBy('s.cuenta_id')
            ->selectRaw('s.cuenta_id, SUM(s.debito - s.credito) as inicial')
            ->pluck('inicial', 'cuenta_id');

        // Movimientos del período seleccionado.
        $movimiento = DB::table('cgl_saldos as s')
            ->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
            ->where('s.compania_id', $companiaId)
            ->where('p.anio', $anio)
            ->where('p.mes', $mes)
            ->groupBy('s.cuenta_id')
            ->selectRaw('s.cuenta_id, SUM(s.debito) as debito, SUM(s.credito) as credito')
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
            'corte' => $corte,
            'filas' => collect($filas),
            'totales' => $totales,
            'generado' => now(),
        ];

        if ($export = $this->exportarReporte($request, 'admin.exports.balance-comprobacion', $datos,
            'balance_comprobacion_'.$corte->format('Y-m'))) {
            return $export;
        }

        return view('admin.reportes.balance-comprobacion', array_merge($datos, [
            'sinDatos' => false,
            'periodos' => $periodos,
            'anio' => $anio,
            'mes' => $mes,
        ]));
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
