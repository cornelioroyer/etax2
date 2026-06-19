<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ConCompaniaActiva;
use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Models\CuentaDefault;
use App\Models\CxcDocumento;
use App\Models\VentaReembolso;
use App\Services\AsientoAutomatico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Reembolso de venta (DGI tipo 09): se cobra al cliente un gasto pagado a su
 * cuenta. Se crea desde el selector de tipo en "Nueva factura de venta".
 * Contablemente: Dr CxC; Cr cuenta de reembolso (recuperación de gasto).
 * Genera saldo cobrable propio, como una factura.
 */
class VentaReembolsoController extends Controller
{
    use ConCompaniaActiva;

    public function store(Request $request): RedirectResponse
    {
        $companiaId = $this->companiaActivaId($request);
        $usuario    = $request->user();

        $data = $request->validate([
            'cliente_id' => ['required', 'integer'],
            'fecha'      => ['required', 'date'],
            'motivo'     => ['required', 'string', 'max:500'],
            'total'      => ['required', 'numeric', 'min:0.01'],
            'cuenta_id'  => ['required', 'integer', 'exists:cgl_cuentas,id'],
        ]);

        $total       = round((float) $data['total'], 2);
        $cuentaCxcId = CuentaDefault::idPara($companiaId, 'CXC');

        $doc = DB::transaction(function () use ($companiaId, $data, $total, $cuentaCxcId, $usuario) {
            $cxc = CxcDocumento::create([
                'compania_id'    => $companiaId,
                'cliente_id'     => $data['cliente_id'],
                'tipo_documento' => CxcDocumento::TIPO_REEMBOLSO,
                'numero'         => CxcDocumento::siguienteNumero($companiaId, CxcDocumento::TIPO_REEMBOLSO),
                'fecha'          => $data['fecha'],
                'subtotal'       => $total,
                'impuesto'       => 0,
                'total'          => $total,
                'saldo'          => $total,
                'estado'         => CxcDocumento::ESTADO_PENDIENTE,
                'created_by'     => $usuario->email,
            ]);

            $doc = VentaReembolso::create([
                'compania_id'      => $companiaId,
                'cliente_id'       => $data['cliente_id'],
                'numero'           => VentaReembolso::siguienteNumero($companiaId),
                'fecha'            => $data['fecha'],
                'motivo'           => $data['motivo'],
                'total'            => $total,
                'saldo'            => $total,
                'cxc_documento_id' => $cxc->id,
                'estado'           => VentaReembolso::ESTADO_EMITIDA,
                'extra'            => ['tipo_fel' => '09'],
                'created_by'       => $usuario->email,
                'updated_by'       => $usuario->email,
            ]);

            // Dr CxC (aumenta deuda cliente); Cr cuenta de reembolso (contrapartida).
            $nombreCliente = Contacto::find($data['cliente_id'])?->nombre ?? '';
            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId,
                $data['fecha'],
                "Reembolso Ventas {$doc->numero} — {$nombreCliente}",
                $doc->numero,
                [
                    [
                        'cuenta_id'   => $cuentaCxcId,
                        'contacto_id' => (int) $data['cliente_id'],
                        'descripcion' => "Reembolso {$doc->numero}",
                        'debito'      => $total,
                        'credito'     => 0,
                    ],
                    [
                        'cuenta_id'   => (int) $data['cuenta_id'],
                        'descripcion' => "Reembolso {$doc->numero}",
                        'debito'      => 0,
                        'credito'     => $total,
                    ],
                ],
                'VENTAS',
                'ventas_facturas',
                $doc->id,
                $usuario,
            );

            $doc->update(['asiento_id' => $asiento->id]);
            $cxc->update(['asiento_id' => $asiento->id]);

            return $doc;
        });

        return redirect()->route('admin.ventas.reembolsos.show', $doc)
            ->with('status', "Reembolso {$doc->numero} emitido por B/. ".number_format($total, 2).'.');
    }

    public function show(Request $request, VentaReembolso $reembolso): View
    {
        abort_unless($reembolso->compania_id === $this->companiaActivaId($request), 404);

        $reembolso->load(['cliente', 'asiento', 'cxcDocumento']);

        return view('admin.ventas.reembolsos.show', ['doc' => $reembolso]);
    }

    public function anular(Request $request, VentaReembolso $reembolso): RedirectResponse
    {
        abort_unless($reembolso->compania_id === $this->companiaActivaId($request), 404);

        if ($reembolso->esAnulada()) {
            return back()->withErrors(['doc' => 'El reembolso ya está anulado.']);
        }

        if ($reembolso->cxcDocumento && $reembolso->cxcDocumento->aplicacionesComoDestino()->exists()) {
            return back()->withErrors(['doc' => 'El reembolso tiene cobros aplicados; anúlalos primero.']);
        }

        $usuario = $request->user();

        DB::transaction(function () use ($reembolso, $usuario) {
            if ($reembolso->asiento) {
                app(AsientoAutomatico::class)->anular($reembolso->asiento, $usuario);
            }

            if ($reembolso->cxcDocumento) {
                $reembolso->cxcDocumento->update([
                    'estado'     => CxcDocumento::ESTADO_ANULADO,
                    'saldo'      => 0,
                    'updated_by' => $usuario->email,
                ]);
            }

            $reembolso->update([
                'estado'     => VentaReembolso::ESTADO_ANULADA,
                'saldo'      => 0,
                'updated_by' => $usuario->email,
            ]);
        });

        return redirect()->route('admin.ventas.reembolsos.show', $reembolso)
            ->with('status', "Reembolso {$reembolso->numero} anulado.");
    }
}
