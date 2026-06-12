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
use App\Http\Controllers\Admin\PeriodoContableController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UsuarioCompaniaController;
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
    });
});

require __DIR__.'/auth.php';
