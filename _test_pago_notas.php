<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CxcDocumento;
use App\Models\CxpDocumento;
use App\Models\CxpAplicacion;
use App\Models\CuentaDefault;
use App\Models\Contacto;
use App\Models\User;
use App\Services\AsientoAutomatico;
use Illuminate\Support\Facades\DB;

$companiaId = 1;
$usuario = User::first();
$proveedor = Contacto::where('compania_id', $companiaId)
    ->whereHas('tipos', fn($q) => $q->where('codigo', 'PROVEEDOR'))
    ->first();
$cliente = Contacto::where('compania_id', $companiaId)
    ->whereHas('tipos', fn($q) => $q->where('codigo', 'CLIENTE'))
    ->first();

// ─── TEST 1: PAGO CxP sobre TEST-CXP-001 ───
echo "=== TEST CxP PAGO ===" . PHP_EOL;

$facturaCxp = CxpDocumento::where('compania_id', $companiaId)
    ->where('numero', 'TEST-CXP-001')
    ->first();

if (!$facturaCxp || $facturaCxp->saldo <= 0) {
    echo "SKIP: TEST-CXP-001 no existe o saldo=0" . PHP_EOL;
} else {
    echo "Factura {$facturaCxp->numero} | Saldo: {$facturaCxp->saldo} | Proveedor: {$facturaCxp->proveedor->nombre}" . PHP_EOL;

    $cuentaCxpId   = CuentaDefault::idPara($companiaId, 'CXP');
    $cuentaBancoId = CuentaDefault::idPara($companiaId, 'BANCO_DEFAULT')
                   ?? CuentaDefault::idPara($companiaId, 'CAJA_DEFAULT');

    try {
        $pago = DB::transaction(function() use ($companiaId, $facturaCxp, $proveedor, $cuentaCxpId, $cuentaBancoId, $usuario) {
            $monto = (float) $facturaCxp->saldo;
            $fecha = '2026-06-12';

            $pago = CxpDocumento::create([
                'compania_id'    => $companiaId,
                'proveedor_id'   => $proveedor->id,
                'tipo_documento' => CxpDocumento::TIPO_PAGO,
                'numero'         => CxpDocumento::siguienteNumeroPago($companiaId),
                'fecha'          => $fecha,
                'subtotal'       => $monto,
                'impuesto'       => 0,
                'total'          => $monto,
                'saldo'          => 0,
                'estado'         => CxpDocumento::ESTADO_PAGADO,
                'created_by'     => $usuario->email,
            ]);

            CxpAplicacion::create([
                'compania_id'          => $companiaId,
                'proveedor_id'         => $proveedor->id,
                'documento_origen_id'  => $pago->id,
                'documento_destino_id' => $facturaCxp->id,
                'fecha'                => $fecha,
                'monto_aplicado'       => $monto,
                'created_by'           => $usuario->email,
            ]);

            $facturaCxp->saldo   = 0;
            $facturaCxp->estado  = CxpDocumento::ESTADO_PAGADO;
            $facturaCxp->updated_by = $usuario->email;
            $facturaCxp->save();

            $asiento = app(AsientoAutomatico::class)->postear(
                $companiaId, $fecha,
                "Pago {$pago->numero} - {$proveedor->nombre}",
                $pago->numero,
                [
                    ['cuenta_id' => $cuentaCxpId,   'contacto_id' => $proveedor->id, 'descripcion' => "Pago {$pago->numero}", 'debito' => $monto, 'credito' => 0],
                    ['cuenta_id' => $cuentaBancoId,  'descripcion' => "Pago {$pago->numero}",   'debito' => 0, 'credito' => $monto],
                ],
                'CXP', 'cxp_documentos', $pago->id, $usuario
            );
            $pago->update(['asiento_id' => $asiento->id]);
            return $pago;
        });

        $facturaCxp->refresh();
        echo "OK - Pago: {$pago->numero} | Total: {$pago->total} | Asiento: {$pago->asiento_id}" . PHP_EOL;
        echo "Factura {$facturaCxp->numero} ahora: Saldo={$facturaCxp->saldo} | Estado={$facturaCxp->estado}" . PHP_EOL;
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . PHP_EOL;
    }
}

// ─── TEST 2: NOTA DE CRÉDITO CxC (crea nueva factura pendiente primero) ───
echo PHP_EOL . "=== TEST CxC NOTA DE CRÉDITO ===" . PHP_EOL;

