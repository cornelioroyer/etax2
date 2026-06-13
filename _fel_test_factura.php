<?php
// Emite una factura FEL de prueba (consumidor final, 1 item) replicando FacturaFelController@store.

use App\Models\Compania;
use App\Models\FelConfiguracion;
use App\Models\FelDocumento;
use App\Models\FelDocumentoDetalle;
use App\Services\FelDocumentoBuilder;
use App\Services\FelService;
use Illuminate\Support\Facades\DB;

$compania = Compania::find(1);
$config = FelConfiguracion::firstWhere('compania_id', 1);
$usuario = 'cornelioroyer@winsof.com';

$data = [
    'forma_pago' => '02',
    'informacion_interes' => 'Factura de prueba ambiente demo',
    'items' => [
        ['descripcion' => 'Servicio de prueba FEL', 'cantidad' => 1, 'precio' => 100.00, 'tasa' => '01'],
    ],
];

$numeroFiscal = DB::transaction(function () use ($config) {
    $cfg = FelConfiguracion::lockForUpdate()->find($config->id);
    $cfg->correlativo += 1;
    $cfg->save();
    return $cfg->correlativo;
});

$builder = new FelDocumentoBuilder();
$documento = $builder->facturaInterna($compania, $config, null, $data, $numeroFiscal);
$totales = $documento['totalesSubTotales'];

$fel = FelDocumento::create([
    'compania_id' => 1,
    'tipo_documento' => '01',
    'documento_origen' => 'fel_manual',
    'documento_id' => 0,
    'numero' => (string) $numeroFiscal,
    'fecha' => now()->toDateString(),
    'cliente_id' => null,
    'subtotal' => $totales['totalPrecioNeto'],
    'itbms' => $totales['totalITBMS'],
    'total' => $totales['totalFactura'],
    'estado_fel' => 'PENDIENTE',
    'created_by' => $usuario,
]);

FelDocumentoDetalle::create([
    'fel_documento_id' => $fel->id,
    'linea' => 1,
    'descripcion' => 'Servicio de prueba FEL',
    'cantidad' => 1,
    'precio_unitario' => 100.00,
    'impuesto_monto' => 7.00,
    'total_linea' => 107.00,
    'created_by' => $usuario,
]);

$resp = (new FelService($config))->enviar($documento);

DB::table('fel_eventos')->insert([
    'fel_documento_id' => $fel->id,
    'evento' => 'ENVIO',
    'descripcion' => $resp['mensaje'] ?? null,
    'respuesta' => json_encode($resp, JSON_UNESCAPED_UNICODE),
    'created_at' => now(),
    'updated_at' => now(),
    'created_by' => $usuario,
]);

$resultado = $resp['EnviarResult'] ?? $resp;
$codigo = (string) ($resp['codigo'] ?? $resultado['codigo'] ?? '');

if ($codigo === '200' || ($resultado['resultado'] ?? '') === 'Procesado') {
    $fel->update([
        'estado_fel' => 'AUTORIZADO',
        'cufe' => $resultado['cufe'] ?? null,
        'qr' => $resultado['qr'] ?? null,
        'respuesta_dgi' => $resp,
        'fecha_envio' => now(),
        'updated_by' => $usuario,
    ]);
    echo "AUTORIZADA factura {$numeroFiscal} (fel_documento id={$fel->id})\n";
    echo 'CUFE: '.($resultado['cufe'] ?? '(sin cufe)')."\n";
    echo 'QR: '.substr((string) ($resultado['qr'] ?? ''), 0, 160)."\n";
} else {
    $fel->update([
        'estado_fel' => 'RECHAZADO',
        'respuesta_dgi' => $resp,
        'fecha_envio' => now(),
        'updated_by' => $usuario,
    ]);
    echo "RECHAZADA factura {$numeroFiscal}\n";
    var_export($resp);
    echo "\n";
}
