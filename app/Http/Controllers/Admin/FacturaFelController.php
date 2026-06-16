<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\FelConfiguracion;
use App\Models\FelDocumento;
use App\Models\FelDocumentoDetalle;
use App\Services\FelDocumentoBuilder;
use App\Services\FelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FacturaFelController extends Controller
{
    public function index(Request $request): View
    {
        $compania = $this->companiaActiva($request);

        $documentos = FelDocumento::with('cliente')
            ->where('compania_id', $compania->id)
            ->orderByDesc('id')
            ->paginate(15);

        $config = FelConfiguracion::firstWhere('compania_id', $compania->id);

        return view('admin.fel.index', compact('documentos', 'compania', 'config'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('fel.gestionar'), 403);
        $compania = $this->companiaActiva($request);

        $config = FelConfiguracion::firstWhere('compania_id', $compania->id);
        $clientes = Contacto::where('compania_id', $compania->id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'identificacion', 'dv']);

        return view('admin.fel.create', [
            'compania' => $compania,
            'config' => $config,
            'clientes' => $clientes,
            'tasas' => ['00' => 'Exento (0%)', '01' => 'ITBMS 7%', '02' => 'ITBMS 10%', '03' => 'ITBMS 15%'],
            'formasPago' => FelDocumentoBuilder::FORMAS_PAGO,
            'tiposDocumento' => FelDocumentoBuilder::TIPOS_DOCUMENTO,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('fel.gestionar'), 403);
        $compania = $this->companiaActiva($request);

        $config = FelConfiguracion::firstWhere('compania_id', $compania->id);
        if (! $config || ! $config->token_empresa) {
            return redirect()->route('admin.fel.configuracion')
                ->withErrors(['fel' => 'Configura primero los tokens de The Factory HKA.']);
        }

        $data = $request->validate([
            'tipo_documento' => ['required', Rule::in(array_keys(FelDocumentoBuilder::TIPOS_DOCUMENTO))],
            'cliente_id' => ['nullable', 'integer', Rule::exists('contact_contactos', 'id')->where('compania_id', $compania->id)],
            'forma_pago' => ['required', Rule::in(array_keys(FelDocumentoBuilder::FORMAS_PAGO))],
            'informacion_interes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['required', 'string', 'max:500'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.precio' => ['required', 'numeric', 'gte:0'],
            'items.*.tasa' => ['required', Rule::in(['00', '01', '02', '03'])],
        ]);

        $cliente = $data['cliente_id'] ? Contacto::find($data['cliente_id']) : null;
        $builder = new FelDocumentoBuilder();
        $usuario = $request->user()->email;

        // correlativo con bloqueo para evitar números duplicados (consecutivo
        // único compartido cuando se usan las credenciales demo de HKA).
        $numeroFiscal = DB::transaction(fn () => $config->siguienteNumeroFiscal());

        $documento = $builder->facturaInterna($compania, $config, $cliente, $data, $numeroFiscal);

        $totales = $documento['totalesSubTotales'];
        $fel = FelDocumento::create([
            'compania_id' => $compania->id,
            'tipo_documento' => $data['tipo_documento'],
            'documento_origen' => 'fel_manual',
            'documento_id' => 0,
            'numero' => (string) $numeroFiscal,
            'fecha' => now()->toDateString(),
            'cliente_id' => $cliente?->id,
            'subtotal' => $totales['totalPrecioNeto'],
            'itbms' => $totales['totalITBMS'],
            'total' => $totales['totalFactura'],
            'estado_fel' => 'PENDIENTE',
            'created_by' => $usuario,
        ]);

        foreach ($data['items'] as $i => $l) {
            FelDocumentoDetalle::create([
                'fel_documento_id' => $fel->id,
                'linea' => $i + 1,
                'descripcion' => $l['descripcion'],
                'cantidad' => $l['cantidad'],
                'precio_unitario' => $l['precio'],
                'impuesto_monto' => round($l['cantidad'] * $l['precio'] * FelDocumentoBuilder::TASAS_ITBMS[$l['tasa']], 2),
                'total_linea' => round($l['cantidad'] * $l['precio'] * (1 + FelDocumentoBuilder::TASAS_ITBMS[$l['tasa']]), 2),
                'created_by' => $usuario,
            ]);
        }

        $resp = (new FelService($config))->enviar($documento);
        $this->registrarEvento($fel, 'ENVIO', $resp, $usuario);

        $codigo = (string) ($resp['codigo'] ?? $resp['EnviarResult']['codigo'] ?? '');
        $resultado = $resp['EnviarResult'] ?? $resp;

        if ($codigo === '200' || ($resultado['resultado'] ?? '') === 'Procesado') {
            $fel->update([
                'estado_fel' => 'AUTORIZADO',
                'cufe' => $resultado['cufe'] ?? null,
                'qr' => $resultado['qr'] ?? null,
                'respuesta_dgi' => $resp,
                'fecha_envio' => now(),
                'updated_by' => $usuario,
            ]);

            return redirect()->route('admin.fel.index')
                ->with('status', "Factura {$numeroFiscal} autorizada por la DGI. CUFE: ".substr((string) ($resultado['cufe'] ?? ''), 0, 40).'…');
        }

        $fel->update([
            'estado_fel' => 'RECHAZADO',
            'respuesta_dgi' => $resp,
            'fecha_envio' => now(),
            'updated_by' => $usuario,
        ]);

        $mensaje = $resultado['mensaje'] ?? $resp['mensaje'] ?? 'Sin detalle';

        return redirect()->route('admin.fel.index')
            ->withErrors(['fel' => "Factura {$numeroFiscal} rechazada: {$mensaje}"]);
    }

    /** Descarga el CAFE (PDF) desde el PAC. */
    public function pdf(Request $request, FelDocumento $documento)
    {
        $compania = $this->companiaActiva($request);
        abort_unless($documento->compania_id === $compania->id, 404);

        $config = FelConfiguracion::firstWhere('compania_id', $compania->id);
        $builder = new FelDocumentoBuilder();
        $resp = (new FelService($config))->descargaPDF($builder->datosDocumento($config, $documento->numero, $documento->tipo_documento));

        $resultado = $resp['DescargaPDFResult'] ?? $resp;
        $base64 = $resultado['documento'] ?? $resultado['pdf'] ?? null;

        if (! $base64) {
            return back()->withErrors(['fel' => 'El PAC no devolvió el PDF: '.($resultado['mensaje'] ?? 'sin detalle')]);
        }

        return response(base64_decode($base64), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="FEL-'.$documento->numero.'.pdf"',
        ]);
    }

    /** Anula un documento autorizado ante la DGI. */
    public function anular(Request $request, FelDocumento $documento): RedirectResponse
    {
        abort_unless($request->user()->can('fel.gestionar'), 403);
        $compania = $this->companiaActiva($request);
        abort_unless($documento->compania_id === $compania->id, 404);

        if ($documento->estado_fel !== 'AUTORIZADO') {
            return back()->withErrors(['fel' => 'Solo se pueden anular documentos autorizados.']);
        }

        $config = FelConfiguracion::firstWhere('compania_id', $compania->id);
        $builder = new FelDocumentoBuilder();
        $motivo = (string) $request->input('motivo', 'Anulación solicitada por el emisor');
        $resp = (new FelService($config))->anulacionDocumento($builder->datosDocumento($config, $documento->numero, $documento->tipo_documento), $motivo);
        $this->registrarEvento($documento, 'ANULACION', $resp, $request->user()->email);

        $resultado = $resp['AnulacionDocumentoResult'] ?? $resp;
        $codigo = (string) ($resultado['codigo'] ?? '');

        if ($codigo === '200' || ($resultado['resultado'] ?? '') === 'Procesado') {
            $documento->update(['estado_fel' => 'ANULADO', 'updated_by' => $request->user()->email]);

            return back()->with('status', "Documento {$documento->numero} anulado.");
        }

        return back()->withErrors(['fel' => 'No se pudo anular: '.($resultado['mensaje'] ?? 'sin detalle')]);
    }

    private function registrarEvento(FelDocumento $fel, string $evento, array $respuesta, string $usuario): void
    {
        DB::table('fel_eventos')->insert([
            'fel_documento_id' => $fel->id,
            'evento' => $evento,
            'descripcion' => $respuesta['mensaje'] ?? null,
            'respuesta' => json_encode($respuesta, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
            'created_by' => $usuario,
        ]);
    }

    private function companiaActiva(Request $request): Compania
    {
        $companiaId = session('compania_activa_id');
        abort_if(! $companiaId, 404, 'No hay compañía activa.');
        abort_unless(
            $request->user()->is_admin || $request->user()->companiasAccesibles()->contains('id', (int) $companiaId),
            403
        );

        return Compania::findOrFail($companiaId);
    }
}
