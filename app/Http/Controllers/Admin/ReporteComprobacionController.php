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
 * Balance de Comprobación por rango de fechas (sumas y saldos).
 *
 * Se pide un período «desde / hasta» y, para cada cuenta de movimiento:
 *   - Balance Inicial : saldo acumulado (débito − crédito) de los asientos
 *                       posteados ANTES de la fecha «desde».
 *   - Débito / Crédito: lo registrado dentro del rango pedido.
 *   - Corriente       : débito − crédito del rango.
 *   - Balance Final   : Balance Inicial + Corriente.
 *
 * Los datos provienen de los asientos POSTEADOS (cgl_asientos +
 * cgl_asientos_detalle) para poder cortar por fecha exacta. Las cifras usan
 * la convención débito − crédito (los saldos acreedores se muestran entre
 * paréntesis) y se presentan según la jerarquía del plan de cuentas, con un
 * subtotal «Suma» por grupo. Por partida doble, los totales de Débito y
 * Crédito deben coincidir y los Balances Inicial/Final deben sumar cero.
 */
class ReporteComprobacionController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    public function __invoke(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $validado = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $hasta = ! empty($validado['hasta'])
            ? Carbon::parse($validado['hasta'])->startOfDay()
            : now()->endOfMonth()->startOfDay();
        $desde = ! empty($validado['desde'])
            ? Carbon::parse($validado['desde'])->startOfDay()
            : $hasta->copy()->startOfMonth();

        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta->copy(), $desde->copy()];
        }

        $compania = Compania::find($companiaId);

        $hayAsientos = DB::table('cgl_asientos')
            ->where('compania_id', $companiaId)
            ->where('estado', 'POSTEADO')
            ->exists();

        if (! $hayAsientos) {
            return view('admin.reportes.balance-comprobacion', [
                'sinDatos' => true,
                'desde' => $desde,
                'hasta' => $hasta,
                'filas' => collect(),
                'totales' => $this->totalesVacios(),
            ]);
        }

        // Balance Inicial: saldo (débito − crédito) acumulado ANTES de «desde».
        $inicial = DB::table('cgl_asientos_detalle as d')
            ->join('cgl_asientos as a', 'a.id', '=', 'd.asiento_id')
            ->where('a.compania_id', $companiaId)
            ->where('a.estado', 'POSTEADO')
            ->whereDate('a.fecha', '<', $desde->toDateString())
            ->groupBy('d.cuenta_id')
            ->selectRaw('d.cuenta_id, SUM(d.debito - d.credito) as inicial')
            ->pluck('inicial', 'cuenta_id');

        // Movimientos dentro del rango pedido.
        $movimiento = DB::table('cgl_asientos_detalle as d')
            ->join('cgl_asientos as a', 'a.id', '=', 'd.asiento_id')
            ->where('a.compania_id', $companiaId)
            ->where('a.estado', 'POSTEADO')
            ->whereDate('a.fecha', '>=', $desde->toDateString())
            ->whereDate('a.fecha', '<=', $hasta->toDateString())
            ->groupBy('d.cuenta_id')
            ->selectRaw('d.cuenta_id, SUM(d.debito) as debito, SUM(d.credito) as credito')
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
            'desde' => $desde,
            'hasta' => $hasta,
            'filas' => collect($filas),
            'totales' => $totales,
            'generado' => now(),
            'usuario' => $request->user()->name ?: $request->user()->email,
        ];

        if ($export = $this->exportarReporte($request, 'admin.exports.balance-comprobacion', $datos,
            'balance_comprobacion_'.$desde->format('Ymd').'_'.$hasta->format('Ymd'))) {
            return $export;
        }

        return view('admin.reportes.balance-comprobacion', array_merge($datos, [
            'sinDatos' => false,
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
