<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\CajaArqueo;
use App\Models\CajaArqueoDetalle;
use App\Models\CajaMovimiento;
use App\Models\CajaReembolso;
use App\Models\CajaVale;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CajaOperacionController extends Controller
{
    use ConCompaniaActiva;

    /** Movimiento de caja (EGRESO o INGRESO) con su asiento. */
    public function movimiento(Request $request, Caja $caja): RedirectResponse
    {
        abort_unless($request->user()->can('caja.gestionar'), 403);
        abort_unless($caja->compania_id === $this->companiaActivaId($request), 404);

        $companiaId = $caja->compania_id;

        $data = $request->validate([
            'tipo_movimiento'    => ['required', Rule::in([CajaMovimiento::TIPO_EGRESO, CajaMovimiento::TIPO_INGRESO])],
            'fecha'              => ['required', 'date'],
            'monto'              => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'beneficiario'       => ['nullable', 'string', 'max:200'],
            'descripcion'        => ['nullable', 'string', 'max:500'],
            'cuenta_contable_id' => ['required', 'integer', Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId)],
        ]);

        if (! $caja->cuenta_contable_id) {
            return back()->withErrors(['movimiento' => 'La caja no tiene cuenta contable de efectivo asignada; edítala primero.']);
        }
        if ((int) $data['cuenta_contable_id'] === (int) $caja->cuenta_contable_id) {
            return back()->withErrors(['cuenta_contable_id' => 'La contrapartida no puede ser la misma cuenta de efectivo de la caja.']);
        }

        $usuario = $request->user();
        $monto = round((float) $data['monto'], 2);
        $esEgreso = $data['tipo_movimiento'] === CajaMovimiento::TIPO_EGRESO;

        DB::transaction(function () use ($caja, $companiaId, $data, $monto, $esEgreso, $usuario) {
            $mov = CajaMovimiento::create([
                'compania_id'        => $companiaId,
                'caja_id'            => $caja->id,
                'fecha'              => $data['fecha'],
                'tipo_movimiento'    => $data['tipo_movimiento'],
                'beneficiario'       => $data['beneficiario'] ?? null,
                'descripcion'        => $data['descripcion'] ?? null,
                'monto'              => $monto,
                'cuenta_contable_id' => (int) $data['cuenta_contable_id'],
                'created_by'         => $usuario->email,
            ]);

            // EGRESO: D gasto / C caja-efectivo. INGRESO: D caja-efectivo / C contrapartida.
            $cuentaContra = (int) $data['cuenta_contable_id'];
            $cuentaCaja = (int) $caja->cuenta_contable_id;

            $lineas = $esEgreso
                ? [
                    ['cuenta_id' => $cuentaContra, 'descripcion' => $data['descripcion'] ?? 'Egreso de caja', 'debito' => $monto, 'credito' => 0],
                    ['cuenta_id' => $cuentaCaja, 'descripcion' => 'Caja '.$caja->codigo, 'debito' => 0, 'credito' => $monto],
                ]
                : [
                    ['cuenta_id' => $cuentaCaja, 'descripcion' => 'Caja '.$caja->codigo, 'debito' => $monto, 'credito' => 0],
                    ['cuenta_id' => $cuentaContra, 'descripcion' => $data['descripcion'] ?? 'Ingreso de caja', 'debito' => 0, 'credito' => $monto],
                ];

            $glosa = ($esEgreso ? 'Egreso' : 'Ingreso')." caja {$caja->codigo}".(($data['beneficiario'] ?? null) ? ' — '.$data['beneficiario'] : '');

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId, $data['fecha'], $glosa, null, $lineas, 'CAJA', 'caj_movimientos', $mov->id, $usuario,
            );

            $mov->update(['asiento_id' => $asiento->id]);
        });

        return back()->with('status', 'Movimiento de caja registrado y contabilizado.');
    }

    /** Reembolso (reposición del fondo desde banco): D caja-efectivo / C banco. */
    public function reembolso(Request $request, Caja $caja): RedirectResponse
    {
        abort_unless($request->user()->can('caja.gestionar'), 403);
        abort_unless($caja->compania_id === $this->companiaActivaId($request), 404);

        $companiaId = $caja->compania_id;

        $data = $request->validate([
            'fecha'          => ['required', 'date'],
            'monto'          => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'cuenta_banco_id'=> ['required', 'integer', Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId)],
        ]);

        if (! $caja->cuenta_contable_id) {
            return back()->withErrors(['reembolso' => 'La caja no tiene cuenta contable de efectivo asignada; edítala primero.']);
        }
        if ((int) $data['cuenta_banco_id'] === (int) $caja->cuenta_contable_id) {
            return back()->withErrors(['cuenta_banco_id' => 'La cuenta de origen no puede ser la misma cuenta de efectivo de la caja.']);
        }

        $usuario = $request->user();
        $monto = round((float) $data['monto'], 2);

        DB::transaction(function () use ($caja, $companiaId, $data, $monto, $usuario) {
            $reembolso = CajaReembolso::create([
                'caja_id'    => $caja->id,
                'fecha'      => $data['fecha'],
                'monto'      => $monto,
                'estado'     => CajaReembolso::ESTADO_APLICADO,
                'created_by' => $usuario->email,
            ]);

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId, $data['fecha'], "Reembolso caja {$caja->codigo}", null,
                [
                    ['cuenta_id' => (int) $caja->cuenta_contable_id, 'descripcion' => 'Caja '.$caja->codigo, 'debito' => $monto, 'credito' => 0],
                    ['cuenta_id' => (int) $data['cuenta_banco_id'], 'descripcion' => 'Reposición de fondo', 'debito' => 0, 'credito' => $monto],
                ],
                'CAJA', 'caj_reembolsos', $reembolso->id, $usuario,
            );

            $reembolso->update(['asiento_id' => $asiento->id]);
        });

        return back()->with('status', 'Reembolso registrado y contabilizado.');
    }

    /** Registra un vale (adelanto pendiente de liquidar). No genera asiento. */
    public function vale(Request $request, Caja $caja): RedirectResponse
    {
        abort_unless($request->user()->can('caja.gestionar'), 403);
        abort_unless($caja->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'fecha'        => ['required', 'date'],
            'beneficiario' => ['required', 'string', 'max:200'],
            'monto'        => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'motivo'       => ['nullable', 'string', 'max:500'],
        ]);

        CajaVale::create([
            'caja_id'      => $caja->id,
            'fecha'        => $data['fecha'],
            'beneficiario' => $data['beneficiario'],
            'monto'        => round((float) $data['monto'], 2),
            'motivo'       => $data['motivo'] ?? null,
            'estado'       => CajaVale::ESTADO_PENDIENTE,
            'created_by'   => $request->user()->email,
        ]);

        return back()->with('status', 'Vale registrado (pendiente de liquidar).');
    }

    /** Liquida un vale: lo convierte en egreso contabilizado (D gasto / C caja). */
    public function liquidarVale(Request $request, CajaVale $vale): RedirectResponse
    {
        abort_unless($request->user()->can('caja.gestionar'), 403);
        $caja = $vale->caja;
        abort_unless($caja->compania_id === $this->companiaActivaId($request), 404);

        if (! $vale->estaPendiente()) {
            return back()->withErrors(['vale' => 'El vale ya fue liquidado o anulado.']);
        }

        $companiaId = $caja->compania_id;

        $data = $request->validate([
            'fecha'              => ['required', 'date'],
            'cuenta_contable_id' => ['required', 'integer', Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId)],
        ]);

        if (! $caja->cuenta_contable_id) {
            return back()->withErrors(['vale' => 'La caja no tiene cuenta contable de efectivo asignada; edítala primero.']);
        }
        if ((int) $data['cuenta_contable_id'] === (int) $caja->cuenta_contable_id) {
            return back()->withErrors(['cuenta_contable_id' => 'La cuenta de gasto no puede ser la misma cuenta de efectivo de la caja.']);
        }

        $usuario = $request->user();
        $monto = round((float) $vale->monto, 2);

        DB::transaction(function () use ($vale, $caja, $companiaId, $data, $monto, $usuario) {
            $mov = CajaMovimiento::create([
                'compania_id'        => $companiaId,
                'caja_id'            => $caja->id,
                'fecha'              => $data['fecha'],
                'tipo_movimiento'    => CajaMovimiento::TIPO_EGRESO,
                'beneficiario'       => $vale->beneficiario,
                'descripcion'        => 'Liquidación de vale: '.($vale->motivo ?? ''),
                'monto'              => $monto,
                'cuenta_contable_id' => (int) $data['cuenta_contable_id'],
                'created_by'         => $usuario->email,
            ]);

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId, $data['fecha'], "Liquidación vale caja {$caja->codigo} — {$vale->beneficiario}", null,
                [
                    ['cuenta_id' => (int) $data['cuenta_contable_id'], 'descripcion' => $vale->motivo ?? 'Gasto de caja', 'debito' => $monto, 'credito' => 0],
                    ['cuenta_id' => (int) $caja->cuenta_contable_id, 'descripcion' => 'Caja '.$caja->codigo, 'debito' => 0, 'credito' => $monto],
                ],
                'CAJA', 'caj_movimientos', $mov->id, $usuario,
            );

            $mov->update(['asiento_id' => $asiento->id]);
            $vale->update(['estado' => CajaVale::ESTADO_LIQUIDADO, 'updated_by' => $usuario->email]);
        });

        return back()->with('status', 'Vale liquidado y contabilizado.');
    }

    /** Arqueo: conteo físico por denominación; calcula diferencia vs saldo del sistema. */
    public function arqueo(Request $request, Caja $caja): RedirectResponse
    {
        abort_unless($request->user()->can('caja.gestionar'), 403);
        abort_unless($caja->compania_id === $this->companiaActivaId($request), 404);

        $data = $request->validate([
            'fecha'                       => ['required', 'date'],
            'denominaciones'              => ['required', 'array', 'min:1'],
            'denominaciones.*.denominacion' => ['required', 'numeric', 'gt:0'],
            'denominaciones.*.cantidad'   => ['required', 'integer', 'gte:0'],
        ]);

        $detalle = [];
        $saldoFisico = 0.0;

        foreach ($data['denominaciones'] as $d) {
            $cantidad = (int) $d['cantidad'];
            if ($cantidad <= 0) {
                continue;
            }
            $denom = round((float) $d['denominacion'], 2);
            $total = round($denom * $cantidad, 2);
            $saldoFisico += $total;
            $detalle[] = ['denominacion' => $denom, 'cantidad' => $cantidad, 'total' => $total];
        }

        if (empty($detalle)) {
            throw ValidationException::withMessages(['denominaciones' => 'Indica al menos una denominación con cantidad.']);
        }

        $saldoFisico = round($saldoFisico, 2);
        $saldoSistema = $caja->saldoSistema();
        $diferencia = round($saldoFisico - $saldoSistema, 2);
        $usuario = $request->user();

        DB::transaction(function () use ($caja, $data, $detalle, $saldoFisico, $saldoSistema, $diferencia, $usuario) {
            $arqueo = CajaArqueo::create([
                'caja_id'       => $caja->id,
                'fecha'         => $data['fecha'],
                'saldo_sistema' => $saldoSistema,
                'saldo_fisico'  => $saldoFisico,
                'diferencia'    => $diferencia,
                'usuario_id'    => $usuario->id,
                'estado'        => CajaArqueo::ESTADO_CERRADO,
                'created_by'    => $usuario->email,
            ]);

            foreach ($detalle as $d) {
                CajaArqueoDetalle::create($d + ['arqueo_id' => $arqueo->id, 'created_by' => $usuario->email]);
            }
        });

        $msg = $diferencia == 0.0
            ? 'Arqueo registrado: cuadra con el sistema.'
            : 'Arqueo registrado: diferencia de B/. '.number_format($diferencia, 2).' vs el sistema.';

        return back()->with('status', $msg);
    }
}
