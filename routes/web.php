<?php

use App\Http\Controllers\Admin\AsientoController;
use App\Http\Controllers\Admin\CompaniaController;
use App\Http\Controllers\Admin\ContactoController;
use App\Http\Controllers\Admin\CuentaContableController;
use App\Http\Controllers\Admin\CxcAntiguedadController;
use App\Http\Controllers\Admin\CxcCobroController;
use App\Http\Controllers\Admin\CxcEstadoCuentaController;
use App\Http\Controllers\Admin\CxcFacturaController;
use App\Http\Controllers\Admin\CxcNotaController;
use App\Http\Controllers\Admin\CxpAntiguedadController;
use App\Http\Controllers\Admin\CxpEstadoCuentaController;
use App\Http\Controllers\Admin\CxpFacturaController;
use App\Http\Controllers\Admin\CxpNotaController;
use App\Http\Controllers\Admin\CxpPagoController;
use App\Http\Controllers\Admin\FacturaFelController;
use App\Http\Controllers\Admin\ReporteBalanceController;
use App\Http\Controllers\Admin\ReporteComparativoController;
use App\Http\Controllers\Admin\ReporteResultadosController;
use App\Http\Controllers\Admin\FelConfiguracionController;
use App\Http\Controllers\Admin\BancoCuentaController;
use App\Http\Controllers\Admin\CuentaDefaultController;
use App\Http\Controllers\Admin\DiarioController;
use App\Http\Controllers\Admin\GastoController;
use App\Http\Controllers\Admin\PeriodoContableController;
use App\Http\Controllers\Admin\ReporteFlujoCajaController;
use App\Http\Controllers\Admin\ReporteLiquidacionItbmsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UsuarioCompaniaController;
use App\Http\Controllers\Admin\VentaCotizacionController;
use App\Http\Controllers\Admin\VentaFacturaController;
use App\Http\Controllers\Admin\VentaReciboController;
use App\Http\Controllers\Admin\VentaNotaCreditoController;
use App\Http\Controllers\Admin\BcoBancoController;
use App\Http\Controllers\Admin\BcoCuentaController;
use App\Http\Controllers\Admin\BcoMovimientoController;
use App\Http\Controllers\Admin\BcoTransferenciaController;
use App\Http\Controllers\Admin\BcoConciliacionController;
use App\Http\Controllers\Admin\AfiActivoController;
use App\Http\Controllers\Admin\AfiCategoriaController;
use App\Http\Controllers\Admin\AfiRevaluacionController;
use App\Http\Controllers\Admin\AfiUbicacionController;
use App\Http\Controllers\Admin\PrhCuotaController;
use App\Http\Controllers\Admin\PrhEdificioController;
use App\Http\Controllers\Admin\PrhPagoController;
use App\Http\Controllers\Admin\PrhPropietarioController;
use App\Http\Controllers\Admin\PrhTipoCuotaController;
use App\Http\Controllers\Admin\PrhUnidadController;
use App\Http\Controllers\Admin\BcoChequeController;
use App\Http\Controllers\Admin\CajaController;
use App\Http\Controllers\Admin\CajaOperacionController;
use App\Http\Controllers\Admin\CompraOrdenController;
use App\Http\Controllers\Admin\CompraRecepcionController;
use App\Http\Controllers\Admin\InvAlmacenController;
use App\Http\Controllers\Admin\InvMovimientoController;
use App\Http\Controllers\Admin\ItemProductoController;
use App\Http\Controllers\Admin\ZonaController;
use App\Http\Controllers\Admin\BcoDepositoController;
use App\Http\Controllers\Admin\CglCierreController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\ContactoExtController;
use App\Http\Controllers\Admin\InvKardexController;
use App\Http\Controllers\Admin\InvTransferenciaController;
use App\Http\Controllers\Admin\ItemPrecioController;
use App\Http\Controllers\Admin\VentaVendedorController;
use App\Http\Controllers\CompaniaActivaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/compania-activa', CompaniaActivaController::class)->name('compania.activa');
});

// Solo super admin (creadores de la plataforma)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->except(['show']);
});

