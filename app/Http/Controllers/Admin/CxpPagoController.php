<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Concerns\ExportaReporte;
use App\Http\Controllers\Controller;
use App\Models\Asiento;
use App\Models\BcoCuenta;
use App\Models\BcoMovimiento;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\CuentaContable;
use App\Models\CuentaDefault;
use App\Models\CxpAplicacion;
use App\Models\CxpDocumento;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CxpPagoController extends Controller
{
    use ConCompaniaActiva;
    use ExportaReporte;

    public function index(Request $request): View|Response
    {
        $companiaId = $this->companiaActivaId($request);

        $filtros = $request->validate([
            'proveedor_id' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $consulta = CxpDocumento::query()
            ->with('proveedor')
            ->where('compania_id', $companiaId)
            ->where('tipo_documento', CxpDocumento::TIPO_PAGO)
            ->when($filtros['proveedor_id'] ?? null, fn ($q, $proveedor) => $q->where('proveedor_id', $proveedor))
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('fecha')
            ->orderByDesc('numero');

        if ($request->query('export')) {
            $todos = (clone $consulta)->get();

            if ($export = $this->exportarReporte($request, 'admin.exports.listado', [
                'titulo' => 'Pagos — CxP',
                'compania' => Compania::find($companiaId)?->nombre ?? '',
                'subtitulo' => 'Listado al '.now()->format('d/m/Y').' — '.$todos->count().' pagos',
                'encabezados' => [
                    ['titulo' => 'Número'], ['titulo' => 'Fecha'], ['titulo' => 'Proveedor'],
                    ['titulo' => 'Total', 'num' => true], ['titulo' => 'Estado'],
                ],
                'filas' => $todos->map(fn ($p) => [
                    $p->numero, $p->fecha->format('d/m/Y'), $p->proveedor->nombre ?? '',
                    number_format((float) $p->total, 2), ucfirst(strtolower($p->estado)),
                ])->all(),
                'totales' => ['TOTAL', '', '', number_format((float) $todos->where('estado', '!=', CxpDocumento::ESTADO_ANULADO)->sum('total'), 2), ''],
            ], 'pagos_cxp_'.now()->format('Y-m-d'))) {
                return $export;
            }
        }

        $pagos = $consulta->paginate(25)->withQueryString();

        $proveedores = Contacto::where('compania_id', $companiaId)
            ->whereHas('tipos', fn ($q) => $q->where('codigo', 'PROVEEDOR'))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        return view('admin.cxp.pagos.index', compact('pagos', 'filtros', 'proveedores'));
    }

    public function create(Request $request): View
    {
        $companiaId = $this->companiaActivaId($request);

        $proveedorId = $request->integer('proveedor_id') ?: null;

        $facturas = $proveedorId
            ? CxpDocumento::where('compania_id', $companiaId)
                ->whereIn('tipo_documento', CxpDocumento::tiposPagables())
                ->where('proveedor_id', $proveedorId)
                ->whereIn('estado', [CxpDocumento::ESTADO_PENDIENTE, CxpDocumento::ESTADO_PARCIAL])
                ->where('saldo', '>', 0)
                ->orderBy('fecha')
                ->get()
            : collect();

        // Créditos a favor del proveedor (anticipos + notas de crédito con saldo
        // disponible) que pueden aplicarse dentro del pago para reducir el efectivo.
        $creditos = $proveedorId
            ? CxpDocumento::where('compania_id', $companiaId)
                ->whereIn('tipo_documento', CxpDocumento::tiposCredito())
                ->where('proveedor_id', $proveedorId)
                ->whereNotIn('estado', [CxpDocumento::ESTADO_ANULADO, CxpDocumento::ESTADO_BORRADOR])
                ->where('saldo', '>', 0)
                ->orderBy('fecha')
                ->get()
            : collect();

        $cuentasMovimiento = CuentaContable::where('compania_id', $companiaId)
            ->where('permite_movimiento', true)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        return view('admin.cxp.pagos.create', [
            'proveedores' => Contacto::where('compania_id', $companiaId)
                ->where('activo', true)
                ->whereHas('tipos', fn ($q) => $q->where('codigo', 'PROVEEDOR'))
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
            'proveedorId' => $proveedorId,
            'facturas' => $facturas,
            'creditos' => $creditos,
            'cuentasPago' => $cuentasMovimiento,
            'cuentaBancoId' => CuentaDefault::idPara($companiaId, 'BANCO_DEFAULT')
                ?? CuentaDefault::idPara($companiaId, 'CAJA_DEFAULT'),
            'cuentaRetencionItbmsId' => CuentaDefault::idPara($companiaId, 'RETENCION_CXP')
                ?? CuentaDefault::idPara($companiaId, 'RETENCION_ITBMS_CXP'),
            'cuentaRetencionIsrId' => CuentaDefault::idPara($companiaId, 'RETENCION_ISR_CXP'),
            'cuentaDescuentoId' => CuentaDefault::idPara($companiaId, 'DESCUENTO_PRONTO_PAGO'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario = $request->user();

        $data = $request->validate([
            'proveedor_id' => [
                'required', 'integer',
                Rule::exists('contact_contactos', 'id')->where('compania_id', $companiaId),
            ],
            'fecha' => ['required', 'date'],
            'cuenta_pago_id' => [
                'required', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
            'referencia' => ['nullable', 'string', 'max:100'],
            // Retención de ITBMS (parámetro histórico 'retencion'/'retencion_cuenta_id').
            'retencion' => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
            'retencion_cuenta_id' => [
                Rule::requiredIf(fn () => (float) $request->input('retencion', 0) > 0),
                'nullable', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
            // Retención de ISR.
            'retencion_isr' => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
            'retencion_isr_cuenta_id' => [
                Rule::requiredIf(fn () => (float) $request->input('retencion_isr', 0) > 0),
                'nullable', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
            // Descuento por pronto pago.
            'descuento' => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
            'descuento_cuenta_id' => [
                Rule::requiredIf(fn () => (float) $request->input('descuento', 0) > 0),
                'nullable', 'integer',
                Rule::exists('cgl_cuentas', 'id')->where('compania_id', $companiaId),
            ],
            'aplicaciones' => ['required', 'array', 'min:1'],
            'aplicaciones.*.documento_id' => ['required', 'integer'],
            'aplicaciones.*.monto' => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
            // Créditos (anticipos / notas de crédito) a aplicar.
            'creditos' => ['nullable', 'array'],
            'creditos.*.documento_id' => ['required', 'integer'],
            'creditos.*.monto' => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
        ]);

        $cuentaCxpId = CuentaDefault::idPara($companiaId, 'CXP');

        if (! $cuentaCxpId) {
            throw ValidationException::withMessages([
                'proveedor_id' => 'La compañía no tiene configurada la cuenta default CXP (Cuentas por Pagar).',
            ]);
        }

        $aplicar = collect($data['aplicaciones'])
            ->map(fn ($a) => ['documento_id' => (int) $a['documento_id'], 'monto' => round((float) ($a['monto'] ?? 0), 2)])
            ->filter(fn ($a) => $a['monto'] > 0)
            ->values();

        if ($aplicar->isEmpty()) {
            throw ValidationException::withMessages(['aplicaciones' => 'Indica el monto a pagar en al menos una factura.']);
        }

        $creditos = collect($data['creditos'] ?? [])
            ->map(fn ($c) => ['documento_id' => (int) $c['documento_id'], 'monto' => round((float) ($c['monto'] ?? 0), 2)])
            ->filter(fn ($c) => $c['monto'] > 0)
            ->values();

        $retItbms = round((float) ($data['retencion'] ?? 0), 2);
        $retIsr = round((float) ($data['retencion_isr'] ?? 0), 2);
        $retencion = round($retItbms + $retIsr, 2);
        $descuento = round((float) ($data['descuento'] ?? 0), 2);

        $totalLiquidado = round($aplicar->sum('monto'), 2);
        $totalCreditos = round($creditos->sum('monto'), 2);

        // Los créditos a favor no pueden financiar más de lo que se está liquidando.
        if ($totalCreditos > $totalLiquidado + 0.004) {
            throw ValidationException::withMessages([
                'creditos' => 'Los créditos aplicados (B/. '.number_format($totalCreditos, 2).') exceden el total a liquidar (B/. '.number_format($totalLiquidado, 2).').',
            ]);
        }

        // El remanente (lo que no cubren los créditos) se financia con efectivo,
        // retención y/o descuento. Retención + descuento no pueden superarlo.
        $remanente = round($totalLiquidado - $totalCreditos, 2);

        if (round($retencion + $descuento, 2) > $remanente + 0.004) {
            $clave = $retencion > 0 ? 'retencion' : 'descuento';
            throw ValidationException::withMessages([
                $clave => 'La retención (B/. '.number_format($retencion, 2).') más el descuento (B/. '.number_format($descuento, 2).') no pueden exceder el monto a pagar en efectivo (B/. '.number_format($remanente, 2).').',
            ]);
        }

        $efectivo = round($remanente - $retencion - $descuento, 2);

        $resultado = DB::transaction(function () use (
            $companiaId, $data, $aplicar, $creditos, $totalLiquidado, $retItbms, $retIsr,
            $retencion, $descuento, $remanente, $efectivo, $cuentaCxpId, $usuario
        ) {
            // Bloquea las facturas y valida pertenencia y saldo.
            $facturas = CxpDocumento::where('compania_id', $companiaId)
                ->where('proveedor_id', $data['proveedor_id'])
                ->whereIn('tipo_documento', CxpDocumento::tiposPagables())
                ->whereIn('id', $aplicar->pluck('documento_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($aplicar as $apl) {
                $factura = $facturas->get($apl['documento_id']);

                if (! $factura) {
                    throw ValidationException::withMessages(['aplicaciones' => 'Una de las facturas no pertenece al proveedor seleccionado.']);
                }

                if ($apl['monto'] > round((float) $factura->saldo, 2) + 0.004) {
                    throw ValidationException::withMessages([
                        'aplicaciones' => "El monto aplicado a {$factura->numero} (B/. {$apl['monto']}) excede su saldo (B/. {$factura->saldo}).",
                    ]);
                }
            }

            // Bloquea y valida los créditos a favor (anticipos / notas de crédito).
            $creditoDocs = collect();

            if ($creditos->isNotEmpty()) {
                $creditoDocs = CxpDocumento::where('compania_id', $companiaId)
                    ->where('proveedor_id', $data['proveedor_id'])
                    ->whereIn('tipo_documento', CxpDocumento::tiposCredito())
                    ->whereIn('id', $creditos->pluck('documento_id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($creditos as $cr) {
                    $credito = $creditoDocs->get($cr['documento_id']);

                    if (! $credito) {
                        throw ValidationException::withMessages(['creditos' => 'Uno de los créditos no pertenece al proveedor seleccionado.']);
                    }

                    if ($credito->esAnulado() || round((float) $credito->saldo, 2) <= 0) {
                        throw ValidationException::withMessages(['creditos' => "El crédito {$credito->numero} no tiene saldo disponible."]);
                    }

                    if ($cr['monto'] > round((float) $credito->saldo, 2) + 0.004) {
                        throw ValidationException::withMessages([
                            'creditos' => "El monto aplicado del crédito {$credito->numero} (B/. {$cr['monto']}) excede su disponible (B/. {$credito->saldo}).",
                        ]);
                    }
                }
            }

            // Remanente por factura (mutable): se va consumiendo al asignar créditos.
            $remPorFactura = [];
            foreach ($aplicar as $apl) {
                $remPorFactura[$apl['documento_id']] = $apl['monto'];
            }

            // Aplica cada crédito FIFO sobre las facturas en orden.
            $creditoAplicIds = [];

            foreach ($creditos as $cr) {
                $credito = $creditoDocs->get($cr['documento_id']);
                $restante = $cr['monto'];
                $asignaciones = [];

                foreach ($remPorFactura as $facturaId => $rem) {
                    if ($restante <= 0.004) {
                        break;
                    }
                    if ($rem <= 0.004) {
                        continue;
                    }
                    $aplica = round(min($restante, $rem), 2);
                    if ($aplica <= 0) {
                        continue;
                    }
                    $asignaciones[$facturaId] = round(($asignaciones[$facturaId] ?? 0) + $aplica, 2);
                    $remPorFactura[$facturaId] = round($rem - $aplica, 2);
                    $restante = round($restante - $aplica, 2);
                }

                $creditoAplicIds = array_merge(
                    $creditoAplicIds,
                    $this->aplicarCredito($companiaId, $credito, $asignaciones, $data['fecha'], $cuentaCxpId, $usuario)
                );
            }

            // Remanente real tras asignar los créditos (a prueba de centavos): es
            // lo que financian el efectivo, la retención y el descuento.
            $remanente = round(array_sum($remPorFactura), 2);
            $efectivo = round($remanente - $retencion - $descuento, 2);

            // El remanente (no cubierto por créditos) se liquida con el pago.
            $pago = null;

            if ($remanente > 0.004) {
                $pago = CxpDocumento::create([
                    'compania_id' => $companiaId,
                    'proveedor_id' => $data['proveedor_id'],
                    'tipo_documento' => CxpDocumento::TIPO_PAGO,
                    'numero' => CxpDocumento::siguienteNumeroPago($companiaId),
                    'referencia' => $data['referencia'] ?? null,
                    'fecha' => $data['fecha'],
                    'subtotal' => $remanente,
                    'descuento' => $descuento,
                    'impuesto' => 0,
                    'retencion' => $retencion,
                    'retencion_itbms' => $retItbms,
                    'retencion_isr' => $retIsr,
                    'total' => $remanente,
                    'saldo' => 0,
                    'estado' => CxpDocumento::ESTADO_PAGADO,
                    'cuenta_pago_id' => (int) $data['cuenta_pago_id'],
                    'created_by' => $usuario->email,
                ]);

                foreach ($remPorFactura as $facturaId => $rem) {
                    if ($rem <= 0.004) {
                        continue;
                    }

                    CxpAplicacion::create([
                        'compania_id' => $companiaId,
                        'proveedor_id' => $data['proveedor_id'],
                        'documento_origen_id' => $pago->id,
                        'documento_destino_id' => $facturaId,
                        'fecha' => $data['fecha'],
                        'monto_aplicado' => $rem,
                        'created_by' => $usuario->email,
                    ]);
                }

                // Dr CXP (remanente); Cr Banco (efectivo) [+ Cr retenciones] [+ Cr descuento].
                $lineas = [[
                    'cuenta_id' => $cuentaCxpId,
                    'contacto_id' => (int) $data['proveedor_id'],
                    'descripcion' => "Pago {$pago->numero}",
                    'debito' => $remanente,
                    'credito' => 0,
                ]];

                if ($efectivo > 0) {
                    $lineas[] = [
                        'cuenta_id' => (int) $data['cuenta_pago_id'],
                        'descripcion' => "Pago {$pago->numero}",
                        'debito' => 0,
                        'credito' => $efectivo,
                    ];
                }

                if ($retItbms > 0) {
                    $lineas[] = [
                        'cuenta_id' => (int) $data['retencion_cuenta_id'],
                        'contacto_id' => (int) $data['proveedor_id'],
                        'descripcion' => "Retención ITBMS pago {$pago->numero}",
                        'debito' => 0,
                        'credito' => $retItbms,
                    ];
                }

                if ($retIsr > 0) {
                    $lineas[] = [
                        'cuenta_id' => (int) $data['retencion_isr_cuenta_id'],
                        'contacto_id' => (int) $data['proveedor_id'],
                        'descripcion' => "Retención ISR pago {$pago->numero}",
                        'debito' => 0,
                        'credito' => $retIsr,
                    ];
                }

                if ($descuento > 0) {
                    $lineas[] = [
                        'cuenta_id' => (int) $data['descuento_cuenta_id'],
                        'descripcion' => "Descuento pronto pago {$pago->numero}",
                        'debito' => 0,
                        'credito' => $descuento,
                    ];
                }

                $asiento = app(AsientoAutomatico::class)->postear(
                    $companiaId,
                    $data['fecha'],
                    "Pago {$pago->numero} — ".$pago->proveedor->nombre,
                    $data['referencia'] ?? $pago->numero,
                    $lineas,
                    'CXP',
                    'cxp_documentos',
                    $pago->id,
                    $usuario,
                );

                $pago->update(['asiento_id' => $asiento->id]);

                // Etiqueta las aplicaciones de crédito con este pago para poder
                // anularlo/corregirlo (efectivo + créditos) como una sola operación.
                if ($creditoAplicIds) {
                    CxpAplicacion::whereIn('id', $creditoAplicIds)->update(['pago_id' => $pago->id]);
                }

                // Integración con Bancos: si la cuenta de pago es una cuenta
                // bancaria registrada, refleja el egreso para la conciliación.
                if ($efectivo > 0) {
                    $this->registrarEgresoBancario($companiaId, $pago, (int) $data['cuenta_pago_id'], $efectivo, $asiento, $usuario);
                }
            }

            // Reduce el saldo de cada factura por el total liquidado (créditos + pago).
            foreach ($aplicar as $apl) {
                $factura = $facturas->get($apl['documento_id']);
                $factura->saldo = round((float) $factura->saldo - $apl['monto'], 2);
                $factura->estado = $factura->estadoSegunSaldo();
                $factura->updated_by = $usuario->email;
                $factura->save();
            }

            return $pago;
        });

        $partes = [];
        if ($efectivo > 0) {
            $partes[] = 'efectivo B/. '.number_format($efectivo, 2);
        }
        if ($totalCreditos > 0) {
            $partes[] = 'créditos B/. '.number_format($totalCreditos, 2);
        }
        if ($retencion > 0) {
            $partes[] = 'retención B/. '.number_format($retencion, 2);
        }
        if ($descuento > 0) {
            $partes[] = 'descuento B/. '.number_format($descuento, 2);
        }
        $detalle = $partes ? ' ('.implode(' + ', $partes).')' : '';

        if ($resultado) {
            return redirect()->route('admin.cxp.pagos.show', $resultado)
                ->with('status', "Pago {$resultado->numero} registrado: se liquidaron B/. ".number_format($totalLiquidado, 2).$detalle.'.');
        }

        // Sin efectivo: la liquidación se cubrió por completo con créditos a favor.
        return redirect()->route('admin.cxp.facturas.index')
            ->with('status', 'Se liquidaron B/. '.number_format($totalLiquidado, 2).' aplicando créditos a favor del proveedor'.$detalle.'.');
    }

    /**
     * Aplica un crédito a favor (anticipo o nota de crédito) sobre un conjunto de
     * facturas. Crea las aplicaciones y reduce el disponible del crédito.
     *
     * - Anticipo: el dinero ya salió a un activo; consumirlo cancela deuda:
     *   Dr CXP / Cr ANTICIPO_PROVEEDOR (un asiento por el total asignado).
     * - Nota de crédito contabilizada: su asiento original ya hizo Dr CXP, así
     *   que aplicarla es solo un movimiento de submayor (sin asiento nuevo).
     *
     * NOTA: no reduce el saldo de las facturas; el llamador lo hace una sola vez
     * por el total liquidado (créditos + efectivo).
     *
     * @param  array<int, float>  $asignaciones  facturaId => monto
     * @return list<int>  ids de las aplicaciones creadas (para etiquetarlas con el pago)
     */
    private function aplicarCredito(int $companiaId, CxpDocumento $credito, array $asignaciones, string $fecha, int $cuentaCxpId, $usuario): array
    {
        $totalAsignado = round(array_sum($asignaciones), 2);

        if ($totalAsignado <= 0) {
            return [];
        }

        $asientoId = null;

        if ($credito->tipo_documento === CxpDocumento::TIPO_ANTICIPO) {
            $cuentaAnticipoId = CuentaDefault::idPara($companiaId, 'ANTICIPO_PROVEEDOR');

            if (! $cuentaAnticipoId) {
                throw ValidationException::withMessages([
                    'creditos' => 'La compañía no tiene configurada la cuenta default ANTICIPO_PROVEEDOR para aplicar el anticipo.',
                ]);
            }

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId,
                $fecha,
                "Aplicación de anticipo {$credito->numero} — ".$credito->proveedor->nombre,
                $credito->numero,
                [[
                    'cuenta_id' => $cuentaCxpId,
                    'contacto_id' => $credito->proveedor_id,
                    'descripcion' => "Aplicación anticipo {$credito->numero}",
                    'debito' => $totalAsignado,
                    'credito' => 0,
                ], [
                    'cuenta_id' => $cuentaAnticipoId,
                    'contacto_id' => $credito->proveedor_id,
                    'descripcion' => "Aplicación anticipo {$credito->numero}",
                    'debito' => 0,
                    'credito' => $totalAsignado,
                ]],
                'CXP',
                'cxp_documentos',
                $credito->id,
                $usuario,
            );

            $asientoId = $asiento->id;
        }

        $ids = [];

        foreach ($asignaciones as $facturaId => $monto) {
            if (round((float) $monto, 2) <= 0) {
                continue;
            }

            $aplicacion = CxpAplicacion::create([
                'compania_id' => $companiaId,
                'proveedor_id' => $credito->proveedor_id,
                'documento_origen_id' => $credito->id,
                'documento_destino_id' => $facturaId,
                'fecha' => $fecha,
                'monto_aplicado' => round((float) $monto, 2),
                'asiento_id' => $asientoId,
                'created_by' => $usuario->email,
            ]);

            $ids[] = $aplicacion->id;
        }

        $credito->saldo = round((float) $credito->saldo - $totalAsignado, 2);
        $credito->estado = $credito->estadoSegunSaldo();
        $credito->updated_by = $usuario->email;
        $credito->save();

        return $ids;
    }

    /**
     * Refleja el egreso en el módulo de Bancos cuando la cuenta de pago es una
     * cuenta bancaria registrada, para que el pago aparezca en la conciliación.
     */
    private function registrarEgresoBancario(int $companiaId, CxpDocumento $pago, int $cuentaPagoId, float $efectivo, Asiento $asiento, $usuario): void
    {
        $bancaria = BcoCuenta::where('compania_id', $companiaId)
            ->where('cuenta_contable_id', $cuentaPagoId)
            ->where('activa', true)
            ->first();

        if (! $bancaria) {
            return;
        }

        BcoMovimiento::create([
            'compania_id' => $companiaId,
            'cuenta_bancaria_id' => $bancaria->id,
            'fecha' => $pago->fecha->format('Y-m-d'),
            'tipo_movimiento' => BcoMovimiento::TIPO_PAGO,
            'descripcion' => "Pago {$pago->numero} — ".($pago->proveedor->nombre ?? ''),
            'referencia' => $pago->referencia ?: $pago->numero,
            'debito' => $efectivo,
            'credito' => 0,
            'contacto_id' => $pago->proveedor_id,
            'conciliado' => false,
            'asiento_id' => $asiento->id,
            'documento_origen' => 'cxp_documentos',
            'documento_id' => $pago->id,
            'created_by' => $usuario->email,
        ]);
    }

    public function show(Request $request, CxpDocumento $documento): View
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($documento->tipo_documento === CxpDocumento::TIPO_PAGO, 404);

        $documento->load(['proveedor', 'asiento', 'cuentaPago', 'aplicacionesComoOrigen.destino']);

        return view('admin.cxp.pagos.show', ['pago' => $documento]);
    }

    public function imprimir(Request $request, CxpDocumento $documento): View
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($documento->tipo_documento === CxpDocumento::TIPO_PAGO, 404);

        $documento->load(['proveedor', 'asiento', 'cuentaPago', 'aplicacionesComoOrigen.destino']);
        $compania = Compania::find($documento->compania_id);

        return view('admin.cxp.pagos.print', ['pago' => $documento, 'compania' => $compania]);
    }

    public function anular(Request $request, CxpDocumento $documento): RedirectResponse
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($documento->tipo_documento === CxpDocumento::TIPO_PAGO, 404);

        if ($documento->esAnulado()) {
            return back()->withErrors(['documento' => 'El pago ya está anulado.']);
        }

        if ($error = $this->revertir($documento, $request->user())) {
            return back()->withErrors(['documento' => $error]);
        }

        return redirect()->route('admin.cxp.pagos.show', $documento)
            ->with('status', "Pago {$documento->numero} anulado; los saldos de las facturas fueron restaurados.");
    }

    /**
     * "Corregir" un pago ya registrado: lo anula como una sola operación
     * (restaura los saldos de las facturas, revierte los créditos aplicados,
     * reversa los asientos y elimina el movimiento bancario) y reabre el
     * formulario de captura prellenado —incluyendo los créditos a favor— para
     * registrar la corrección como un pago nuevo. El original queda ANULADO.
     */
    public function corregir(Request $request, CxpDocumento $documento): RedirectResponse
    {
        abort_unless($documento->compania_id === $this->companiaActivaId($request), 404);
        abort_unless($documento->tipo_documento === CxpDocumento::TIPO_PAGO, 404);

        if ($documento->esAnulado()) {
            return back()->withErrors(['documento' => 'El pago ya está anulado.']);
        }

        // Datos para reabrir el formulario (antes de que revertir borre todo).
        // Monto por factura = porción en efectivo (origen = pago) + porción
        // cubierta por créditos (aplicaciones etiquetadas con este pago).
        $efectivoPorFactura = $documento->aplicacionesComoOrigen()
            ->get(['documento_destino_id', 'monto_aplicado'])
            ->groupBy('documento_destino_id')
            ->map(fn ($g) => (float) $g->sum('monto_aplicado'));

        $aplicacionesCredito = CxpAplicacion::where('pago_id', $documento->id)
            ->get(['documento_destino_id', 'documento_origen_id', 'monto_aplicado']);

        $creditoPorFactura = $aplicacionesCredito
            ->groupBy('documento_destino_id')
            ->map(fn ($g) => (float) $g->sum('monto_aplicado'));

        $aplicaciones = $efectivoPorFactura->keys()
            ->merge($creditoPorFactura->keys())
            ->unique()
            ->map(fn ($facturaId) => [
                'documento_id' => (int) $facturaId,
                'monto' => number_format((float) ($efectivoPorFactura->get($facturaId, 0) + $creditoPorFactura->get($facturaId, 0)), 2, '.', ''),
            ])->values()->all();

        $creditos = $aplicacionesCredito
            ->groupBy('documento_origen_id')
            ->map(fn ($g, $creditoId) => [
                'documento_id' => (int) $creditoId,
                'monto' => number_format((float) $g->sum('monto_aplicado'), 2, '.', ''),
            ])->values()->all();

        if ($error = $this->revertir($documento, $request->user())) {
            return back()->withErrors(['documento' => $error]);
        }

        return redirect()->route('admin.cxp.pagos.create', ['proveedor_id' => $documento->proveedor_id])
            ->withInput([
                'proveedor_id' => $documento->proveedor_id,
                'fecha' => $documento->fecha->format('Y-m-d'),
                'cuenta_pago_id' => $documento->cuenta_pago_id,
                'referencia' => $documento->referencia,
                'retencion' => (float) $documento->retencion_itbms > 0 ? number_format((float) $documento->retencion_itbms, 2, '.', '') : null,
                'retencion_isr' => (float) $documento->retencion_isr > 0 ? number_format((float) $documento->retencion_isr, 2, '.', '') : null,
                'descuento' => (float) $documento->descuento > 0 ? number_format((float) $documento->descuento, 2, '.', '') : null,
                'aplicaciones' => $aplicaciones,
                'creditos' => $creditos,
            ])
            ->with('status', "Pago {$documento->numero} anulado para corrección y saldos restaurados. Ajusta los montos y registra de nuevo (se asignará un número nuevo).");
    }

    /**
     * Reversa un pago como una sola operación: valida que no tenga movimiento
     * bancario conciliado; restaura los saldos de las facturas; revierte los
     * créditos aplicados dentro del pago (restaura el disponible del anticipo /
     * nota de crédito y reversa el asiento de aplicación del anticipo); reversa
     * el asiento del pago; elimina el movimiento bancario y deja el pago
     * ANULADO. Devuelve un mensaje de error si no se puede revertir, o null si
     * se revirtió correctamente.
     */
    private function revertir(CxpDocumento $documento, $usuario): ?string
    {
        $movimientos = BcoMovimiento::where('compania_id', $documento->compania_id)
            ->where('documento_origen', 'cxp_documentos')
            ->where('documento_id', $documento->id)
            ->get();

        if ($movimientos->firstWhere('conciliado', true)) {
            return 'El pago tiene un movimiento bancario conciliado; quita la conciliación antes de anularlo.';
        }

        DB::transaction(function () use ($documento, $usuario, $movimientos) {
            // 1) Reversa la porción en efectivo (aplicaciones cuyo origen es el pago).
            foreach ($documento->aplicacionesComoOrigen()->with('destino')->lockForUpdate()->get() as $aplicacion) {
                $factura = $aplicacion->destino;
                $factura->saldo = round((float) $factura->saldo + (float) $aplicacion->monto_aplicado, 2);
                $factura->estado = $factura->estadoSegunSaldo();
                $factura->updated_by = $usuario->email;
                $factura->save();

                $aplicacion->delete();
            }

            // 2) Reversa los créditos aplicados dentro de este pago. Se acumulan
            //    los montos por factura y por crédito para no perder escrituras
            //    cuando un mismo documento aparece en varias aplicaciones.
            $deltaFactura = [];
            $deltaCredito = [];
            $asientosCredito = [];

            foreach (CxpAplicacion::where('pago_id', $documento->id)->lockForUpdate()->get() as $aplicacion) {
                $deltaFactura[$aplicacion->documento_destino_id] = round(($deltaFactura[$aplicacion->documento_destino_id] ?? 0) + (float) $aplicacion->monto_aplicado, 2);
                $deltaCredito[$aplicacion->documento_origen_id] = round(($deltaCredito[$aplicacion->documento_origen_id] ?? 0) + (float) $aplicacion->monto_aplicado, 2);

                if ($aplicacion->asiento_id) {
                    $asientosCredito[$aplicacion->asiento_id] = true;
                }

                $aplicacion->delete();
            }

            foreach ($deltaFactura as $facturaId => $monto) {
                $factura = CxpDocumento::lockForUpdate()->find($facturaId);
                if ($factura) {
                    $factura->saldo = round((float) $factura->saldo + $monto, 2);
                    $factura->estado = $factura->estadoSegunSaldo();
                    $factura->updated_by = $usuario->email;
                    $factura->save();
                }
            }

            foreach ($deltaCredito as $creditoId => $monto) {
                $credito = CxpDocumento::lockForUpdate()->find($creditoId);
                if ($credito) {
                    $credito->saldo = round((float) $credito->saldo + $monto, 2);
                    $credito->estado = $credito->estadoSegunSaldo();
                    $credito->updated_by = $usuario->email;
                    $credito->save();
                }
            }

            foreach (array_keys($asientosCredito) as $asientoId) {
                app(AsientoAutomatico::class)->anular(Asiento::find($asientoId), $usuario);
            }

            // 3) Reversa el asiento del pago y el movimiento bancario.
            foreach ($movimientos as $movimiento) {
                $movimiento->delete();
            }

            app(AsientoAutomatico::class)->anular($documento->asiento, $usuario);

            $documento->update([
                'estado' => CxpDocumento::ESTADO_ANULADO,
                'saldo' => 0,
                'updated_by' => $usuario->email,
            ]);
        });

        return null;
    }
}
