<?php

namespace App\Services;

use App\Models\CuentaDefault;
use App\Models\NomConcepto;
use App\Models\NomConfiguracion;
use App\Models\NomEmpleado;
use App\Models\NomIsrTramo;
use App\Models\NomMovimiento;
use App\Models\NomNovedad;
use App\Models\NomParametroLegal;
use App\Models\NomPlanilla;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Motor de la planilla regular. Reemplaza en espíritu a f_planilla_regular_
 * empleado del sistema legacy, pero con TODAS las reglas parametrizadas en
 * tablas (nom_conceptos, nom_parametros_legales, nom_isr_tramos,
 * nom_configuracion) — jamás ramas de código por compañía.
 *
 * Flujo: procesar() calcula y llena nom_movimientos (repetible mientras sea
 * borrador/procesada); contabilizar() postea el asiento vía AsientoAutomatico;
 * anular() reversa. Llamar SIEMPRE dentro de una transacción del controlador.
 */
class NomMotorPlanilla
{
    public function __construct(private AsientoAutomatico $asientos)
    {
    }

    /** Pagos al año por tipo de planilla (para proyección anual del ISR). */
    private const PERIODOS_ANIO = [
        'SEMANAL' => 52,
        'QUINCENAL' => 24,
        'MENSUAL' => 12,
    ];

