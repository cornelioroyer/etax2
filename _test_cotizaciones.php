<?php
// Prueba del módulo de cotizaciones de venta
use App\Models\Contacto;
use App\Models\TaxImpuesto;
use App\Models\User;
use App\Models\VentaCotizacion;
use App\Models\VentaCotizacionDetalle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$usuario = User::where('is_admin', true)->first();
Auth::login($usuario);
$companiaId = 1;

// 1. Verificar impuestos seeded
$impuestos = TaxImpuesto::itbmsGlobales();
echo "1. TaxImpuesto ITBMS globales: " . $impuestos->count() . " registros ("
    . $impuestos->pluck('porcentaje')->join('%, ') . "%)\n";

// 2. Obtener un cliente
$cliente = Contacto::where('compania_id', $companiaId)
    ->whereHas('tipos', fn ($q) => $q->where('codigo', 'CLIENTE'))
    ->first();
echo "2. Cliente: " . ($cliente?->nombre ?? 'NO ENCONTRADO') . "\n";

if (! $cliente) {
    echo "SKIP: no hay clientes en compania 1\n";
    return;
}

$imp7 = $impuestos->firstWhere('codigo', 'ITBMS_7');
$imp0 = $impuestos->firstWhere('codigo', 'ITBMS_0');

// 3. Crear cotización
$cotizacion = DB::transaction(function () use ($companiaId, $cliente, $imp7, $imp0, $usuario) {
    $cot = VentaCotizacion::create([
        'compania_id' => $companiaId,
        'cliente_id'  => $cliente->id,
        'numero'      => VentaCotizacion::siguienteNumero($companiaId),
        'fecha'       => now()->format('Y-m-d'),
        'fecha_validez' => now()->addDays(15)->format('Y-m-d'),
        'subtotal'    => 200.00,
        'descuento'   => 0,
        'itbms'       => 14.00,
        'total'       => 214.00,
        'estado'      => VentaCotizacion::ESTADO_BORRADOR,
        'extra'       => ['notas' => 'Prueba automatizada'],
        'created_by'  => $usuario->email,
    ]);

    VentaCotizacionDetalle::create([
        'cotizacion_id'   => $cot->id,
        'linea'           => 1,
        'descripcion'     => 'Servicio de consultoría',
        'cantidad'        => 1,
        'precio_unitario' => 100.00,
        'descuento'       => 0,
        'impuesto_id'     => $imp7->id,
        'impuesto_monto'  => 7.00,
        'total_linea'     => 107.00,
    ]);

    VentaCotizacionDetalle::create([
        'cotizacion_id'   => $cot->id,
        'linea'           => 2,
        'descripcion'     => 'Licencia de software',
        'cantidad'        => 1,
        'precio_unitario' => 100.00,
        'descuento'       => 0,
        'impuesto_id'     => $imp7->id,
        'impuesto_monto'  => 7.00,
        'total_linea'     => 107.00,
    ]);

    return $cot;
});

echo "3. Cotización creada: {$cotizacion->numero}, estado: {$cotizacion->estado}, total: B/. {$cotizacion->total}\n";
echo "   Detalle: " . $cotizacion->detalle()->count() . " líneas\n";
echo "   Notas: " . ($cotizacion->notas ?? '—') . "\n";

// 4. Cambiar estado: BORRADOR → ENVIADA
$cotizacion->update(['estado' => VentaCotizacion::ESTADO_ENVIADA]);
echo "4. Estado → ENVIADA: " . ($cotizacion->fresh()->estado === 'ENVIADA' ? 'OK' : 'FALLO') . "\n";

// 5. ENVIADA → ACEPTADA
$cotizacion->update(['estado' => VentaCotizacion::ESTADO_ACEPTADA]);
echo "5. Estado → ACEPTADA: " . ($cotizacion->fresh()->estado === 'ACEPTADA' ? 'OK' : 'FALLO') . "\n";

// 6. Carga con relación impuesto
$det = $cotizacion->detalle()->with('impuesto')->first();
echo "6. Detalle con impuesto: " . ($det->impuesto?->nombre ?? 'NULL') . " ({$det->impuesto->porcentaje}%)\n";

// 7. Anular
$cotizacion->update(['estado' => VentaCotizacion::ESTADO_ANULADA]);
echo "7. Anulada: " . ($cotizacion->fresh()->estado === 'ANULADA' ? 'OK' : 'FALLO') . "\n";

// Limpiar
$cotizacion->detalle()->delete();
$cotizacion->delete();
echo "Cotización de prueba eliminada.\n";