try {
    // Crear factura nueva para aplicar NC
    $cuentaCxcId   = CuentaDefault::idPara($companiaId, 'CXC');
    $cuentaVentasId = CuentaDefault::idPara($companiaId, 'VENTAS');
    $cuentaItbmsId  = CuentaDefault::idPara($companiaId, 'ITBMS_POR_PAGAR');
    $cuentaDescuentosId = CuentaDefault::idPara($companiaId, 'DESCUENTOS_VENTA');

    $facturaNC = DB::transaction(function() use ($companiaId, $cliente, $cuentaCxcId, $cuentaVentasId, $cuentaItbmsId, $usuario) {
        $doc = CxcDocumento::create(['compania_id' => $companiaId, 'cliente_id' => $cliente->id, 'tipo_documento' => CxcDocumento::TIPO_FACTURA, 'numero' => CxcDocumento::siguienteNumero($companiaId, CxcDocumento::TIPO_FACTURA), 'fecha' => '2026-06-12', 'subtotal' => 50.00, 'descuento' => 0, 'impuesto' => 0, 'total' => 50.00, 'saldo' => 50.00, 'estado' => CxcDocumento::ESTADO_PENDIENTE, 'created_by' => $usuario->email]);
        $asiento = app(AsientoAutomatico::class)->postear($companiaId, '2026-06-12', "Factura {$doc->numero}", $doc->numero, [['cuenta_id' => $cuentaCxcId, 'contacto_id' => $cliente->id, 'descripcion' => "Factura {$doc->numero}", 'debito' => 50.00, 'credito' => 0], ['cuenta_id' => $cuentaVentasId, 'descripcion' => 'Servicio', 'debito' => 0, 'credito' => 50.00]], 'CXC', 'cxc_documentos', $doc->id, $usuario);
        $doc->update(['asiento_id' => $asiento->id]);
        return $doc;
    });

    echo "Factura de prueba: {$facturaNC->numero} | Saldo: {$facturaNC->saldo}" . PHP_EOL;

    // Ahora nota de crédito por 20
    $nc = DB::transaction(function() use ($companiaId, $cliente, $facturaNC, $cuentaCxcId, $cuentaDescuentosId, $cuentaItbmsId, $usuario) {
        $base = 20.00; $itbms = 0.00; $total = 20.00;

        $nota = CxcDocumento::create(['compania_id' => $companiaId, 'cliente_id' => $cliente->id, 'tipo_documento' => CxcDocumento::TIPO_NOTA_CREDITO, 'numero' => CxcDocumento::siguienteNumero($companiaId, CxcDocumento::TIPO_NOTA_CREDITO), 'fecha' => '2026-06-12', 'subtotal' => $base, 'descuento' => 0, 'impuesto' => $itbms, 'total' => $total, 'saldo' => 0, 'estado' => CxcDocumento::ESTADO_PAGADO, 'created_by' => $usuario->email]);

        $lineas = [['cuenta_id' => $cuentaDescuentosId ?? $cuentaVentasId ?? $cuentaCxcId, 'descripcion' => 'Descuento por devolución', 'debito' => $base, 'credito' => 0], ['cuenta_id' => $cuentaCxcId, 'contacto_id' => $cliente->id, 'descripcion' => "NC {$nota->numero}", 'debito' => 0, 'credito' => $total]];

        \App\Models\CxcAplicacion::create(['compania_id' => $companiaId, 'cliente_id' => $cliente->id, 'documento_origen_id' => $nota->id, 'documento_destino_id' => $facturaNC->id, 'fecha' => '2026-06-12', 'monto_aplicado' => $total, 'created_by' => $usuario->email]);
        $facturaNC->saldo  = round((float)$facturaNC->saldo - $total, 2);
        $facturaNC->estado = $facturaNC->estadoSegunSaldo();
        $facturaNC->updated_by = $usuario->email;
        $facturaNC->save();

        $asiento = app(AsientoAutomatico::class)->postear($companiaId, '2026-06-12', "NC {$nota->numero} - {$cliente->nombre}", $nota->numero, $lineas, 'CXC', 'cxc_documentos', $nota->id, $usuario);
        $nota->update(['asiento_id' => $asiento->id]);
        return $nota;
    });

    $facturaNC->refresh();
    echo "OK - NC: {$nc->numero} | Total: {$nc->total} | Asiento: {$nc->asiento_id}" . PHP_EOL;
    echo "Factura {$facturaNC->numero} ahora: Saldo={$facturaNC->saldo} | Estado={$facturaNC->estado}" . PHP_EOL;
} catch (Exception $e) {
    echo "ERROR NC CxC: " . $e->getMessage() . " en " . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
}

// ─── TEST 3: NOTA DE DÉBITO CxP ───
echo PHP_EOL . "=== TEST CxP NOTA DE DÉBITO ===" . PHP_EOL;

try {
    $cuentaCxpId   = CuentaDefault::idPara($companiaId, 'CXP');
    $cuentaGastoId = CuentaDefault::idPara($companiaId, 'GASTO_DEFAULT');

    $nd = DB::transaction(function() use ($companiaId, $proveedor, $cuentaCxpId, $cuentaGastoId, $usuario) {
        $base = 30.00; $total = 30.00;
        $nota = CxpDocumento::create(['compania_id' => $companiaId, 'proveedor_id' => $proveedor->id, 'tipo_documento' => CxpDocumento::TIPO_NOTA_DEBITO, 'numero' => CxpDocumento::siguienteNumeroNota($companiaId, CxpDocumento::TIPO_NOTA_DEBITO), 'fecha' => '2026-06-12', 'subtotal' => $base, 'descuento' => 0, 'impuesto' => 0, 'total' => $total, 'saldo' => $total, 'estado' => CxpDocumento::ESTADO_PENDIENTE, 'created_by' => $usuario->email]);

        $lineas = [['cuenta_id' => $cuentaGastoId, 'descripcion' => 'Cargo adicional proveedor', 'debito' => $base, 'credito' => 0], ['cuenta_id' => $cuentaCxpId, 'contacto_id' => $proveedor->id, 'descripcion' => "ND {$nota->numero}", 'debito' => 0, 'credito' => $total]];

        $asiento = app(AsientoAutomatico::class)->postear($companiaId, '2026-06-12', "ND {$nota->numero} - {$proveedor->nombre}", $nota->numero, $lineas, 'CXP', 'cxp_documentos', $nota->id, $usuario);
        $nota->update(['asiento_id' => $asiento->id]);
        return $nota;
    });

    echo "OK - ND CxP: {$nd->numero} | Total: {$nd->total} | Asiento: {$nd->asiento_id} | Saldo: {$nd->saldo}" . PHP_EOL;
} catch (Exception $e) {
    echo "ERROR ND CxP: " . $e->getMessage() . " en " . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
}

echo PHP_EOL . "=== FIN ===" . PHP_EOL;
