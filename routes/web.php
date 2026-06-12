<?php

use App\Http\Controllers\Admin\AsientoController;
use App\Http\Controllers\Admin\CompaniaController;
use App\Http\Controllers\Admin\ContactoController;
use App\Http\Controllers\Admin\CuentaContableController;
use App\Http\Controllers\Admin\ReporteBalanceController;
use App\Http\Controllers\Admin\ReporteComparativoController;
use App\Http\Controllers\Admin\ReporteResultadosController;
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
    });

    Route::middleware('permission:reportes.ver')->group(function () {
        Route::get('reportes/balance-situacion', ReporteBalanceController::class)->name('reportes.balance');
        Route::get('reportes/estado-resultado', ReporteResultadosController::class)->name('reportes.resultado');
        Route::get('reportes/comparativo-mensual', ReporteComparativoController::class)->name('reportes.comparativo');
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
