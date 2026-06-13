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
use App\Http\Controllers\Admin\AfiActivoController;
use App\Http\Controllers\Admin\AfiCategoriaController;
use App\Http\Controllers\Admin\AfiUbicacionController;
use App\Http\Controllers\Admin\CajaController;
use App\Http\Controllers\Admin\CajaOperacionController;
use App\Http\Controllers\Admin\CompraOrdenController;
use App\Http\Controllers\Admin\CompraRecepcionController;
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
use App\Http\Controllers\Admin\ZonaController;
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
        Route::get('caja/cajas', [CajaController::class, 'index'])->name('caja.cajas.index');
        Route::get('caja/cajas/{caja}', [CajaController::class, 'show'])->whereNumber('caja')->name('caja.cajas.show');
    });

    Route::middleware('permission:caja.gestionar')->group(function () {
        Route::post('caja/cajas', [CajaController::class, 'store'])->name('caja.cajas.store');
        Route::put('caja/cajas/{caja}', [CajaController::class, 'update'])->whereNumber('caja')->name('caja.cajas.update');
        Route::post('caja/cajas/{caja}/toggle', [CajaController::class, 'toggle'])->whereNumber('caja')->name('caja.cajas.toggle');
        Route::post('caja/cajas/{caja}/movimientos', [CajaOperacionController::class, 'movimiento'])->whereNumber('caja')->name('caja.cajas.movimientos');
        Route::post('caja/cajas/{caja}/reembolsos', [CajaOperacionController::class, 'reembolso'])->whereNumber('caja')->name('caja.cajas.reembolsos');
        Route::post('caja/cajas/{caja}/vales', [CajaOperacionController::class, 'vale'])->whereNumber('caja')->name('caja.cajas.vales');
        Route::post('caja/vales/{vale}/liquidar', [CajaOperacionController::class, 'liquidarVale'])->whereNumber('vale')->name('caja.vales.liquidar');
        Route::post('caja/cajas/{caja}/arqueos', [CajaOperacionController::class, 'arqueo'])->whereNumber('caja')->name('caja.cajas.arqueos');
    });

    Route::middleware('permission:activos.ver')->group(function () {
        Route::get('activos/activos', [AfiActivoController::class, 'index'])->name('activos.activos.index');
        Route::get('activos/activos/{activo}', [AfiActivoController::class, 'show'])->whereNumber('activo')->name('activos.activos.show');
        Route::get('activos/categorias', [AfiCategoriaController::class, 'index'])->name('activos.categorias.index');
        Route::get('activos/ubicaciones', [AfiUbicacionController::class, 'index'])->name('activos.ubicaciones.index');
    });

    Route::middleware('permission:activos.gestionar')->group(function () {
        Route::get('activos/activos/crear', [AfiActivoController::class, 'create'])->name('activos.activos.create');
        Route::post('activos/activos', [AfiActivoController::class, 'store'])->name('activos.activos.store');
        Route::post('activos/activos/{activo}/depreciar', [AfiActivoController::class, 'depreciar'])->whereNumber('activo')->name('activos.activos.depreciar');
        Route::post('activos/activos/{activo}/baja', [AfiActivoController::class, 'baja'])->whereNumber('activo')->name('activos.activos.baja');
        Route::post('activos/categorias', [AfiCategoriaController::class, 'store'])->name('activos.categorias.store');
        Route::put('activos/categorias/{categoria}', [AfiCategoriaController::class, 'update'])->whereNumber('categoria')->name('activos.categorias.update');
        Route::delete('activos/categorias/{categoria}', [AfiCategoriaController::class, 'destroy'])->whereNumber('categoria')->name('activos.categorias.destroy');
        Route::post('activos/ubicaciones', [AfiUbicacionController::class, 'store'])->name('activos.ubicaciones.store');
        Route::put('activos/ubicaciones/{ubicacion}', [AfiUbicacionController::class, 'update'])->whereNumber('ubicacion')->name('activos.ubicaciones.update');
        Route::delete('activos/ubicaciones/{ubicacion}', [AfiUbicacionController::class, 'destroy'])->whereNumber('ubicacion')->name('activos.ubicaciones.destroy');
    });

    Route::middleware('permission:ventas.ver')->group(function () {
        Route::get('ventas/cotizaciones', [VentaCotizacionController::class, 'index'])->name('ventas.cotizaciones.index');
        Route::get('ventas/cotizaciones/{cotizacion}', [VentaCotizacionController::class, 'show'])->whereNumber('cotizacion')->name('ventas.cotizaciones.show');
    });

    Route::middleware('permission:ventas.gestionar')->group(function () {
        Route::get('ventas/cotizaciones/nueva', [VentaCotizacionController::class, 'create'])->name('ventas.cotizaciones.create');
        Route::post('ventas/cotizaciones', [VentaCotizacionController::class, 'store'])->name('ventas.cotizaciones.store');
        Route::post('ventas/cotizaciones/{cotizacion}/estado', [VentaCotizacionController::class, 'cambiarEstado'])->whereNumber('cotizacion')->name('ventas.cotizaciones.estado');
        Route::post('ventas/cotizaciones/{cotizacion}/anular', [VentaCotizacionController::class, 'anular'])->whereNumber('cotizacion')->name('ventas.cotizaciones.anular');
        Route::post('ventas/cotizaciones/{cotizacion}/facturar', [VentaCotizacionController::class, 'facturar'])->whereNumber('cotizacion')->name('ventas.cotizaciones.facturar');
        Route::post('ventas/facturas/{factura}/anular', [VentaFacturaController::class, 'anular'])->whereNumber('factura')->name('ventas.facturas.anular');
    });

    Route::middleware('permission:ventas.ver')->group(function () {
        Route::get('ventas/facturas', [VentaFacturaController::class, 'index'])->name('ventas.facturas.index');
        Route::get('ventas/facturas/{factura}', [VentaFacturaController::class, 'show'])->whereNumber('factura')->name('ventas.facturas.show');
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
});

require __DIR__.'/auth.php';
