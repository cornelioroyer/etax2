<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\AfiActivo;
use App\Models\AfiBaja;
use App\Models\AfiCategoria;
use App\Models\AfiDepreciacion;
use App\Models\AfiUbicacion;
use App\Models\CuentaContable;
use App\Models\PeriodoContable;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AfiActivoController extends Controller
{
    use ConCompaniaActiva;

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('activos.ver'), 403);
        $companiaId = $this->companiaActivaId($request);

        $activos = AfiActivo::where('compania_id', $companiaId)
            ->with(['categoria', 'ubicacion'])
            ->orderBy('codigo')
            ->get()
            ->map(function ($a) {
                $a->dep_acumulada = $a->depreciacionAcumulada();
                $a->valor_libros  = $a->valorLibros();
                return $a;
            });

        return view('admin.activos.activos.index', compact('activos'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('activos.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $categorias = AfiCategoria::where('compania_id', $companiaId)->orderBy('codigo')->get();
        $ubicaciones = AfiUbicacion::where('compania_id', $companiaId)->orderBy('codigo')->get();
        $cuentas = CuentaContable::where('compania_id', $companiaId)
            ->where('acepta_movimientos', true)
            ->orderBy('codigo')
            ->get();

        return view('admin.activos.activos.create', compact('categorias', 'ubicaciones', 'cuentas'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('activos.gestionar'), 403);
        $companiaId = $this->companiaActivaId($request);

        $data = $request->validate([
            'descripcion'                  => ['required', 'string', 'max:500'],
            'categoria_id'                 => ['nullable', 'integer'],
            'ubicacion_id'                 => ['nullable', 'integer'],
            'fecha_compra'                 => ['required', 'date'],
            'fecha_inicio_depreciacion'    => ['required', 'date'],
            'valor_compra'                 => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'valor_residual'               => ['required', 'numeric', 'min:0', 'max:999999999'],
            'vida_util_meses'              => ['required', 'integer', 'min:0', 'max:600'],
            'cuenta_activo_id'             => ['nullable', 'integer'],
            'cuenta_depreciacion_acum_id'  => ['nullable', 'integer'],
            'cuenta_gasto_depreciacion_id' => ['nullable', 'integer'],
            'cuenta_contrapartida_id'      => ['required', 'integer'],
        ]);

        $usuario = $request->user();
        $activo = null;

        DB::transaction(function () use ($companiaId, $data, $usuario, &$activo) {
            $codigo = AfiActivo::siguienteNumero($companiaId);

            $activo = AfiActivo::create([
                'compania_id'                  => $companiaId,
                'codigo'                       => $codigo,
                'descripcion'                  => $data['descripcion'],
                'categoria_id'                 => $data['categoria_id'] ?? null,
                'ubicacion_id'                 => $data['ubicacion_id'] ?? null,
                'fecha_compra'                 => $data['fecha_compra'],
                'fecha_inicio_depreciacion'    => $data['fecha_inicio_depreciacion'],
                'valor_compra'                 => round((float) $data['valor_compra'], 2),
                'valor_residual'               => round((float) $data['valor_residual'], 2),
                'vida_util_meses'              => (int) $data['vida_util_meses'],
                'metodo_depreciacion'          => 'LINEA_RECTA',
                'cuenta_activo_id'             => $data['cuenta_activo_id'] ?? null,
                'cuenta_depreciacion_acum_id'  => $data['cuenta_depreciacion_acum_id'] ?? null,
                'cuenta_gasto_depreciacion_id' => $data['cuenta_gasto_depreciacion_id'] ?? null,
                'estado'                       => AfiActivo::ESTADO_ACTIVO,
                'created_by'                   => $usuario->email,
            ]);

            // Asiento de compra: D cuenta_activo / C contrapartida
            $cuentaActivoId = $activo->cuentaActivoEfectivaId();
            $cuentaContraId = (int) $data['cuenta_contrapartida_id'];
            $monto          = round((float) $data['valor_compra'], 2);

            if ($cuentaActivoId && $cuentaContraId) {
                $lineas = [
                    ['cuenta_id' => $cuentaActivoId, 'descripcion' => $activo->descripcion, 'debito' => $monto, 'credito' => 0],
                    ['cuenta_id' => $cuentaContraId, 'descripcion' => 'Compra activo '.$activo->codigo, 'debito' => 0, 'credito' => $monto],
                ];

                $asiento = app(AsientoAutomatico::class)->postear(
                    $companiaId,
                    $data['fecha_compra'],
                    'Registro activo fijo: '.$activo->descripcion,
                    $activo->codigo,
                    $lineas,
                    'ACTIVO_FIJO',
                    'afi_activos',
                    $activo->id,
                    $usuario,
                );

                $activo->update(['asiento_compra_id' => $asiento->id]);
            }
        });

        return redirect()->route('admin.activos.activos.show', $activo)
            ->with('status', "Activo {$activo->codigo} registrado.");
    }

    public function show(Request $request, AfiActivo $activo): View
    {
        abort_unless($request->user()->can('activos.ver'), 403);
        abort_unless($activo->compania_id === $this->companiaActivaId($request), 404);

        $activo->load([
            'categoria', 'ubicacion',
            'cuentaActivo', 'cuentaDepreciacionAcum', 'cuentaGastoDepreciacion',
            'asientoCompra',
            'depreciaciones.periodo', 'depreciaciones.asiento',
            'baja.asiento',
        ]);

        $companiaId = $activo->compania_id;
        $cuentas = CuentaContable::where('compania_id', $companiaId)
            ->where('acepta_movimientos', true)
            ->orderBy('codigo')
            ->get();

        $periodos = PeriodoContable::where('compania_id', $companiaId)
            ->where('estado', 'ABIERTO')
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->get();

        return view('admin.activos.activos.show', compact('activo', 'cuentas', 'periodos'));
    }

    /** Correr depreciación mensual de un activo (un período). */
    public function depreciar(Request $request, AfiActivo $activo): RedirectResponse
    {
        abort_unless($request->user()->can('activos.gestionar'), 403);
        abort_unless($activo->compania_id === $this->companiaActivaId($request), 404);

        if (! $activo->estaActivo()) {
            return back()->withErrors(['depreciar' => 'El activo no está activo.']);
        }

        $data = $request->validate([
            'fecha'      => ['required', 'date'],
            'periodo_id' => ['required', 'integer'],
        ]);

        $periodo = PeriodoContable::findOrFail((int) $data['periodo_id']);
        if ($periodo->compania_id !== $activo->compania_id) {
            abort(404);
        }

        // Verificar que no se ha corrido depreciación para este período
        $yaDepreciado = AfiDepreciacion::where('activo_id', $activo->id)
            ->where('periodo_id', $periodo->id)
            ->exists();

        if ($yaDepreciado) {
            return back()->withErrors(['depreciar' => "Ya existe depreciación para el período {$periodo->anio}-".str_pad($periodo->mes, 2, '0', STR_PAD_LEFT).".'"]);
        }

        if ($activo->estaDepreciadoTotal()) {
            return back()->withErrors(['depreciar' => 'El activo ya está totalmente depreciado.']);
        }

        $cuentaGastoId = $activo->cuentaGastoDepEfectivaId();
        $cuentaAcumId  = $activo->cuentaDepAcumEfectivaId();

        if (! $cuentaGastoId || ! $cuentaAcumId) {
            return back()->withErrors(['depreciar' => 'El activo o su categoría no tiene cuentas de depreciación configuradas.']);
        }

        $usuario   = $request->user();
        $depMensual = $activo->depreciacionMensual();
        $depAcumPrev = $activo->depreciacionAcumulada();
        $depMax    = round($activo->valor_compra - $activo->valor_residual - $depAcumPrev, 2);
        $monto     = min($depMensual, $depMax);

        if ($monto <= 0) {
            return back()->withErrors(['depreciar' => 'No hay monto a depreciar.']);
        }

        DB::transaction(function () use ($activo, $periodo, $data, $monto, $depAcumPrev, $cuentaGastoId, $cuentaAcumId, $usuario) {
            $dep = AfiDepreciacion::create([
                'activo_id'  => $activo->id,
                'periodo_id' => $periodo->id,
                'fecha'      => $data['fecha'],
                'monto'      => $monto,
                'acumulado'  => round($depAcumPrev + $monto, 2),
                'estado'     => 'POSTEADA',
                'created_by' => $usuario->email,
            ]);

            $lineas = [
                ['cuenta_id' => $cuentaGastoId, 'descripcion' => 'Dep. '.$activo->codigo.' '.$activo->descripcion, 'debito' => $monto, 'credito' => 0],
                ['cuenta_id' => $cuentaAcumId,  'descripcion' => 'Dep. acum. '.$activo->codigo, 'debito' => 0, 'credito' => $monto],
            ];

            $asiento = app(AsientoAutomatico::class)->postear(
                $activo->compania_id,
                $data['fecha'],
                'Depreciación '.$activo->codigo.' — '.$activo->descripcion,
                null,
                $lineas,
                'ACTIVO_FIJO',
                'afi_depreciaciones',
                $dep->id,
                $usuario,
            );

            $dep->update(['asiento_id' => $asiento->id]);
        });

        return back()->with('status', 'Depreciación registrada: B/. '.number_format($monto, 2).'.');
    }

    /** Dar de baja un activo (retiro/desincorporación). */
    public function baja(Request $request, AfiActivo $activo): RedirectResponse
    {
        abort_unless($request->user()->can('activos.gestionar'), 403);
        abort_unless($activo->compania_id === $this->companiaActivaId($request), 404);

        if (! $activo->estaActivo()) {
            return back()->withErrors(['baja' => 'El activo ya está dado de baja.']);
        }

        $data = $request->validate([
            'fecha'             => ['required', 'date'],
            'motivo'            => ['nullable', 'string', 'max:500'],
            'cuenta_resultado_id' => ['required', 'integer'],
        ]);

        $cuentaActivoId = $activo->cuentaActivoEfectivaId();
        $cuentaAcumId   = $activo->cuentaDepAcumEfectivaId();

        if (! $cuentaActivoId) {
            return back()->withErrors(['baja' => 'El activo no tiene cuenta de activo configurada.']);
        }

        $usuario   = $request->user();
        $depAcum   = $activo->depreciacionAcumulada();
        $valorLibros = round($activo->valor_compra - $depAcum, 2);

        DB::transaction(function () use ($activo, $data, $depAcum, $valorLibros, $cuentaActivoId, $cuentaAcumId, $usuario) {
            $baja = AfiBaja::create([
                'activo_id'  => $activo->id,
                'fecha'      => $data['fecha'],
                'motivo'     => $data['motivo'] ?? null,
                'valor_baja' => $valorLibros,
                'created_by' => $usuario->email,
            ]);

            // Asiento de baja:
            // D dep_acumulada (si hay)      = depAcum
            // D resultado_baja (pérdida)    = valorLibros (si > 0)
            // C cuenta_activo               = valor_compra
            $monto        = round($activo->valor_compra, 2);
            $cuentaResultId = (int) $data['cuenta_resultado_id'];

            $lineas = [];

            if ($cuentaAcumId && $depAcum > 0) {
                $lineas[] = ['cuenta_id' => $cuentaAcumId, 'descripcion' => 'Dep. acum. baja '.$activo->codigo, 'debito' => $depAcum, 'credito' => 0];
            }

            if ($valorLibros > 0) {
                $lineas[] = ['cuenta_id' => $cuentaResultId, 'descripcion' => 'Pérdida baja '.$activo->codigo, 'debito' => $valorLibros, 'credito' => 0];
            } elseif ($valorLibros < 0) {
                // Ganancia en baja (activo revaluado por encima)
                $lineas[] = ['cuenta_id' => $cuentaResultId, 'descripcion' => 'Ganancia baja '.$activo->codigo, 'debito' => 0, 'credito' => abs($valorLibros)];
                $monto = $depAcum; // creditamos solo lo que hay en activo neto
            }

            $lineas[] = ['cuenta_id' => $cuentaActivoId, 'descripcion' => 'Baja activo '.$activo->codigo, 'debito' => 0, 'credito' => round($activo->valor_compra, 2)];

            $asiento = app(AsientoAutomatico::class)->postear(
                $activo->compania_id,
                $data['fecha'],
                'Baja activo fijo '.$activo->codigo.' — '.$activo->descripcion,
                null,
                $lineas,
                'ACTIVO_FIJO',
                'afi_bajas',
                $baja->id,
                $usuario,
            );

            $baja->update(['asiento_id' => $asiento->id]);
            $activo->update(['estado' => AfiActivo::ESTADO_DADO_DE_BAJA, 'updated_by' => $usuario->email]);
        });

        return back()->with('status', 'Activo '.$activo->codigo.' dado de baja.');
    }
}
