<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Models\CuentaDefault;
use App\Models\CxcDocumento;
use App\Models\VentaNotaDebito;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Nota de débito de venta: cargo adicional al cliente (aumenta lo que debe).
 * Se crea desde el selector de tipo en "Nueva factura de venta". Contablemente:
 * Dr CxC; Cr cuenta contrapartida (ingreso/otro). Genera saldo cobrable propio.
 */
class VentaNotaDebitoController extends Controller
{
    use ConCompaniaActiva;

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario    = $request->user();

        $data = $request->validate([
            'cliente_id' => ['required', 'integer'],
            'fecha'      => ['required', 'date'],
            'motivo'        => ['required', 'string', 'max:500'],
            'total'         => ['required', 'numeric', 'min:0.01'],
            'cuenta_id'     => ['required', 'integer', 'exists:cgl_cuentas,id'],
            'factura_ref_id' => ['nullable', 'integer'],
            'tipo_fel'      => ['nullable', 'in:05,07'],
        ]);

        $total       = round((float) $data['total'], 2);
        $cuentaCxcId = CuentaDefault::idPara($companiaId, 'CXC');
        // Código DGI: 05 si referencia una factura, 07 (genérica) si no.
        $tipoFel     = $data['tipo_fel'] ?? (! empty($data['factura_ref_id']) ? '05' : '07');
        $extra       = array_filter([
            'tipo_fel'       => $tipoFel,
            'factura_ref_id' => $data['factura_ref_id'] ?? null,
        ], fn ($v) => $v !== null);

        $nota = DB::transaction(function () use ($companiaId, $data, $total, $cuentaCxcId, $extra, $usuario) {
            $cxc = CxcDocumento::create([
                'compania_id'    => $companiaId,
                'cliente_id'     => $data['cliente_id'],
                'tipo_documento' => CxcDocumento::TIPO_NOTA_DEBITO,
                'numero'         => CxcDocumento::siguienteNumero($companiaId, CxcDocumento::TIPO_NOTA_DEBITO),
                'fecha'          => $data['fecha'],
                'subtotal'       => $total,
                'impuesto'       => 0,
                'total'          => $total,
                'saldo'          => $total,
                'estado'         => CxcDocumento::ESTADO_PENDIENTE,
                'created_by'     => $usuario->email,
            ]);

            $nota = VentaNotaDebito::create([
                'compania_id'      => $companiaId,
                'cliente_id'       => $data['cliente_id'],
                'numero'           => VentaNotaDebito::siguienteNumero($companiaId),
                'fecha'            => $data['fecha'],
                'motivo'           => $data['motivo'],
                'total'            => $total,
                'saldo'            => $total,
                'cxc_documento_id' => $cxc->id,
                'estado'           => VentaNotaDebito::ESTADO_EMITIDA,
                'extra'            => $extra,
                'created_by'       => $usuario->email,
                'updated_by'       => $usuario->email,
            ]);

            // Dr CxC (aumenta deuda cliente); Cr cuenta contrapartida.
            $nombreCliente = Contacto::find($data['cliente_id'])?->nombre ?? '';
            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId,
                $data['fecha'],
                "ND Ventas {$nota->numero} — {$nombreCliente}",
                $nota->numero,
                [
                    [
                        'cuenta_id'   => $cuentaCxcId,
                        'contacto_id' => (int) $data['cliente_id'],
                        'descripcion' => "Nota débito {$nota->numero}",
                        'debito'      => $total,
                        'credito'     => 0,
                    ],
                    [
                        'cuenta_id'   => (int) $data['cuenta_id'],
                        'descripcion' => "Nota débito {$nota->numero}",
                        'debito'      => 0,
                        'credito'     => $total,
                    ],
                ],
                'VENTAS',
                'ventas_facturas',
                $nota->id,
                $usuario,
            );

            $nota->update(['asiento_id' => $asiento->id]);
            $cxc->update(['asiento_id' => $asiento->id]);

            return $nota;
        });

        return redirect()->route('admin.ventas.notas-debito.show', $nota)
            ->with('status', "Nota de débito {$nota->numero} emitida por B/. ".number_format($total, 2).'.');
    }

    public function show(Request $request, VentaNotaDebito $notaDebito): View
    {
        abort_unless($notaDebito->compania_id === $this->companiaActivaId($request), 404);

        $notaDebito->load(['cliente', 'asiento', 'cxcDocumento']);

        return view('admin.ventas.notas-debito.show', ['nota' => $notaDebito]);
    }

    public function anular(Request $request, VentaNotaDebito $notaDebito): RedirectResponse
    {
        abort_unless($notaDebito->compania_id === $this->companiaActivaId($request), 404);

        if ($notaDebito->esAnulada()) {
            return back()->withErrors(['nota' => 'La nota de débito ya está anulada.']);
        }

        if ($notaDebito->cxcDocumento && $notaDebito->cxcDocumento->aplicacionesComoDestino()->exists()) {
            return back()->withErrors(['nota' => 'La nota de débito tiene cobros aplicados; anúlalos primero.']);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($notaDebito, $usuario) {
            if ($notaDebito->asiento) {
                app(AsientoAutomatico::class)->anular($notaDebito->asiento, $usuario);
            }

            if ($notaDebito->cxcDocumento) {
                $notaDebito->cxcDocumento->update([
                    'estado'     => CxcDocumento::ESTADO_ANULADO,
                    'saldo'      => 0,
                    'updated_by' => $usuario->email,
                ]);
            }

            $notaDebito->update([
                'estado'     => VentaNotaDebito::ESTADO_ANULADA,
                'saldo'      => 0,
                'updated_by' => $usuario->email,
            ]);
        });

        return redirect()->route('admin.ventas.notas-debito.show', $notaDebito)
            ->with('status', "Nota {$notaDebito->numero} anulada.");
    }
}
