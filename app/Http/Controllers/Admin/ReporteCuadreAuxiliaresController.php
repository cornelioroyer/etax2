<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\CuentaDefault;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuadre de auxiliares ↔ mayor (a la fecha actual).
 *
 * Compara el saldo de cada subdiario (auxiliar) contra el saldo de su cuenta
 * de control en el mayor, para detectar descuadres (típicamente asientos
 * manuales posteados directo contra una cuenta de control):
 *
 *   - CxC        : SUM(cxc_documentos.saldo) (no anulados) ↔ cuenta default CXC
 *   - CxP        : SUM(cxp_documentos.saldo) (no anulados/borradores) ↔ cuenta default CXP
 *   - Inventario : SUM(existencias.cantidad × costo_promedio) por cuenta de
 *                  inventario del producto ↔ saldo de esa cuenta en el mayor
 *
 * El auxiliar es una foto «a hoy» (los saldos de documentos y las existencias
 * no tienen dimensión de período), así que el mayor se toma como saldo
 * acumulado de TODOS los asientos posteados (débito − crédito). Read-only.
 */
class ReporteCuadreAuxiliaresController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    /** Tolerancia para considerar una diferencia como «cuadrado». */
    private const TOLERANCIA = 0.01;

    public function __invoke(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);
        $compania = Compania::find($companiaId);

        $secciones = [
            $this->seccionCxc($companiaId),
            $this->seccionCxp($companiaId),
            $this->seccionInventario($companiaId),
        ];

        $datos = [
            'compania' => $compania,
            'secciones' => $secciones,
            'tolerancia' => self::TOLERANCIA,
            'generado' => now(),
            'usuario' => $request->user()->name ?: $request->user()->email,
        ];

        if ($export = $this->exportarReporte($request, 'admin.exports.cuadre-auxiliares', $datos,
            'cuadre_auxiliares_'.now()->format('Ymd'))) {
            return $export;
        }

        return view('admin.reportes.cuadre-auxiliares', $datos);
    }

    /** Cuentas por Cobrar: auxiliar = saldos de documentos vivos. */
    private function seccionCxc(int $companiaId): array
    {
        $cuentaId = CuentaDefault::idPara($companiaId, 'CXC');

        $auxiliar = (float) DB::table('cxc_documentos')
            ->where('compania_id', $companiaId)
            ->where('estado', '!=', 'ANULADO')
            ->sum('saldo');

        $mayor = $cuentaId ? $this->saldoMayorNeto($companiaId, [$cuentaId], 'DEBITO') : 0.0;

        return $this->armarSeccion('Cuentas por Cobrar', $cuentaId, $auxiliar, $mayor, [], $companiaId);
    }

    /** Cuentas por Pagar: excluye BORRADOR (sin asiento) además de ANULADO. */
    private function seccionCxp(int $companiaId): array
    {
        $cuentaId = CuentaDefault::idPara($companiaId, 'CXP');

        $auxiliar = (float) DB::table('cxp_documentos')
            ->where('compania_id', $companiaId)
            ->whereNotIn('estado', ['ANULADO', 'BORRADOR'])
            ->sum('saldo');

        $mayor = $cuentaId ? $this->saldoMayorNeto($companiaId, [$cuentaId], 'CREDITO') : 0.0;

        return $this->armarSeccion('Cuentas por Pagar', $cuentaId, $auxiliar, $mayor, [], $companiaId);
    }

    /**
     * Inventario: auxiliar = valor de existencias agrupado por la cuenta de
     * inventario del producto. Puede haber varias cuentas de inventario.
     */
    private function seccionInventario(int $companiaId): array
    {
        // Valor de existencias por cuenta de inventario del producto.
        $porCuenta = DB::table('inv_existencias as e')
            ->join('inv_almacenes as al', 'al.id', '=', 'e.almacen_id')
            ->join('item_productos_servicios as it', 'it.id', '=', 'e.item_id')
            ->where('al.compania_id', $companiaId)
            ->whereNotNull('it.cuenta_inventario_id')
            ->groupBy('it.cuenta_inventario_id')
            ->selectRaw('it.cuenta_inventario_id as cuenta_id, SUM(e.cantidad * e.costo_promedio) as valor')
            ->pluck('valor', 'cuenta_id');

        $cuentaIds = $porCuenta->keys()->map(fn ($id) => (int) $id)->all();

        $auxiliar = round((float) $porCuenta->sum(), 2);
        $mayor = $cuentaIds ? $this->saldoMayorNeto($companiaId, $cuentaIds, 'DEBITO') : 0.0;

        // Desglose por cuenta (auxiliar vs mayor de cada cuenta de inventario).
        $detalle = [];
        if ($cuentaIds) {
            $nombres = DB::table('cgl_cuentas')
                ->where('compania_id', $companiaId)
                ->whereIn('id', $cuentaIds)
                ->pluck('nombre', 'id');
            $codigos = DB::table('cgl_cuentas')
                ->where('compania_id', $companiaId)
                ->whereIn('id', $cuentaIds)
                ->pluck('codigo', 'id');
            $mayorPorCuenta = $this->saldoMayorPorCuenta($companiaId, $cuentaIds);

            foreach ($cuentaIds as $id) {
                $aux = round((float) ($porCuenta[$id] ?? 0), 2);
                $may = round((float) ($mayorPorCuenta[$id] ?? 0), 2);
                $detalle[] = [
                    'codigo' => $codigos[$id] ?? '',
                    'nombre' => $nombres[$id] ?? ('Cuenta '.$id),
                    'auxiliar' => $aux,
                    'mayor' => $may,
                    'diferencia' => round($aux - $may, 2),
                ];
            }
        }

        return $this->armarSeccion('Inventario', $cuentaIds ? -1 : null, $auxiliar, $mayor, $detalle, $companiaId);
    }

    /**
     * Saldo neto de un conjunto de cuentas en el mayor, en su naturaleza
     * (DEBITO → débito−crédito; CREDITO → crédito−débito), sobre todos los
     * asientos posteados.
     */
    private function saldoMayorNeto(int $companiaId, array $cuentaIds, string $naturaleza): float
    {
        $expr = $naturaleza === 'CREDITO' ? 'd.credito - d.debito' : 'd.debito - d.credito';

        $valor = (float) DB::table('cgl_asientos_detalle as d')
            ->join('cgl_asientos as a', 'a.id', '=', 'd.asiento_id')
            ->where('a.compania_id', $companiaId)
            ->where('a.estado', 'POSTEADO')
            ->whereIn('d.cuenta_id', $cuentaIds)
            ->sum(DB::raw($expr));

        return round($valor, 2);
    }

    /** Saldo (débito − crédito) por cuenta. */
    private function saldoMayorPorCuenta(int $companiaId, array $cuentaIds): array
    {
        return DB::table('cgl_asientos_detalle as d')
            ->join('cgl_asientos as a', 'a.id', '=', 'd.asiento_id')
            ->where('a.compania_id', $companiaId)
            ->where('a.estado', 'POSTEADO')
            ->whereIn('d.cuenta_id', $cuentaIds)
            ->groupBy('d.cuenta_id')
            ->selectRaw('d.cuenta_id, SUM(d.debito - d.credito) as saldo')
            ->pluck('saldo', 'cuenta_id')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();
    }

    /**
     * @param  int|null  $cuentaId  id de la cuenta de control, -1 si son varias
     *                              (inventario), null si no está configurada.
     * @param  array<int,array<string,mixed>>  $detalle
     */
    private function armarSeccion(string $titulo, ?int $cuentaId, float $auxiliar, float $mayor, array $detalle, int $companiaId): array
    {
        $auxiliar = round($auxiliar, 2);
        $mayor = round($mayor, 2);
        $diferencia = round($auxiliar - $mayor, 2);

        $cuenta = null;
        if ($cuentaId !== null && $cuentaId > 0) {
            $cuenta = DB::table('cgl_cuentas')
                ->where('compania_id', $companiaId)
                ->where('id', $cuentaId)
                ->first(['codigo', 'nombre']);
        }

        return [
            'titulo' => $titulo,
            'sin_cuenta' => $cuentaId === null,
            'cuenta' => $cuenta,
            'varias_cuentas' => $cuentaId === -1,
            'auxiliar' => $auxiliar,
            'mayor' => $mayor,
            'diferencia' => $diferencia,
            'cuadra' => abs($diferencia) < self::TOLERANCIA,
            'detalle' => $detalle,
        ];
    }
}