    /**
     * Calcula la corrida: por cada empleado pagable del tipo de planilla del
     * período genera sus movimientos (salario, novedades, CSS/SE/ISR, cuotas
     * patronales). Borra y recalcula si ya había movimientos (solo en
     * borrador/procesada).
     */
    public function procesar(NomPlanilla $planilla, mixed $usuario): NomPlanilla
    {
        if (! in_array($planilla->estado, [NomPlanilla::ESTADO_BORRADOR, NomPlanilla::ESTADO_PROCESADA], true)) {
            throw ValidationException::withMessages([
                'planilla' => 'Solo una planilla en borrador o procesada puede (re)calcularse.',
            ]);
        }

        $periodo = $planilla->periodo;

        if (! $periodo || ! $periodo->estaAbierto()) {
            throw ValidationException::withMessages([
                'periodo' => 'El período de pago no existe o está cerrado.',
            ]);
        }

        $conceptos = NomConcepto::where('compania_id', $planilla->compania_id)
            ->where('activo', true)
            ->get()
            ->keyBy('codigo');

        foreach ([NomConcepto::COD_SALARIO, NomConcepto::COD_CSS, NomConcepto::COD_SEGURO_EDUCATIVO, NomConcepto::COD_ISR] as $requerido) {
            if (! $conceptos->has($requerido)) {
                throw ValidationException::withMessages([
                    'conceptos' => "Falta el concepto $requerido en el catálogo. Aplica el catálogo default de nómina primero.",
                ]);
            }
        }

        $config = NomConfiguracion::deCompania($planilla->compania_id);
        $fecha = Carbon::parse($planilla->fecha);
        $periodosAnio = self::PERIODOS_ANIO[$periodo->tipo_planilla] ?? 24;

        // Tasas legales vigentes a la fecha de la corrida (fuente única)
        $tasaCssEmp = NomParametroLegal::vigente(NomParametroLegal::CSS_EMPLEADO, $fecha);
        $tasaSeEmp = NomParametroLegal::vigente(NomParametroLegal::SE_EMPLEADO, $fecha);
        $tasaCssPat = NomParametroLegal::vigente(NomParametroLegal::CSS_PATRONO, $fecha);
        $tasaSePat = NomParametroLegal::vigente(NomParametroLegal::SE_PATRONO, $fecha);
        $tasaRiesgo = (float) $config->riesgo_profesional;

        $empleados = NomEmpleado::where('compania_id', $planilla->compania_id)
            ->where('tipo_planilla', $periodo->tipo_planilla)
            ->whereIn('status', [NomEmpleado::STATUS_ACTIVO, NomEmpleado::STATUS_VACACIONES])
            ->orderBy('codigo')
            ->get();

        if ($empleados->isEmpty()) {
            throw ValidationException::withMessages([
                'empleados' => "No hay empleados activos con planilla {$periodo->tipo_planilla} en esta compañía.",
            ]);
        }

        // Recalcular = borrar lo anterior (todavía no hay asiento, es seguro)
        NomMovimiento::where('planilla_id', $planilla->id)->delete();

        $novedades = NomNovedad::where('compania_id', $planilla->compania_id)
            ->where('activo', true)
            ->whereIn('empleado_id', $empleados->pluck('id'))
            ->get()
            ->groupBy('empleado_id');

        $totIngresos = 0.0;
        $totDeducciones = 0.0;
        $totPatronal = 0.0;

        foreach ($empleados as $empleado) {
            $lineas = [];   // [concepto, cantidad, base, monto, descripcion]

            // 1. Salario del período
            if ($empleado->esPorHora()) {
                // v1 (sin reloj): horas del período capturadas como novedad
                // VARIABLE del concepto 03; monto = horas x tasa
                $horasNovedad = ($novedades[$empleado->id] ?? collect())
                    ->first(fn (NomNovedad $n) => $n->concepto?->codigo === NomConcepto::COD_SALARIO && $n->aplicaA($periodo));

                $horas = (float) ($horasNovedad->cantidad ?? 0);
                $salario = round($horas * (float) $empleado->tasa_hora, 2);

                if ($salario > 0) {
                    $lineas[] = [$conceptos[NomConcepto::COD_SALARIO], $horas, null, $salario, "Salario {$horas} h"];
                }
            } else {
                $salario = $empleado->salarioDelPeriodo($periodo->tipo_planilla);

                if ($salario > 0) {
                    $lineas[] = [$conceptos[NomConcepto::COD_SALARIO], null, null, $salario, null];
                }
            }

            // 2. Novedades del período (ingresos y deducciones manuales;
            //    las de concepto 03 por-hora ya se consumieron arriba)
            foreach ($novedades[$empleado->id] ?? [] as $novedad) {
                $concepto = $novedad->concepto;

                if (! $concepto || ! $concepto->activo || $concepto->codigo === NomConcepto::COD_SALARIO) {
                    continue;
                }

                if ($concepto->calculo !== NomConcepto::CALCULO_MANUAL || ! $novedad->aplicaA($periodo)) {
                    continue;
                }

                $monto = round((float) $novedad->monto, 2);

                if ($monto > 0) {
                    $lineas[] = [$concepto, $novedad->cantidad, null, $monto, $novedad->descripcion];
                }
            }

            // 3. Bases gravables (solo ingresos marcados gravables)
            $baseCss = 0.0;
            $baseIsr = 0.0;

            foreach ($lineas as [$concepto, , , $monto]) {
                if ($concepto->esIngreso() && $concepto->gravable_css) {
                    $baseCss += $monto;
                }
                if ($concepto->esIngreso() && $concepto->gravable_isr) {
                    $baseIsr += $monto;
                }
            }

            // 4. Deducciones de ley del empleado
            if ($baseCss > 0) {
                $lineas[] = [$conceptos[NomConcepto::COD_CSS], null, $baseCss, round($baseCss * $tasaCssEmp / 100, 2), null];
                $lineas[] = [$conceptos[NomConcepto::COD_SEGURO_EDUCATIVO], null, $baseCss, round($baseCss * $tasaSeEmp / 100, 2), null];
            }

            // ISR: proyección anual simple (base del período x pagos al año).
            // v1: no considera acumulado real del año ni otras rentas.
            if ($baseIsr > 0) {
                $isrAnual = NomIsrTramo::impuestoAnual($baseIsr * $periodosAnio, $fecha);
                $isrPeriodo = round($isrAnual / $periodosAnio, 2);

                if ($isrPeriodo > 0) {
                    $lineas[] = [$conceptos[NomConcepto::COD_ISR], null, $baseIsr, $isrPeriodo, null];
                }
            }

            // 5. Cuotas patronales (si los conceptos existen en el catálogo)
            if ($baseCss > 0) {
                foreach ([
                    [NomConcepto::COD_CSS_PATRONO, $tasaCssPat],
                    [NomConcepto::COD_SE_PATRONO, $tasaSePat],
                    [NomConcepto::COD_RIESGO_PROFESIONAL, $tasaRiesgo],
                ] as [$codigo, $tasa]) {
                    if ($conceptos->has($codigo) && $tasa > 0) {
                        $lineas[] = [$conceptos[$codigo], null, $baseCss, round($baseCss * $tasa / 100, 2), null];
                    }
                }
            }

            // 6. Persistir movimientos del empleado
            foreach ($lineas as [$concepto, $cantidad, $base, $monto, $descripcion]) {
                if ($monto <= 0) {
                    continue;
                }

                NomMovimiento::create([
                    'compania_id' => $planilla->compania_id,
                    'planilla_id' => $planilla->id,
                    'empleado_id' => $empleado->id,
                    'concepto_id' => $concepto->id,
                    'cantidad' => $cantidad,
                    'base' => $base,
                    'monto' => $monto,
                    'descripcion' => $descripcion,
                ]);

                if ($concepto->esIngreso()) {
                    $totIngresos += $monto;
                } elseif ($concepto->esDeduccion()) {
                    $totDeducciones += $monto;
                } else {
                    $totPatronal += $monto;
                }
            }
        }

        $planilla->update([
            'estado' => NomPlanilla::ESTADO_PROCESADA,
            'total_ingresos' => round($totIngresos, 2),
            'total_deducciones' => round($totDeducciones, 2),
            'total_neto' => round($totIngresos - $totDeducciones, 2),
            'total_patronal' => round($totPatronal, 2),
        ]);

        return $planilla->refresh();
    }