// Módulos protegidos por permisos (por compañía)
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('permission:zonas.ver')->group(function () {
        Route::resource('zonas', ZonaController::class)->except(['show'])->parameters(['zonas' => 'zona']);
    });

    Route::middleware('permission:companias.ver')->group(function () {
        Route::resource('companias', CompaniaController::class)->except(['show'])->parameters(['companias' => 'compania']);
    });

    Route::middleware('permission:contactos.ver')->group(function () {
        Route::resource('contactos', ContactoController::class)->except(['show'])->parameters(['contactos' => 'contacto']);
    });

    Route::middleware('permission:contabilidad.ver')->group(function () {
        Route::post('cuentas-aplicar-plantilla', [CuentaContableController::class, 'aplicarPlantilla'])->name('cuentas.aplicar-plantilla');
        Route::resource('cuentas', CuentaContableController::class)->except(['show'])->parameters(['cuentas' => 'cuenta']);
        Route::post('asientos/{asiento}/postear', [AsientoController::class, 'postear'])->name('asientos.postear');
        Route::post('asientos/{asiento}/anular', [AsientoController::class, 'anular'])->name('asientos.anular');
        Route::resource('asientos', AsientoController::class)->parameters(['asientos' => 'asiento']);
        Route::get('cuentas-default', [CuentaDefaultController::class, 'index'])->name('cuentas-default.index');
        Route::put('cuentas-default', [CuentaDefaultController::class, 'update'])->name('cuentas-default.update');
        Route::get('diarios', [DiarioController::class, 'index'])->name('diarios.index');
        Route::post('diarios', [DiarioController::class, 'store'])->name('diarios.store');
        Route::put('diarios/{diario}', [DiarioController::class, 'update'])->name('diarios.update');
        Route::post('diarios/{diario}/toggle', [DiarioController::class, 'toggleActivo'])->name('diarios.toggle');
        Route::get('periodos', [PeriodoContableController::class, 'index'])->name('periodos.index');
        Route::post('periodos/cerrar', [PeriodoContableController::class, 'cerrar'])->name('periodos.cerrar');
        Route::post('periodos/{periodo}/reabrir', [PeriodoContableController::class, 'reabrir'])->name('periodos.reabrir');
    });

    Route::middleware('permission:cxc.ver')->group(function () {
        Route::get('cxc/facturas', [CxcFacturaController::class, 'index'])->name('cxc.facturas.index');
        Route::get('cxc/facturas/{documento}', [CxcFacturaController::class, 'show'])->whereNumber('documento')->name('cxc.facturas.show');
        Route::get('cxc/cobros', [CxcCobroController::class, 'index'])->name('cxc.cobros.index');
        Route::get('cxc/cobros/{documento}', [CxcCobroController::class, 'show'])->whereNumber('documento')->name('cxc.cobros.show');
        Route::get('cxc/antiguedad', CxcAntiguedadController::class)->name('cxc.antiguedad');
        Route::get('cxc/estado-cuenta', CxcEstadoCuentaController::class)->name('cxc.estado-cuenta');
        Route::get('cxc/notas', [CxcNotaController::class, 'index'])->name('cxc.notas.index');
        Route::get('cxc/notas/{documento}', [CxcNotaController::class, 'show'])->whereNumber('documento')->name('cxc.notas.show');
    });

    Route::middleware('permission:cxc.gestionar')->group(function () {
        Route::get('cxc/notas/crear', [CxcNotaController::class, 'create'])->name('cxc.notas.create');
        Route::post('cxc/notas', [CxcNotaController::class, 'store'])->name('cxc.notas.store');
        Route::post('cxc/notas/{documento}/anular', [CxcNotaController::class, 'anular'])->whereNumber('documento')->name('cxc.notas.anular');
        Route::get('cxc/facturas/nueva', [CxcFacturaController::class, 'create'])->name('cxc.facturas.create');
        Route::post('cxc/facturas', [CxcFacturaController::class, 'store'])->name('cxc.facturas.store');
        Route::post('cxc/facturas/{documento}/anular', [CxcFacturaController::class, 'anular'])->whereNumber('documento')->name('cxc.facturas.anular');
        Route::get('cxc/cobros/nuevo', [CxcCobroController::class, 'create'])->name('cxc.cobros.create');
        Route::post('cxc/cobros', [CxcCobroController::class, 'store'])->name('cxc.cobros.store');
        Route::post('cxc/cobros/{documento}/anular', [CxcCobroController::class, 'anular'])->whereNumber('documento')->name('cxc.cobros.anular');
    });

    Route::middleware('permission:cxp.ver')->group(function () {
        Route::get('cxp/facturas', [CxpFacturaController::class, 'index'])->name('cxp.facturas.index');
        Route::get('cxp/facturas/{documento}', [CxpFacturaController::class, 'show'])->whereNumber('documento')->name('cxp.facturas.show');
        Route::get('cxp/pagos', [CxpPagoController::class, 'index'])->name('cxp.pagos.index');
        Route::get('cxp/pagos/{documento}', [CxpPagoController::class, 'show'])->whereNumber('documento')->name('cxp.pagos.show');
        Route::get('cxp/antiguedad', CxpAntiguedadController::class)->name('cxp.antiguedad');
        Route::get('cxp/estado-cuenta', CxpEstadoCuentaController::class)->name('cxp.estado-cuenta');
        Route::get('cxp/notas', [CxpNotaController::class, 'index'])->name('cxp.notas.index');
        Route::get('cxp/notas/{documento}', [CxpNotaController::class, 'show'])->whereNumber('documento')->name('cxp.notas.show');
    });

    Route::middleware('permission:cxp.gestionar')->group(function () {
        Route::get('cxp/notas/crear', [CxpNotaController::class, 'create'])->name('cxp.notas.create');
        Route::post('cxp/notas', [CxpNotaController::class, 'store'])->name('cxp.notas.store');
        Route::post('cxp/notas/{documento}/anular', [CxpNotaController::class, 'anular'])->whereNumber('documento')->name('cxp.notas.anular');
        Route::get('cxp/facturas/nueva', [CxpFacturaController::class, 'create'])->name('cxp.facturas.create');
        Route::post('cxp/facturas', [CxpFacturaController::class, 'store'])->name('cxp.facturas.store');
        Route::post('cxp/facturas/{documento}/anular', [CxpFacturaController::class, 'anular'])->whereNumber('documento')->name('cxp.facturas.anular');
        Route::get('cxp/pagos/nuevo', [CxpPagoController::class, 'create'])->name('cxp.pagos.create');
        Route::post('cxp/pagos', [CxpPagoController::class, 'store'])->name('cxp.pagos.store');
        Route::post('cxp/pagos/{documento}/anular', [CxpPagoController::class, 'anular'])->whereNumber('documento')->name('cxp.pagos.anular');
    });

    Route::middleware('permission:reportes.ver')->group(function () {
        Route::get('reportes/balance-situacion', ReporteBalanceController::class)->name('reportes.balance');
        Route::get('reportes/estado-resultado', ReporteResultadosController::class)->name('reportes.resultado');
        Route::get('reportes/comparativo-mensual', ReporteComparativoController::class)->name('reportes.comparativo');
        Route::get('reportes/flujo-caja', ReporteFlujoCajaController::class)->name('reportes.flujo-caja');
        Route::get('reportes/liquidacion-itbms', ReporteLiquidacionItbmsController::class)->name('reportes.liquidacion-itbms');
    });

    Route::middleware('permission:bancos.ver')->group(function () {
        Route::get('bancos', [BancoCuentaController::class, 'index'])->name('bancos.index');
    });

    Route::middleware('permission:bancos.gestionar')->group(function () {
        Route::post('bancos', [BancoCuentaController::class, 'store'])->name('bancos.store');
        Route::put('bancos/{cuenta}', [BancoCuentaController::class, 'update'])->name('bancos.update');
        Route::post('bancos/{cuenta}/toggle', [BancoCuentaController::class, 'toggleActiva'])->name('bancos.toggle');
    });

    Route::middleware('permission:compras.ver')->group(function () {
        Route::get('compras/gastos', [GastoController::class, 'index'])->name('compras.gastos.index');
        Route::get('compras/ordenes', [CompraOrdenController::class, 'index'])->name('compras.ordenes.index');
        Route::get('compras/ordenes/{orden}', [CompraOrdenController::class, 'show'])->whereNumber('orden')->name('compras.ordenes.show');
    });

    Route::middleware('permission:compras.gestionar')->group(function () {
        Route::get('compras/gastos/nuevo', [GastoController::class, 'create'])->name('compras.gastos.create');
        Route::post('compras/gastos', [GastoController::class, 'store'])->name('compras.gastos.store');
        Route::get('compras/ordenes/nueva', [CompraOrdenController::class, 'create'])->name('compras.ordenes.create');
        Route::post('compras/ordenes', [CompraOrdenController::class, 'store'])->name('compras.ordenes.store');
        Route::post('compras/ordenes/{orden}/aprobar', [CompraOrdenController::class, 'aprobar'])->whereNumber('orden')->name('compras.ordenes.aprobar');
        Route::post('compras/ordenes/{orden}/anular', [CompraOrdenController::class, 'anular'])->whereNumber('orden')->name('compras.ordenes.anular');
        Route::post('compras/ordenes/{orden}/facturar', [CompraOrdenController::class, 'facturar'])->whereNumber('orden')->name('compras.ordenes.facturar');
        Route::post('compras/ordenes/{orden}/recepciones', [CompraRecepcionController::class, 'store'])->whereNumber('orden')->name('compras.ordenes.recepciones.store');
    });

    Route::middleware('permission:caja.ver')->group(function () {
        Route::get('caja', [CajaController::class, 'index'])->name('caja.index');
        Route::get('caja/{caja}', [CajaController::class, 'show'])->whereNumber('caja')->name('caja.show');
    });

    Route::middleware('permission:caja.gestionar')->group(function () {
        Route::post('caja', [CajaController::class, 'store'])->name('caja.store');
        Route::put('caja/{caja}', [CajaController::class, 'update'])->whereNumber('caja')->name('caja.update');
        Route::post('caja/{caja}/toggle', [CajaController::class, 'toggle'])->whereNumber('caja')->name('caja.toggle');
        Route::post('caja/{caja}/movimiento', [CajaOperacionController::class, 'movimiento'])->whereNumber('caja')->name('caja.movimiento');
        Route::post('caja/{caja}/reembolso', [CajaOperacionController::class, 'reembolso'])->whereNumber('caja')->name('caja.reembolso');
        Route::post('caja/{caja}/vale', [CajaOperacionController::class, 'vale'])->whereNumber('caja')->name('caja.vale');
        Route::post('caja/vales/{vale}/liquidar', [CajaOperacionController::class, 'liquidarVale'])->whereNumber('vale')->name('caja.vale.liquidar');
        Route::post('caja/{caja}/arqueo', [CajaOperacionController::class, 'arqueo'])->whereNumber('caja')->name('caja.arqueo');
    });

    Route::middleware('permission:activos.ver')->group(function () {
        Route::get('activos', [AfiActivoController::class, 'index'])->name('activos.index');
        Route::get('activos/categorias', [AfiCategoriaController::class, 'index'])->name('activos.categorias.index');
        Route::get('activos/ubicaciones', [AfiUbicacionController::class, 'index'])->name('activos.ubicaciones.index');
        Route::get('activos/{activo}', [AfiActivoController::class, 'show'])->whereNumber('activo')->name('activos.show');
    });

    Route::middleware('permission:activos.gestionar')->group(function () {
        Route::get('activos/nuevo', [AfiActivoController::class, 'create'])->name('activos.create');
        Route::post('activos', [AfiActivoController::class, 'store'])->name('activos.store');
        Route::post('activos/{activo}/depreciar', [AfiActivoController::class, 'depreciar'])->whereNumber('activo')->name('activos.depreciar');
        Route::post('activos/{activo}/baja', [AfiActivoController::class, 'baja'])->whereNumber('activo')->name('activos.baja');
        Route::post('activos/categorias', [AfiCategoriaController::class, 'store'])->name('activos.categorias.store');
        Route::put('activos/categorias/{categoria}', [AfiCategoriaController::class, 'update'])->whereNumber('categoria')->name('activos.categorias.update');
        Route::delete('activos/categorias/{categoria}', [AfiCategoriaController::class, 'destroy'])->whereNumber('categoria')->name('activos.categorias.destroy');
        Route::post('activos/ubicaciones', [AfiUbicacionController::class, 'store'])->name('activos.ubicaciones.store');
        Route::put('activos/ubicaciones/{ubicacion}', [AfiUbicacionController::class, 'update'])->whereNumber('ubicacion')->name('activos.ubicaciones.update');
        Route::delete('activos/ubicaciones/{ubicacion}', [AfiUbicacionController::class, 'destroy'])->whereNumber('ubicacion')->name('activos.ubicaciones.destroy');
    });

    Route::middleware('permission:inventario.ver')->group(function () {
        Route::get('inventario/almacenes', [InvAlmacenController::class, 'index'])->name('inventario.almacenes.index');
        Route::get('inventario/almacenes/{almacen}/existencias', [InvAlmacenController::class, 'existencias'])->whereNumber('almacen')->name('inventario.almacenes.existencias');
        Route::get('inventario/movimientos', [InvMovimientoController::class, 'index'])->name('inventario.movimientos.index');
        Route::get('inventario/movimientos/{movimiento}', [InvMovimientoController::class, 'show'])->whereNumber('movimiento')->name('inventario.movimientos.show');
        Route::get('items', [ItemProductoController::class, 'index'])->name('items.index');
        Route::get('items/{item}/edit', [ItemProductoController::class, 'edit'])->whereNumber('item')->name('items.edit');
    });

    Route::middleware('permission:inventario.gestionar')->group(function () {
        Route::get('items/nuevo', [ItemProductoController::class, 'create'])->name('items.create');
        Route::post('items', [ItemProductoController::class, 'store'])->name('items.store');
        Route::put('items/{item}', [ItemProductoController::class, 'update'])->whereNumber('item')->name('items.update');
        Route::post('items/{item}/toggle', [ItemProductoController::class, 'toggle'])->whereNumber('item')->name('items.toggle');
        Route::post('inventario/almacenes', [InvAlmacenController::class, 'store'])->name('inventario.almacenes.store');
        Route::put('inventario/almacenes/{almacen}', [InvAlmacenController::class, 'update'])->whereNumber('almacen')->name('inventario.almacenes.update');
        Route::post('inventario/almacenes/{almacen}/toggle', [InvAlmacenController::class, 'toggle'])->whereNumber('almacen')->name('inventario.almacenes.toggle');
        Route::get('inventario/movimientos/nuevo', [InvMovimientoController::class, 'create'])->name('inventario.movimientos.create');
        Route::post('inventario/movimientos', [InvMovimientoController::class, 'store'])->name('inventario.movimientos.store');
    });

    Route::middleware('permission:ventas.ver')->group(function () {
        Route::get('ventas/cotizaciones', [VentaCotizacionController::class, 'index'])->name('ventas.cotizaciones.index');
        Route::get('ventas/cotizaciones/{cotizacion}', [VentaCotizacionController::class, 'show'])->whereNumber('cotizacion')->name('ventas.cotizaciones.show');
        Route::get('ventas/cotizaciones/{cotizacion}/imprimir', [VentaCotizacionController::class, 'imprimir'])->whereNumber('cotizacion')->name('ventas.cotizaciones.imprimir');
    });

    Route::middleware('permission:ventas.gestionar')->group(function () {
        Route::get('ventas/cotizaciones/nueva', [VentaCotizacionController::class, 'create'])->name('ventas.cotizaciones.create');
        Route::post('ventas/cotizaciones', [VentaCotizacionController::class, 'store'])->name('ventas.cotizaciones.store');
        Route::post('ventas/cotizaciones/{cotizacion}/estado', [VentaCotizacionController::class, 'cambiarEstado'])->whereNumber('cotizacion')->name('ventas.cotizaciones.estado');
        Route::post('ventas/cotizaciones/{cotizacion}/anular', [VentaCotizacionController::class, 'anular'])->whereNumber('cotizacion')->name('ventas.cotizaciones.anular');
        Route::post('ventas/cotizaciones/{cotizacion}/facturar', [VentaCotizacionController::class, 'facturar'])->whereNumber('cotizacion')->name('ventas.cotizaciones.facturar');
        Route::post('ventas/cotizaciones/{cotizacion}/email', [VentaCotizacionController::class, 'enviarEmail'])->whereNumber('cotizacion')->name('ventas.cotizaciones.email');
        Route::get('ventas/facturas/nueva', [VentaFacturaController::class, 'create'])->name('ventas.facturas.create');
        Route::post('ventas/facturas', [VentaFacturaController::class, 'store'])->name('ventas.facturas.store');
        Route::post('ventas/facturas/{factura}/anular', [VentaFacturaController::class, 'anular'])->whereNumber('factura')->name('ventas.facturas.anular');
    });

    Route::middleware('permission:ventas.ver')->group(function () {
        Route::get('ventas/facturas', [VentaFacturaController::class, 'index'])->name('ventas.facturas.index');
        Route::get('ventas/facturas/{factura}', [VentaFacturaController::class, 'show'])->whereNumber('factura')->name('ventas.facturas.show');
        Route::get('ventas/recibos', [VentaReciboController::class, 'index'])->name('ventas.recibos.index');
        Route::get('ventas/recibos/{recibo}', [VentaReciboController::class, 'show'])->whereNumber('recibo')->name('ventas.recibos.show');
        Route::get('ventas/notas-credito', [VentaNotaCreditoController::class, 'index'])->name('ventas.notas-credito.index');
        Route::get('ventas/notas-credito/{notaCredito}', [VentaNotaCreditoController::class, 'show'])->whereNumber('notaCredito')->name('ventas.notas-credito.show');
    });

    Route::middleware('permission:ventas.gestionar')->group(function () {
        Route::get('ventas/recibos/nuevo', [VentaReciboController::class, 'create'])->name('ventas.recibos.create');
        Route::post('ventas/recibos', [VentaReciboController::class, 'store'])->name('ventas.recibos.store');
        Route::post('ventas/recibos/{recibo}/anular', [VentaReciboController::class, 'anular'])->whereNumber('recibo')->name('ventas.recibos.anular');
        Route::get('ventas/notas-credito/nueva', [VentaNotaCreditoController::class, 'create'])->name('ventas.notas-credito.create');
        Route::post('ventas/notas-credito', [VentaNotaCreditoController::class, 'store'])->name('ventas.notas-credito.store');
        Route::post('ventas/notas-credito/{notaCredito}/anular', [VentaNotaCreditoController::class, 'anular'])->whereNumber('notaCredito')->name('ventas.notas-credito.anular');
    });

    // Módulo bancario (bco_*)
    Route::middleware('permission:bancos.ver')->group(function () {
        Route::get('bco/cuentas', [BcoCuentaController::class, 'index'])->name('bco.cuentas.index');
        Route::get('bco/cuentas/{cuenta}', [BcoCuentaController::class, 'show'])->whereNumber('cuenta')->name('bco.cuentas.show');
        Route::get('bco/movimientos', [BcoMovimientoController::class, 'index'])->name('bco.movimientos.index');
        Route::get('bco/movimientos/{movimiento}', [BcoMovimientoController::class, 'show'])->whereNumber('movimiento')->name('bco.movimientos.show');
        Route::get('bco/transferencias', [BcoTransferenciaController::class, 'index'])->name('bco.transferencias.index');
        Route::get('bco/transferencias/{transferencia}', [BcoTransferenciaController::class, 'show'])->whereNumber('transferencia')->name('bco.transferencias.show');
        Route::get('bco/conciliaciones', [BcoConciliacionController::class, 'index'])->name('bco.conciliaciones.index');
        Route::get('bco/conciliaciones/{conciliacion}', [BcoConciliacionController::class, 'show'])->whereNumber('conciliacion')->name('bco.conciliaciones.show');
    });

    Route::middleware('permission:bancos.gestionar')->group(function () {
        Route::post('bco/bancos', [BcoBancoController::class, 'store'])->name('bco.bancos.store');
        Route::put('bco/bancos/{banco}', [BcoBancoController::class, 'update'])->whereNumber('banco')->name('bco.bancos.update');
        Route::post('bco/bancos/{banco}/toggle', [BcoBancoController::class, 'toggle'])->whereNumber('banco')->name('bco.bancos.toggle');
        Route::post('bco/cuentas', [BcoCuentaController::class, 'store'])->name('bco.cuentas.store');
        Route::put('bco/cuentas/{cuenta}', [BcoCuentaController::class, 'update'])->whereNumber('cuenta')->name('bco.cuentas.update');
        Route::post('bco/cuentas/{cuenta}/toggle', [BcoCuentaController::class, 'toggle'])->whereNumber('cuenta')->name('bco.cuentas.toggle');
        Route::get('bco/movimientos/nuevo', [BcoMovimientoController::class, 'create'])->name('bco.movimientos.create');
        Route::post('bco/movimientos', [BcoMovimientoController::class, 'store'])->name('bco.movimientos.store');
        Route::get('bco/transferencias/nueva', [BcoTransferenciaController::class, 'create'])->name('bco.transferencias.create');
        Route::post('bco/transferencias', [BcoTransferenciaController::class, 'store'])->name('bco.transferencias.store');
        Route::get('bco/conciliaciones/nueva', [BcoConciliacionController::class, 'create'])->name('bco.conciliaciones.create');
        Route::post('bco/conciliaciones', [BcoConciliacionController::class, 'store'])->name('bco.conciliaciones.store');
        Route::post('bco/conciliaciones/{conciliacion}/marcar', [BcoConciliacionController::class, 'marcar'])->whereNumber('conciliacion')->name('bco.conciliaciones.marcar');
        Route::post('bco/conciliaciones/{conciliacion}/cerrar', [BcoConciliacionController::class, 'cerrar'])->whereNumber('conciliacion')->name('bco.conciliaciones.cerrar');
    });

    Route::middleware('permission:fel.ver')->group(function () {
        Route::get('fel', [FacturaFelController::class, 'index'])->name('fel.index');
    });

    Route::middleware('permission:fel.gestionar')->group(function () {
        Route::get('fel/configuracion', [FelConfiguracionController::class, 'edit'])->name('fel.configuracion');
        Route::put('fel/configuracion', [FelConfiguracionController::class, 'update'])->name('fel.configuracion.update');
        Route::post('fel/configuracion/probar', [FelConfiguracionController::class, 'probar'])->name('fel.configuracion.probar');
        Route::get('fel/nueva', [FacturaFelController::class, 'create'])->name('fel.create');
        Route::post('fel', [FacturaFelController::class, 'store'])->name('fel.store');
        Route::post('fel/{documento}/anular', [FacturaFelController::class, 'anular'])->name('fel.anular');
    });

    Route::middleware('permission:fel.ver')->group(function () {
        Route::get('fel/{documento}/pdf', [FacturaFelController::class, 'pdf'])->name('fel.pdf');
    });

    Route::middleware('permission:usuarios_compania.ver')->group(function () {
        Route::resource('usuarios-compania', UsuarioCompaniaController::class)
            ->only(['index'])
            ->parameters(['usuarios-compania' => 'user']);
    });

    Route::middleware('permission:usuarios_compania.gestionar')->group(function () {
        Route::resource('usuarios-compania', UsuarioCompaniaController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['usuarios-compania' => 'user']);
        Route::get('usuarios-compania/{user}/permisos', [UsuarioCompaniaController::class, 'editarPermisos'])->name('usuarios-compania.permisos.edit');
        Route::put('usuarios-compania/{user}/permisos', [UsuarioCompaniaController::class, 'actualizarPermisos'])->name('usuarios-compania.permisos.update');
    });

    // ── Bancos: Cheques y Depósitos ──────────────────────────────────────────
    Route::middleware('permission:bancos.ver')->group(function () {
        Route::get('bco/cheques', [BcoChequeController::class, 'index'])->name('bco.cheques.index');
        Route::get('bco/cheques/{cheque}', [BcoChequeController::class, 'show'])->whereNumber('cheque')->name('bco.cheques.show');
        Route::get('bco/depositos', [BcoDepositoController::class, 'index'])->name('bco.depositos.index');
        Route::get('bco/depositos/{deposito}', [BcoDepositoController::class, 'show'])->whereNumber('deposito')->name('bco.depositos.show');
    });
    Route::middleware('permission:bancos.gestionar')->group(function () {
        Route::get('bco/cheques/nuevo', [BcoChequeController::class, 'create'])->name('bco.cheques.create');
        Route::post('bco/cheques', [BcoChequeController::class, 'store'])->name('bco.cheques.store');
        Route::post('bco/cheques/{cheque}/estado', [BcoChequeController::class, 'cambiarEstado'])->whereNumber('cheque')->name('bco.cheques.estado');
        Route::get('bco/depositos/nuevo', [BcoDepositoController::class, 'create'])->name('bco.depositos.create');
        Route::post('bco/depositos', [BcoDepositoController::class, 'store'])->name('bco.depositos.store');
    });

    // ── Inventario avanzado ──────────────────────────────────────────────────
    Route::middleware('permission:inventario.ver')->group(function () {
        Route::get('inventario/kardex', [InvKardexController::class, 'index'])->name('inventario.kardex.index');
        Route::get('inventario/transferencias', [InvTransferenciaController::class, 'index'])->name('inventario.transferencias.index');
        Route::get('inventario/transferencias/{transferencia}', [InvTransferenciaController::class, 'show'])->whereNumber('transferencia')->name('inventario.transferencias.show');
    });
    Route::middleware('permission:inventario.gestionar')->group(function () {
        Route::get('inventario/transferencias/nueva', [InvTransferenciaController::class, 'create'])->name('inventario.transferencias.create');
        Route::post('inventario/transferencias', [InvTransferenciaController::class, 'store'])->name('inventario.transferencias.store');
        // Lista de precios de items
        Route::post('items/{item}/precios', [ItemPrecioController::class, 'store'])->whereNumber('item')->name('items.precios.store');
        Route::put('items/{item}/precios/{precio}', [ItemPrecioController::class, 'update'])->whereNumber(['item', 'precio'])->name('items.precios.update');
        Route::delete('items/{item}/precios/{precio}', [ItemPrecioController::class, 'destroy'])->whereNumber(['item', 'precio'])->name('items.precios.destroy');
    });

    // ── Ventas: Vendedores ───────────────────────────────────────────────────
    Route::middleware('permission:ventas.ver')->group(function () {
        Route::get('ventas/vendedores', [VentaVendedorController::class, 'index'])->name('ventas.vendedores.index');
        Route::get('ventas/vendedores/{vendedor}', [VentaVendedorController::class, 'show'])->whereNumber('vendedor')->name('ventas.vendedores.show');
    });
    Route::middleware('permission:ventas.gestionar')->group(function () {
        Route::get('ventas/vendedores/nuevo', [VentaVendedorController::class, 'create'])->name('ventas.vendedores.create');
        Route::post('ventas/vendedores', [VentaVendedorController::class, 'store'])->name('ventas.vendedores.store');
        Route::put('ventas/vendedores/{vendedor}', [VentaVendedorController::class, 'update'])->whereNumber('vendedor')->name('ventas.vendedores.update');
        Route::post('ventas/vendedores/{vendedor}/toggle', [VentaVendedorController::class, 'toggle'])->whereNumber('vendedor')->name('ventas.vendedores.toggle');
    });

    // ── Contactos extendidos ─────────────────────────────────────────────────
    Route::get('contactos/{contacto}/detalle', [ContactoExtController::class, 'show'])->whereNumber('contacto')->name('contactos.detalle');
    Route::post('contactos/{contacto}/cuentas-bancarias', [ContactoExtController::class, 'storeCuentaBancaria'])->whereNumber('contacto')->name('contactos.cuentas-bancarias.store');
    Route::delete('contactos/{contacto}/cuentas-bancarias/{cuenta}', [ContactoExtController::class, 'destroyCuentaBancaria'])->whereNumber(['contacto', 'cuenta'])->name('contactos.cuentas-bancarias.destroy');
    Route::post('contactos/{contacto}/direcciones', [ContactoExtController::class, 'storeDireccion'])->whereNumber('contacto')->name('contactos.direcciones.store');
    Route::delete('contactos/{contacto}/direcciones/{direccion}', [ContactoExtController::class, 'destroyDireccion'])->whereNumber(['contacto', 'direccion'])->name('contactos.direcciones.destroy');
    Route::post('contactos/{contacto}/personas', [ContactoExtController::class, 'storePersona'])->whereNumber('contacto')->name('contactos.personas.store');
    Route::delete('contactos/{contacto}/personas/{persona}', [ContactoExtController::class, 'destroyPersona'])->whereNumber(['contacto', 'persona'])->name('contactos.personas.destroy');

    // ── Activos Fijos: Revaluaciones ─────────────────────────────────────────
    Route::middleware('permission:activos.ver')->group(function () {
        Route::get('activos/{activo}/revaluaciones', [AfiRevaluacionController::class, 'create'])->whereNumber('activo')->name('activos.revaluaciones.create');
    });
    Route::middleware('permission:activos.gestionar')->group(function () {
        Route::post('activos/{activo}/revaluaciones', [AfiRevaluacionController::class, 'store'])->whereNumber('activo')->name('activos.revaluaciones.store');
    });

    // ── Cierre contable ──────────────────────────────────────────────────────
    Route::middleware('permission:contabilidad.ver')->group(function () {
        Route::get('contabilidad/cierres', [CglCierreController::class, 'index'])->name('contabilidad.cierres.index');
        Route::get('contabilidad/cierres/{cierre}', [CglCierreController::class, 'show'])->whereNumber('cierre')->name('contabilidad.cierres.show');
    });
    Route::middleware('permission:contabilidad.gestionar')->group(function () {
        Route::post('contabilidad/cierres', [CglCierreController::class, 'store'])->name('contabilidad.cierres.store');
        Route::post('contabilidad/cierres/{cierre}/cerrar', [CglCierreController::class, 'cerrar'])->whereNumber('cierre')->name('contabilidad.cierres.cerrar');
    });

    // ── Propiedad Horizontal (prh_*) ─────────────────────────────────────────
    Route::middleware('permission:prh.ver')->group(function () {
        Route::get('prh/edificios', [PrhEdificioController::class, 'index'])->name('prh.edificios.index');
        Route::get('prh/edificios/{edificio}', [PrhEdificioController::class, 'show'])->whereNumber('edificio')->name('prh.edificios.show');
        Route::get('prh/propietarios', [PrhPropietarioController::class, 'index'])->name('prh.propietarios.index');
        Route::get('prh/tipos-cuota', [PrhTipoCuotaController::class, 'index'])->name('prh.tipos-cuota.index');
        Route::get('prh/cuotas', [PrhCuotaController::class, 'index'])->name('prh.cuotas.index');
        Route::get('prh/pagos', [PrhPagoController::class, 'index'])->name('prh.pagos.index');
        Route::get('prh/edificios/{edificio}/unidades', [PrhUnidadController::class, 'index'])->whereNumber('edificio')->name('prh.edificios.unidades.index');
    });

    Route::middleware('permission:prh.gestionar')->group(function () {
        Route::get('prh/edificios/nuevo', [PrhEdificioController::class, 'create'])->name('prh.edificios.create');
        Route::post('prh/edificios', [PrhEdificioController::class, 'store'])->name('prh.edificios.store');
        Route::get('prh/edificios/{edificio}/editar', [PrhEdificioController::class, 'edit'])->whereNumber('edificio')->name('prh.edificios.edit');
        Route::put('prh/edificios/{edificio}', [PrhEdificioController::class, 'update'])->whereNumber('edificio')->name('prh.edificios.update');
        Route::delete('prh/edificios/{edificio}', [PrhEdificioController::class, 'destroy'])->whereNumber('edificio')->name('prh.edificios.destroy');

        Route::get('prh/edificios/{edificio}/unidades/nueva', [PrhUnidadController::class, 'create'])->whereNumber('edificio')->name('prh.edificios.unidades.create');
        Route::post('prh/edificios/{edificio}/unidades', [PrhUnidadController::class, 'store'])->whereNumber('edificio')->name('prh.edificios.unidades.store');
        Route::get('prh/edificios/{edificio}/unidades/{unidad}/editar', [PrhUnidadController::class, 'edit'])->whereNumber(['edificio', 'unidad'])->name('prh.edificios.unidades.edit');
        Route::put('prh/edificios/{edificio}/unidades/{unidad}', [PrhUnidadController::class, 'update'])->whereNumber(['edificio', 'unidad'])->name('prh.edificios.unidades.update');
        Route::delete('prh/edificios/{edificio}/unidades/{unidad}', [PrhUnidadController::class, 'destroy'])->whereNumber(['edificio', 'unidad'])->name('prh.edificios.unidades.destroy');

        Route::post('prh/propietarios', [PrhPropietarioController::class, 'store'])->name('prh.propietarios.store');
        Route::put('prh/propietarios/{propietario}', [PrhPropietarioController::class, 'update'])->whereNumber('propietario')->name('prh.propietarios.update');
        Route::delete('prh/propietarios/{propietario}', [PrhPropietarioController::class, 'destroy'])->whereNumber('propietario')->name('prh.propietarios.destroy');

        Route::post('prh/tipos-cuota', [PrhTipoCuotaController::class, 'store'])->name('prh.tipos-cuota.store');
        Route::put('prh/tipos-cuota/{tipoCuota}', [PrhTipoCuotaController::class, 'update'])->whereNumber('tipoCuota')->name('prh.tipos-cuota.update');
        Route::delete('prh/tipos-cuota/{tipoCuota}', [PrhTipoCuotaController::class, 'destroy'])->whereNumber('tipoCuota')->name('prh.tipos-cuota.destroy');

        Route::get('prh/cuotas/generar', [PrhCuotaController::class, 'generar'])->name('prh.cuotas.generar');
        Route::post('prh/cuotas/generar', [PrhCuotaController::class, 'procesarGenerar'])->name('prh.cuotas.procesarGenerar');
        Route::patch('prh/cuotas/{cuota}/anular', [PrhCuotaController::class, 'anular'])->whereNumber('cuota')->name('prh.cuotas.anular');

        Route::get('prh/pagos/nuevo', [PrhPagoController::class, 'create'])->name('prh.pagos.create');
        Route::post('prh/pagos', [PrhPagoController::class, 'store'])->name('prh.pagos.store');
        Route::delete('prh/pagos/{pago}', [PrhPagoController::class, 'destroy'])->whereNumber('pago')->name('prh.pagos.destroy');
    });

    // ── Ayuda / base de conocimientos ────────────────────────────────────────
    Route::get('ayuda', fn () => view('admin.ayuda.index'))->name('ayuda.index');

    // ── Configuración general (catálogos core) ───────────────────────────────
    Route::middleware('permission:contabilidad.ver')->group(function () {
        Route::get('configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
    });
    Route::middleware('permission:contabilidad.gestionar')->group(function () {
        Route::post('configuracion/sucursales', [ConfiguracionController::class, 'storeSucursal'])->name('configuracion.sucursales.store');
        Route::put('configuracion/sucursales/{sucursal}', [ConfiguracionController::class, 'updateSucursal'])->whereNumber('sucursal')->name('configuracion.sucursales.update');
        Route::post('configuracion/sucursales/{sucursal}/toggle', [ConfiguracionController::class, 'toggleSucursal'])->whereNumber('sucursal')->name('configuracion.sucursales.toggle');
        Route::post('configuracion/departamentos', [ConfiguracionController::class, 'storeDepartamento'])->name('configuracion.departamentos.store');
        Route::put('configuracion/departamentos/{departamento}', [ConfiguracionController::class, 'updateDepartamento'])->whereNumber('departamento')->name('configuracion.departamentos.update');
        Route::post('configuracion/centros-costo', [ConfiguracionController::class, 'storeCentroCosto'])->name('configuracion.centros-costo.store');
        Route::put('configuracion/centros-costo/{centroCosto}', [ConfiguracionController::class, 'updateCentroCosto'])->whereNumber('centroCosto')->name('configuracion.centros-costo.update');
        Route::post('configuracion/proyectos', [ConfiguracionController::class, 'storeProyecto'])->name('configuracion.proyectos.store');
        Route::put('configuracion/proyectos/{proyecto}', [ConfiguracionController::class, 'updateProyecto'])->whereNumber('proyecto')->name('configuracion.proyectos.update');
        Route::post('configuracion/monedas', [ConfiguracionController::class, 'storeMoneda'])->name('configuracion.monedas.store');
        Route::post('configuracion/tasas', [ConfiguracionController::class, 'storeTasa'])->name('configuracion.tasas.store');
        Route::post('configuracion/retenciones', [ConfiguracionController::class, 'storeRetencion'])->name('configuracion.retenciones.store');
        Route::put('configuracion/retenciones/{retencion}', [ConfiguracionController::class, 'updateRetencion'])->whereNumber('retencion')->name('configuracion.retenciones.update');
    });
});

require __DIR__.'/auth.php';
