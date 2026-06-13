<?php
// Prueba del ciclo completo: cotización → factura de venta → cxc_documentos + asiento
use App\Models\Contacto;
use App\Models\CuentaDefault;
use App\Models\CxcDocumento;
use App\Models\TaxImpuesto;
use App\Models\User;
use App\Models\VentaCotizacion;
use App\Models\VentaCotizacionDetalle;
use App\Models\VentaFactura;
use App\Models\VentaFacturaDetalle;
use App\Services\AsientoAutomatico;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$usuario    = User::where('is_admin', true)->first();
Auth::login($usuario);
$companiaId = 1;

$cliente = Contacto::where('compania_id', $companiaId)
    ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))->first();
$imp7 = TaxImpuesto::itbmsGlobales()->firstWhere('codigo', 'ITBMS_7');

// 1. Crear cotización
$cot = DB::transaction(function () use ($companiaId, $cliente, $imp7, $usuario) {
    $cot = VentaCotizacion::create([
        'compania_id' => $companiaId, 'cliente_id' => $cliente->id,
        'numero'      => VentaCotizacion::siguienteNumero($companiaId),
        'fecha'       => now()->format('Y-m-d'),
        'subtotal'    => 100.00, 'descuento' => 0, 'itbms' => 7.00, 'total' => 107.00,
        'estado'      => VentaCotizacion::ESTADO_ACEPTADA,
        'extra'       => [], 'created_by' => $usuario->email,
    ]);
    VentaCotizacionDetalle::create([
        'cotizacion_id' => $cot->id, 'linea' => 1,
        'descripcion' => 'Servicio de prueba', 'cantidad' => 1,
        'precio_unitario' => 100.00, 'descuento' => 0,
        'impuesto_id' => $imp7->id, 'impuesto_monto' => 7.00, 'total_linea' => 107.00,
    ]);
    return $cot;
});
echo "1. Cotización: {$cot->numero}, estado: {$cot->estado}, total: B/. {$cot->total}\n";

// 2. Verificar cuentas default
$cxcId    = CuentaDefault::idPara($companiaId, 'CXC');
$ventasId = CuentaDefault::idPara($companiaId, 'VENTAS');
$itbmsId  = CuentaDefault::idPara($companiaId, 'ITBMS_POR_PAGAR');
echo "2. Cuentas default: CXC={$cxcId}, VENTAS={$ventasId}, ITBMS_POR_PAGAR={$itbmsId}\n";

if (! $cxcId || ! $ventasId) {
    echo "FALLO: faltan cuentas default. Configura la compañía antes de facturar.\n";
    $cot->detalle()->delete(); $cot->delete();
    return;
}

// 3. Facturar (simular VentaCotizacionController::facturar)
$cot->load('detalle.impuesto', 'cliente');
$numero = VentaFactura::siguienteNumero($companiaId);

$factura = DB::transaction(function () use ($cot, $companiaId, $usuario, $numero, $cxcId, $ventasId, $itbmsId) {
    $factura = VentaFactura::create([
        'compania_id' => $companiaId, 'cliente_id' => $cot->cliente_id,
        'numero'      => $numero, 'fecha' => now()->format('Y-m-d'),
        'subtotal'    => $cot->subtotal, 'descuento' => $cot->descuento,
        'itbms'       => $cot->itbms, 'total' => $cot->total, 'saldo' => $cot->total,
        'estado'      => VentaFactura::ESTADO_EMITIDA,
        'cotizacion_id' => $cot->id, 'created_by' => $usuario->email,
    ]);

    VentaFacturaDetalle::create([
        'factura_id' => $factura->id, 'linea' => 1,
        'descripcion' => $cot->detalle[0]->descripcion,
        'cantidad' => $cot->detalle[0]->cantidad,
        'precio_unitario' => $cot->detalle[0]->precio_unitario,
        'descuento' => 0, 'impuesto_id' => $cot->detalle[0]->impuesto_id,
        'impuesto_monto' => $cot->detalle[0]->impuesto_monto,
        'total_linea' => $cot->detalle[0]->total_linea,
        'cuenta_ingreso_id' => $ventasId, 'created_by' => $usuario->email,
    ]);

    $cxc = CxcDocumento::create([
        'compania_id' => $companiaId, 'cliente_id' => $cot->cliente_id,
        'tipo_documento' => CxcDocumento::TIPO_FACTURA, 'numero' => $numero,
        'fecha' => now()->format('Y-m-d'),
        'subtotal' => $cot->subtotal, 'descuento' => 0,
        'impuesto' => $cot->itbms, 'total' => $cot->total, 'saldo' => $cot->total,
        'estado' => CxcDocumento::ESTADO_PENDIENTE, 'created_by' => $usuario->email,
    ]);

    $lineasAsiento = [
        ['cuenta_id' => $cxcId, 'contacto_id' => $cot->cliente_id, 'descripcion' => "Factura {$numero}", 'debito' => 107.00, 'credito' => 0],
        ['cuenta_id' => $ventasId, 'descripcion' => 'Servicio de prueba', 'debito' => 0, 'credito' => 100.00],
        ['cuenta_id' => $itbmsId, 'descripcion' => "ITBMS {$numero}", 'debito' => 0, 'credito' => 7.00],
    ];

    $asiento = app(AsientoAutomatico::class)->postear(
        $companiaId, now()->format('Y-m-d'),
        "Factura de venta {$numero}", $numero,
        $lineasAsiento, 'CXC', 'ventas_facturas', $factura->id, $usuario,
    );

    $factura->update(['cxc_documento_id' => $cxc->id, 'asiento_id' => $asiento->id]);
    $cxc->update(['asiento_id' => $asiento->id]);
    $cot->update(['estado' => VentaCotizacion::ESTADO_FACTURADA]);

    return $factura;
});

echo "3. Factura: {$factura->numero}, estado: {$factura->estado}, total: B/. {$factura->total}\n";
echo "   CxC vinculado: " . ($factura->cxc_documento_id ? "ID {$factura->cxc_documento_id}" : 'NO') . "\n";
echo "   Asiento: " . ($factura->asiento_id ? "ID {$factura->asiento_id}" : 'NO') . "\n";
echo "   Cotización ahora: " . $cot->fresh()->estado . "\n";

// 4. Verificar detalle de ventas_facturas_detalle
$det = $factura->detalle()->with('impuesto', 'cuentaIngreso')->first();
echo "4. Detalle: {$det->descripcion}, ITBMS: {$det->impuesto?->nombre}, cuenta ingreso: {$det->cuentaIngreso?->nombre}\n";

// 5. Verificar que aparece en listado CxC
$cxcFact = CxcDocumento::find($factura->cxc_documento_id);
echo "5. CxC saldo: B/. {$cxcFact->saldo}, estado: {$cxcFact->estado}\n";

// Cleanup
$det2 = $factura->detalle()->get();
foreach ($det2 as $d) $d->delete();
CxcDocumento::where('id', $factura->cxc_documento_id)->delete();
$factura->delete();
$cot->detalle()->delete();
$cot->delete();
echo "Limpieza OK.\n";