    /**
     * Postea el asiento de la corrida:
     *   Dr gasto por concepto de ingreso (agrupado por cuenta)
     *   Dr gasto cuota patronal
     *   Cr CSS/SE por pagar (empleado + patrono), Cr ISR retenido,
     *   Cr otras retenciones, Cr Salarios por Pagar (neto)
     */
    public function contabilizar(NomPlanilla $planilla, mixed $usuario): NomPlanilla
    {
        if (! $planilla->esProcesada()) {
            throw ValidationException::withMessages([
                'planilla' => 'Solo una planilla procesada (calculada y revisada) puede contabilizarse.',
            ]);
        }

        $companiaId = $planilla->compania_id;

        $ctaGastoSalarios = $this->cuentaDefault($companiaId, 'GASTO_SALARIOS', 'Gasto de Salarios');
        $ctaSalariosPagar = $this->cuentaDefault($companiaId, 'SALARIOS_POR_PAGAR', 'Salarios por Pagar');
        $ctaGastoPatronal = CuentaDefault::idPara($companiaId, 'GASTO_CUOTA_PATRONAL') ?? $ctaGastoSalarios;
        $ctaCssPorPagar = CuentaDefault::idPara($companiaId, 'CSS_POR_PAGAR')
            ?? CuentaDefault::idPara($companiaId, 'RETENCIONES');
        $ctaIsrRetenido = CuentaDefault::idPara($companiaId, 'ISR_RETENIDO')
            ?? CuentaDefault::idPara($companiaId, 'RETENCIONES');
        $ctaRetenciones = CuentaDefault::idPara($companiaId, 'RETENCIONES') ?? $ctaCssPorPagar;

        if (! $ctaCssPorPagar || ! $ctaIsrRetenido) {
            throw ValidationException::withMessages([
                'cuentas' => 'Configura las cuentas default CSS_POR_PAGAR / ISR_RETENIDO (o al menos RETENCIONES) en Cuentas por Defecto.',
            ]);
        }

        $movimientos = $planilla->movimientos()->with('concepto')->get();

        // debitos/creditos agregados por cuenta
        $debitos = [];
        $creditos = [];

        foreach ($movimientos as $mov) {
            $concepto = $mov->concepto;
            $monto = (float) $mov->monto;

            if ($concepto->esIngreso()) {
                $cuenta = $concepto->cuenta_gasto_id ?? $ctaGastoSalarios;
                $debitos[$cuenta] = ($debitos[$cuenta] ?? 0) + $monto;
            } elseif ($concepto->esPatronal()) {
                $ctaGasto = $concepto->cuenta_gasto_id ?? $ctaGastoPatronal;
                $ctaPasivo = $concepto->cuenta_pasivo_id ?? $ctaCssPorPagar;
                $debitos[$ctaGasto] = ($debitos[$ctaGasto] ?? 0) + $monto;
                $creditos[$ctaPasivo] = ($creditos[$ctaPasivo] ?? 0) + $monto;
            } else { // DEDUCCION
                $cuenta = $concepto->cuenta_pasivo_id ?? match ($concepto->codigo) {
                    NomConcepto::COD_CSS, NomConcepto::COD_SEGURO_EDUCATIVO => $ctaCssPorPagar,
                    NomConcepto::COD_ISR => $ctaIsrRetenido,
                    default => $ctaRetenciones,
                };
                $creditos[$cuenta] = ($creditos[$cuenta] ?? 0) + $monto;
            }
        }

        // Neto por pagar a empleados
        $neto = (float) $planilla->total_neto;

        if ($neto > 0) {
            $creditos[$ctaSalariosPagar] = ($creditos[$ctaSalariosPagar] ?? 0) + $neto;
        }

        $lineas = [];

        foreach ($debitos as $cuentaId => $monto) {
            $lineas[] = ['cuenta_id' => (int) $cuentaId, 'descripcion' => 'Planilla '.$planilla->numero, 'debito' => round($monto, 2), 'credito' => 0];
        }

        foreach ($creditos as $cuentaId => $monto) {
            $lineas[] = ['cuenta_id' => (int) $cuentaId, 'descripcion' => 'Planilla '.$planilla->numero, 'debito' => 0, 'credito' => round($monto, 2)];
        }

        $asiento = $this->asientos->postear(
            $companiaId,
            $planilla->fecha->toDateString(),
            'Planilla '.$planilla->numero.' — '.$planilla->periodo->etiqueta(),
            $planilla->numero,
            $lineas,
            'NOM',
            'nom_planillas',
            $planilla->id,
            $usuario,
        );

        $planilla->update([
            'estado' => NomPlanilla::ESTADO_CONTABILIZADA,
            'asiento_id' => $asiento->id,
        ]);

        return $planilla->refresh();
    }

    /** Anula la corrida: reversa el asiento (si existe) y marca ANULADA. */
    public function anular(NomPlanilla $planilla, User $usuario): NomPlanilla
    {
        if ($planilla->estaAnulada()) {
            throw ValidationException::withMessages(['planilla' => 'La planilla ya está anulada.']);
        }

        $this->asientos->anular($planilla->asiento, $usuario);

        $planilla->update(['estado' => NomPlanilla::ESTADO_ANULADA]);

        return $planilla->refresh();
    }

    private function cuentaDefault(int $companiaId, string $clave, string $nombre): int
    {
        $id = CuentaDefault::idPara($companiaId, $clave);

        if (! $id) {
            throw ValidationException::withMessages([
                'cuentas' => "Configura la cuenta default $clave ($nombre) en Contabilidad → Cuentas por Defecto antes de contabilizar la planilla.",
            ]);
        }

        return $id;
    }
}
