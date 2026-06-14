<?php

namespace App\Services;

use App\Models\Asiento;
use App\Models\AsientoDetalle;
use App\Models\CuentaDefault;
use App\Models\Diario;
use App\Models\PeriodoContable;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cierre del ejercicio contable: genera el asiento que salda las cuentas de
 * resultado (Ingreso / Costo / Gasto) contra la cuenta de patrimonio
 * configurada como UTILIDADES_RETENIDAS, dejando la utilidad/pérdida del
 * período en el patrimonio.
 *
 * El asiento se postea en el período de ajuste (mes 13) del año, de modo que
 * los reportes operativos (Estado de Resultado por mes 1..12) no lo incluyan.
 */
class CierreAnual
{
    public const ORIGEN = 'CIERRE_ANUAL';

    /**
     * Resultado del ejercicio y líneas del asiento de cierre, SIN postear.
     *
     * @return array{lineas: array<int, array<string, mixed>>, ingresos: float,
     *               costos: float, gastos: float, utilidad: float,
     *               cierre: array{cuenta_id: ?int, debito: float, credito: float},
     *               total_debito: float, total_credito: float}
     */
    public function previsualizar(int $companiaId, int $anio): array
    {
        $filas = DB::table('cgl_saldos as s')
            ->join('cgl_periodos as p', 'p.id', '=', 's.periodo_id')
            ->join('cgl_cuentas as c', 'c.id', '=', 's.cuenta_id')
            ->join('cgl_tipos_cuenta as t', 't.id', '=', 'c.tipo_cuenta_id')
            ->where('s.compania_id', $companiaId)
            ->where('p.anio', $anio)
            ->where('p.mes', '<=', 12)
            ->whereIn('t.codigo', ['INGRESO', 'COSTO', 'GASTO'])
            ->groupBy('c.id', 'c.codigo', 'c.nombre', 't.codigo')
            ->selectRaw('c.id AS cuenta_id, c.codigo, c.nombre, t.codigo AS tipo,
                         SUM(s.debito) AS deb, SUM(s.credito) AS cred')
            ->orderBy('c.codigo')
            ->get();

        $lineas = [];
        $ingresos = 0.0;
        $costos = 0.0;
        $gastos = 0.0;

        foreach ($filas as $f) {
            $net = round((float) $f->deb - (float) $f->cred, 2); // saldo deudor
            if (abs($net) < 0.005) {
                continue;
            }

            // Reversa: saldo deudor (net>0) se acredita; saldo acreedor se debita.
            $lineas[] = [
                'cuenta_id' => (int) $f->cuenta_id,
                'codigo'    => $f->codigo,
                'nombre'    => $f->nombre,
                'tipo'      => $f->tipo,
                'debito'    => $net < 0 ? round(-$net, 2) : 0.0,
                'credito'   => $net > 0 ? round($net, 2) : 0.0,
            ];

            match ($f->tipo) {
                'INGRESO' => $ingresos += -$net, // ingreso = saldo acreedor
                'COSTO'   => $costos += $net,
                default   => $gastos += $net,
            };
        }

        $utilidad = round($ingresos - $costos - $gastos, 2);

        $totalDebRev = round(array_sum(array_column($lineas, 'debito')), 2);
        $totalCredRev = round(array_sum(array_column($lineas, 'credito')), 2);
        $diff = round($totalDebRev - $totalCredRev, 2); // = utilidad

        $cierre = [
            'cuenta_id' => CuentaDefault::idPara($companiaId, 'UTILIDADES_RETENIDAS'),
            'debito'    => $diff < 0 ? round(-$diff, 2) : 0.0,
            'credito'   => $diff > 0 ? round($diff, 2) : 0.0,
        ];

        return [
            'lineas'        => $lineas,
            'ingresos'      => round($ingresos, 2),
            'costos'        => round($costos, 2),
            'gastos'        => round($gastos, 2),
            'utilidad'      => $utilidad,
            'cierre'        => $cierre,
            'total_debito'  => round($totalDebRev + $cierre['debito'], 2),
            'total_credito' => round($totalCredRev + $cierre['credito'], 2),
        ];
    }

    /** Asiento de cierre posteado del año, o null si no existe / fue anulado. */
    public function asientoDe(int $companiaId, int $anio): ?Asiento
    {
        return Asiento::where('compania_id', $companiaId)
            ->where('origen_modulo', self::ORIGEN)
            ->where('origen_id', $anio)
            ->where('estado', Asiento::ESTADO_POSTEADO)
            ->first();
    }

    /**
     * Genera y postea el asiento de cierre del ejercicio en el período de
     * ajuste (mes 13). Lanza ValidationException si no procede.
     */
    public function cerrar(int $companiaId, int $anio, User $usuario): Asiento
    {
        if ($this->asientoDe($companiaId, $anio)) {
            throw ValidationException::withMessages([
                'anio' => "El ejercicio {$anio} ya tiene un asiento de cierre posteado.",
            ]);
        }

        $prev = $this->previsualizar($companiaId, $anio);

        if (empty($prev['lineas'])) {
            throw ValidationException::withMessages([
                'anio' => "No hay movimientos en cuentas de resultado en {$anio} para cerrar.",
            ]);
        }
        if (! $prev['cierre']['cuenta_id']) {
            throw ValidationException::withMessages([
                'anio' => 'Configura la cuenta por defecto «UTILIDADES_RETENIDAS» antes de cerrar el ejercicio.',
            ]);
        }

        $periodo = PeriodoContable::ajusteAnual($companiaId, $anio, $usuario->email);
        if (! $periodo->estaAbierto()) {
            throw ValidationException::withMessages([
                'anio' => "El período de ajuste de {$anio} está cerrado; reábrelo para registrar el cierre.",
            ]);
        }

        $detalle = [];
        foreach ($prev['lineas'] as $l) {
            $detalle[] = [
                'cuenta_id'   => $l['cuenta_id'],
                'descripcion' => "Cierre {$anio} · {$l['codigo']} {$l['nombre']}",
                'debito'      => $l['debito'],
                'credito'     => $l['credito'],
            ];
        }
        $detalle[] = [
            'cuenta_id'   => $prev['cierre']['cuenta_id'],
            'descripcion' => ($prev['utilidad'] >= 0 ? 'Utilidad' : 'Pérdida')." del ejercicio {$anio}",
            'debito'      => $prev['cierre']['debito'],
            'credito'     => $prev['cierre']['credito'],
        ];

        $totalDebito = round(collect($detalle)->sum('debito'), 2);
        $totalCredito = round(collect($detalle)->sum('credito'), 2);
        $fecha = Carbon::create($anio, 12, 31)->toDateString();

        return DB::transaction(function () use ($companiaId, $periodo, $usuario, $detalle, $totalDebito, $totalCredito, $fecha, $anio) {
            $asiento = Asiento::create([
                'compania_id'   => $companiaId,
                'periodo_id'    => $periodo->id, // respetado por el trigger (mes 13)
                'diario_id'     => Diario::general($companiaId, $usuario->email)->id,
                'numero'        => Asiento::siguienteNumero($companiaId),
                'fecha'         => $fecha,
                'descripcion'   => "Asiento de cierre del ejercicio {$anio}",
                'referencia'    => "CIERRE-{$anio}",
                'estado'        => Asiento::ESTADO_BORRADOR,
                'origen_modulo' => self::ORIGEN,
                'origen_tabla'  => 'cgl_periodos',
                'origen_id'     => $anio,
                'total_debito'  => $totalDebito,
                'total_credito' => $totalCredito,
                'usuario_id'    => $usuario->id,
                'created_by'    => $usuario->email,
            ]);

            foreach (array_values($detalle) as $i => $l) {
                AsientoDetalle::create([
                    'asiento_id'    => $asiento->id,
                    'linea'         => $i + 1,
                    'cuenta_id'     => $l['cuenta_id'],
                    'descripcion'   => $l['descripcion'],
                    'debito'        => $l['debito'],
                    'credito'       => $l['credito'],
                    'tasa_cambio'   => 1,
                    'debito_local'  => $l['debito'],
                    'credito_local' => $l['credito'],
                    'created_by'    => $usuario->email,
                ]);
            }

            $asiento->update([
                'estado'       => Asiento::ESTADO_POSTEADO,
                'periodo_id'   => $periodo->id,
                'posteado_por' => $usuario->id,
                'fecha_posteo' => now(),
            ]);

            return $asiento;
        });
    }

    /** Anula (reversa) el asiento de cierre del ejercicio; los saldos se revierten por trigger. */
    public function reversar(int $companiaId, int $anio, User $usuario): void
    {
        $asiento = $this->asientoDe($companiaId, $anio);

        if (! $asiento) {
            throw ValidationException::withMessages([
                'anio' => "No hay asiento de cierre posteado para {$anio}.",
            ]);
        }

        $asiento->update([
            'estado'     => Asiento::ESTADO_ANULADO,
            'updated_by' => $usuario->email,
        ]);
    }
}
