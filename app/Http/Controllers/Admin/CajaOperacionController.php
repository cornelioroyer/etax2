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
use App\Models\CuentaDefault;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'itbms_monto'        => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
            'documento_ref'      => ['nullable', 'string', 'max:60'],
            'comprobante'        => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
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

        // El ITBMS crédito fiscal solo aplica a compras/gastos (egreso). Es la
        // porción de impuesto incluida en el monto total que sale de la caja.
        $itbms = $esEgreso ? round((float) ($data['itbms_monto'] ?? 0), 2) : 0.0;
        $cuentaItbmsId = null;

        if ($itbms > 0) {
            if (round($monto - $itbms, 2) <= 0) {
                return back()->withErrors(['itbms_monto' => 'El ITBMS no puede ser mayor o igual al monto total del egreso.']);
            }
            $cuentaItbmsId = CuentaDefault::idPara($companiaId, 'ITBMS_CREDITO');
            if (! $cuentaItbmsId) {
                return back()->withErrors(['itbms_monto' => 'La compañía no tiene configurada la cuenta default ITBMS_CREDITO; no se puede separar el crédito fiscal.']);
            }
        }

        $comprobante = $request->file('comprobante');

        DB::transaction(function () use ($caja, $companiaId, $data, $monto, $itbms, $cuentaItbmsId, $esEgreso, $usuario, $comprobante) {
            $mov = CajaMovimiento::create([
                'compania_id'        => $companiaId,
                'caja_id'            => $caja->id,
                'fecha'              => $data['fecha'],
                'tipo_movimiento'    => $data['tipo_movimiento'],
                'beneficiario'       => $data['beneficiario'] ?? null,
                'descripcion'        => $data['descripcion'] ?? null,
                'monto'              => $monto,
                'itbms_monto'        => $itbms,
                'documento_ref'      => $data['documento_ref'] ?? null,
                'cuenta_contable_id' => (int) $data['cuenta_contable_id'],
                'created_by'         => $usuario->email,
            ]);

            // EGRESO: D gasto (base) [+ D ITBMS crédito] / C caja-efectivo (total).
            // INGRESO: D caja-efectivo / C contrapartida.
            $cuentaContra = (int) $data['cuenta_contable_id'];
            $cuentaCaja = (int) $caja->cuenta_contable_id;

            if ($esEgreso) {
                $base = round($monto - $itbms, 2);
                $lineas = [
                    ['cuenta_id' => $cuentaContra, 'descripcion' => $data['descripcion'] ?? 'Egreso de caja', 'debito' => $base, 'credito' => 0],
                ];
                if ($itbms > 0) {
                    $lineas[] = ['cuenta_id' => $cuentaItbmsId, 'descripcion' => 'ITBMS crédito fiscal'.(($data['documento_ref'] ?? null) ? ' '.$data['documento_ref'] : ''), 'debito' => $itbms, 'credito' => 0];
                }
                $lineas[] = ['cuenta_id' => $cuentaCaja, 'descripcion' => 'Caja '.$caja->codigo, 'debito' => 0, 'credito' => $monto];
            } else {
                $lineas = [
                    ['cuenta_id' => $cuentaCaja, 'descripcion' => 'Caja '.$caja->codigo, 'debito' => $monto, 'credito' => 0],
                    ['cuenta_id' => $cuentaContra, 'descripcion' => $data['descripcion'] ?? 'Ingreso de caja', 'debito' => 0, 'credito' => $monto],
                ];
            }

            $glosa = ($esEgreso ? 'Egreso' : 'Ingreso')." caja {$caja->codigo}".(($data['beneficiario'] ?? null) ? ' — '.$data['beneficiario'] : '');

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId, $data['fecha'], $glosa, null, $lineas, 'CAJA', 'caj_movimientos', $mov->id, $usuario,
            );

            $mov->update(['asiento_id' => $asiento->id]);
            $this->guardarComprobante($mov, $comprobante, $companiaId);
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
            'itbms_monto'        => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
            'documento_ref'      => ['nullable', 'string', 'max:60'],
            'comprobante'        => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
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

        $itbms = round((float) ($data['itbms_monto'] ?? 0), 2);
        $cuentaItbmsId = null;
        if ($itbms > 0) {
            if (round($monto - $itbms, 2) <= 0) {
                return back()->withErrors(['itbms_monto' => 'El ITBMS no puede ser mayor o igual al monto del vale.']);
            }
            $cuentaItbmsId = CuentaDefault::idPara($companiaId, 'ITBMS_CREDITO');
            if (! $cuentaItbmsId) {
                return back()->withErrors(['itbms_monto' => 'La compañía no tiene configurada la cuenta default ITBMS_CREDITO; no se puede separar el crédito fiscal.']);
            }
        }

        $comprobante = $request->file('comprobante');

        DB::transaction(function () use ($vale, $caja, $companiaId, $data, $monto, $itbms, $cuentaItbmsId, $usuario, $comprobante) {
            $mov = CajaMovimiento::create([
                'compania_id'        => $companiaId,
                'caja_id'            => $caja->id,
                'fecha'              => $data['fecha'],
                'tipo_movimiento'    => CajaMovimiento::TIPO_EGRESO,
                'beneficiario'       => $vale->beneficiario,
                'descripcion'        => 'Liquidación de vale: '.($vale->motivo ?? ''),
                'monto'              => $monto,
                'itbms_monto'        => $itbms,
                'documento_ref'      => $data['documento_ref'] ?? null,
                'cuenta_contable_id' => (int) $data['cuenta_contable_id'],
                'created_by'         => $usuario->email,
            ]);

            $base = round($monto - $itbms, 2);
            $lineas = [
                ['cuenta_id' => (int) $data['cuenta_contable_id'], 'descripcion' => $vale->motivo ?? 'Gasto de caja', 'debito' => $base, 'credito' => 0],
            ];
            if ($itbms > 0) {
                $lineas[] = ['cuenta_id' => $cuentaItbmsId, 'descripcion' => 'ITBMS crédito fiscal'.(($data['documento_ref'] ?? null) ? ' '.$data['documento_ref'] : ''), 'debito' => $itbms, 'credito' => 0];
            }
            $lineas[] = ['cuenta_id' => (int) $caja->cuenta_contable_id, 'descripcion' => 'Caja '.$caja->codigo, 'debito' => 0, 'credito' => $monto];

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId, $data['fecha'], "Liquidación vale caja {$caja->codigo} — {$vale->beneficiario}", null,
                $lineas, 'CAJA', 'caj_movimientos', $mov->id, $usuario,
            );

            $mov->update(['asiento_id' => $asiento->id]);
            $vale->update(['estado' => CajaVale::ESTADO_LIQUIDADO, 'updated_by' => $usuario->email]);
            $this->guardarComprobante($mov, $comprobante, $companiaId);
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

            // Asiento de diferencia del arqueo
            if ($diferencia != 0.0 && $caja->cuenta_contable_id) {
                $cuentaGastoId = CuentaDefault::idPara($caja->compania_id, 'GASTO_DEFAULT');
                if ($cuentaGastoId) {
                    $absDif    = abs($diferencia);
                    $cuentaCaja = (int) $caja->cuenta_contable_id;
                    if ($diferencia > 0) {
                        // Sobrante: hay más efectivo del esperado → Dr Caja / Cr Ingreso
                        $lineas = [
                            ['cuenta_id' => $cuentaCaja,    'descripcion' => 'Sobrante arqueo caja '.$caja->codigo, 'debito' => $absDif, 'credito' => 0],
                            ['cuenta_id' => $cuentaGastoId, 'descripcion' => 'Sobrante arqueo caja '.$caja->codigo, 'debito' => 0, 'credito' => $absDif],
                        ];
                    } else {
                        // Faltante: hay menos efectivo → Dr Gasto / Cr Caja
                        $lineas = [
                            ['cuenta_id' => $cuentaGastoId, 'descripcion' => 'Faltante arqueo caja '.$caja->codigo, 'debito' => $absDif, 'credito' => 0],
                            ['cuenta_id' => $cuentaCaja,    'descripcion' => 'Faltante arqueo caja '.$caja->codigo, 'debito' => 0, 'credito' => $absDif],
                        ];
                    }
                    $glosa   = ($diferencia > 0 ? 'Sobrante' : 'Faltante').' arqueo caja '.$caja->codigo;
                    $asiento = app(AsientoAutomatico::class)->postear(
                        $caja->compania_id, $data['fecha'], $glosa, null,
                        $lineas, 'CAJA', 'caj_arqueos', $arqueo->id, $usuario,
                    );
                    $arqueo->update(['asiento_id' => $asiento->id]);
                }
            }
        });

        $msg = $diferencia == 0.0
            ? 'Arqueo registrado: cuadra con el sistema.'
            : 'Arqueo registrado: diferencia de B/. '.number_format($diferencia, 2).' vs el sistema.';

        return back()->with('status', $msg);
    }

    /** Sirve el comprobante (recibo) adjunto a un movimiento de caja. */
    public function archivo(Request $request, CajaMovimiento $movimiento): StreamedResponse
    {
        abort_unless($request->user()->can('caja.ver'), 403);
        abort_unless($movimiento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($movimiento->archivo_path, 404);

        $disk = $movimiento->archivo_disk ?: config('filesystems.adjuntos', 's3');
        abort_unless(Storage::disk($disk)->exists($movimiento->archivo_path), 404);

        return Storage::disk($disk)->response($movimiento->archivo_path);
    }

    /**
     * Guarda el comprobante adjunto en el disco de adjuntos y persiste su
     * ruta/disco en el movimiento. Best-effort: el egreso y su asiento ya
     * quedaron registrados; si el archivo falla solo se registra en el log.
     */
    private function guardarComprobante(CajaMovimiento $mov, ?UploadedFile $file, int $companiaId): void
    {
        if (! $file) {
            return;
        }

        try {
            $disk = config('filesystems.adjuntos', 's3');
            $ext  = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $path = 'caja/'.$companiaId.'/'.Str::uuid().'.'.$ext;

            if (Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()))) {
                $mov->update(['archivo_path' => $path, 'archivo_disk' => $disk]);
            }
        } catch (\Throwable $e) {
            Log::warning('Comprobante de caja no se pudo guardar', ['mov' => $mov->id, 'error' => $e->getMessage()]);
        }
    }
}
