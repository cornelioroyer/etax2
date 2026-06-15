<?php
/**
 * Ciclo contable de prueba: crea una compañía de cero (plan PA_ISR) y postea
 * los 8 asientos del ejercicio (aporte, compra, venta, costo, cobro, pago,
 * gasto y cierre). Imprime el balance antes y después del cierre.
 *
 * Ejecutar:  php artisan tinker _seed_ciclo_contable.php   (en dev.etax2.com)
 */

use App\Models\Compania;
use App\Models\CuentaContable;
use App\Models\User;
use App\Services\AsientoAutomatico;
use App\Services\PlantillaCuentas;
use Illuminate\Support\Facades\DB;

$usuario = User::find(4); // super_admin cornelioroyer@winsof.com
$auto    = app(AsientoAutomatico::class);

$snapshot = function (int $cid, string $titulo) {
    $rows = DB::table('cgl_saldos as s')
        ->join('cgl_cuentas as c', 'c.id', '=', 's.cuenta_id')
        ->where('s.compania_id', $cid)
        ->groupBy('c.codigo', 'c.nombre')
        ->orderBy('c.codigo')
        ->get([
            'c.codigo', 'c.nombre',
            DB::raw('SUM(s.debito) as deb'),
            DB::raw('SUM(s.credito) as cred'),
            DB::raw('SUM(s.debito - s.credito) as neto'),
        ]);

    echo "\n== {$titulo} ==\n";
    printf("%-7s %-32s %12s %12s %12s\n", 'Codigo', 'Cuenta', 'Debito', 'Credito', 'Neto(D-C)');
    echo str_repeat('-', 79)."\n";
    $td = 0.0; $tc = 0.0;
    foreach ($rows as $r) {
        printf("%-7s %-32s %12.2f %12.2f %12.2f\n", $r->codigo, mb_substr($r->nombre, 0, 32), $r->deb, $r->cred, $r->neto);
        $td += (float) $r->deb; $tc += (float) $r->cred;
    }
    echo str_repeat('-', 79)."\n";
    printf("%-7s %-32s %12.2f %12.2f   (cuadre: %s)\n", '', 'TOTALES', $td, $tc, abs($td - $tc) < 0.005 ? 'OK' : 'DESCUADRE');
    echo "\n";
};

try {
    DB::transaction(function () use ($usuario, $auto, $snapshot) {

        // ── 1. Compañía nueva + plan de cuentas PA_ISR ──────────────────────
        $compania = Compania::create([
            'nombre'              => 'DEMO CICLO CONTABLE',
            'ruc'                 => 'DEMO-CICLO-'.date('YmdHis'),
            'dv'                  => '00',
            'direccion'           => 'Ciudad de Panama',
            'email'               => 'demociclo@etax2.test',
            'telefono'            => '000-0000',
            'correlativo_ss'      => 0,
            'fecha_de_apertura'   => '2026-06-01',
            'fecha_de_expiracion' => '2027-06-01',
            'activa'              => true,
            'zonas_id'            => 2,
            'created_by'          => $usuario->email,
        ]);
        $cid = $compania->id;
        echo "Compania creada: id={$cid}  {$compania->nombre}  (RUC {$compania->ruc})\n";

        $n = app(PlantillaCuentas::class)->aplicar($cid, PlantillaCuentas::POR_DEFECTO, $usuario->email);
        echo "Plan de cuentas PA_ISR aplicado: {$n} cuentas\n\n";

        // ── Resolver cuentas por codigo ─────────────────────────────────────
        $C = function (string $cod) use ($cid) {
            $id = CuentaContable::where('compania_id', $cid)->where('codigo', $cod)->value('id');
            if (! $id) {
                throw new \RuntimeException("Cuenta {$cod} no encontrada en la compania {$cid}");
            }
            return (int) $id;
        };
        $banco     = $C('10102'); // Bancos
        $capital   = $C('30101'); // Acciones Comunes (capital social)
        $inv       = $C('10112'); // Inventario
        $itbmsCred = $C('10113'); // ITBMS Credito Fiscal
        $cxp       = $C('20101'); // Cuentas por Pagar Proveedores
        $cxc       = $C('10103'); // Cuentas por Cobrar Clientes
        $ventas    = $C('40101'); // Ventas y Prestacion de Servicios
        $itbmsDeb  = $C('20107'); // ITBMS por Pagar
        $costo     = $C('50103'); // Costo de Ventas
        $gastos    = $C('60126'); // Otros Gastos (administrativo)
        $resultado = $C('30201'); // Superavit Acumulado (resultado del ejercicio)

        $L = fn ($cuenta, $d, $c) => ['cuenta_id' => $cuenta, 'debito' => (float) $d, 'credito' => (float) $c];
        $post = function ($fecha, $desc, $ref, $lineas) use ($auto, $cid, $usuario) {
            $a = $auto->postear($cid, $fecha, $desc, $ref, $lineas, 'CGL', null, null, $usuario);
            printf("  %-10s %s  %-9s %s\n", $a->numero, $fecha, $ref, $desc);
            return $a;
        };

        echo "Asientos posteados (pasos 1-7):\n";
        $post('2026-06-01', 'Aporte inicial de capital',        'DG-0001', [$L($banco, 5000, 0),  $L($capital, 0, 5000)]);
        $post('2026-06-03', 'Compra de mercancia al credito',   'CP-0001', [$L($inv, 1200, 0),    $L($itbmsCred, 84, 0), $L($cxp, 0, 1284)]);
        $post('2026-06-05', 'Venta al credito',                 'FV-0001', [$L($cxc, 1605, 0),    $L($ventas, 0, 1500),  $L($itbmsDeb, 0, 105)]);
        $post('2026-06-05', 'Costo de la mercancia vendida',    'CV-0001', [$L($costo, 700, 0),   $L($inv, 0, 700)]);
        $post('2026-06-08', 'Cobro parcial de factura FV-0001', 'RC-0001', [$L($banco, 1000, 0),  $L($cxc, 0, 1000)]);
        $post('2026-06-10', 'Pago parcial de compra CP-0001',   'PG-0001', [$L($cxp, 800, 0),     $L($banco, 0, 800)]);
        $post('2026-06-12', 'Pago de gasto administrativo',     'EG-0001', [$L($gastos, 250, 0),  $L($banco, 0, 250)]);

        $snapshot($cid, 'SALDOS DESPUES DE PASOS 1-7 (antes del cierre)');

        echo "Asientos de cierre (paso 8):\n";
        $post('2026-06-30', 'Cierre de ingresos',          'CIE-0001', [$L($ventas, 1500, 0), $L($resultado, 0, 1500)]);
        $post('2026-06-30', 'Cierre de costos y gastos',   'CIE-0001', [$L($resultado, 950, 0), $L($costo, 0, 700), $L($gastos, 0, 250)]);

        $snapshot($cid, 'SALDOS DESPUES DEL CIERRE (paso 8)');

        echo "Compania id={$cid} lista. Ciclo contable completo.\n";
    });
} catch (\Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    echo 'En: '.$e->getFile().' linea '.$e->getLine()."\n";
}
