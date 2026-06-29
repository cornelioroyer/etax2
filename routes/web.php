<?php

use App\Http\Controllers\Admin\AdjuntoController;
use App\Http\Controllers\Admin\AsientoController;
use App\Http\Controllers\Admin\AsientoRecurrenteController;
use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\BudgetEscenarioController;
use App\Http\Controllers\Admin\BudgetPresupuestoController;
use App\Http\Controllers\Admin\BudgetVersionController;
use App\Http\Controllers\Admin\CompaniaController;
use App\Http\Controllers\Admin\ContactoController;
use App\Http\Controllers\Admin\CuentaContableController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\CxcAntiguedadController;
use App\Http\Controllers\Admin\CxcCobroController;
use App\Http\Controllers\Admin\CxcEstadoCuentaController;
use App\Http\Controllers\Admin\CxcFacturaController;
use App\Http\Controllers\Admin\CxcNotaController;
use App\Http\Controllers\Admin\CxpAnticipoController;
use App\Http\Controllers\Admin\CxpAntiguedadController;
use App\Http\Controllers\Admin\CxpEstadoCuentaController;
use App\Http\Controllers\Admin\CxpFacturaController;
use App\Http\Controllers\Admin\CxpNotaController;
use App\Http\Controllers\Admin\CxpPagoController;
use App\Http\Controllers\Admin\CxpRecurrenteController;
use App\Http\Controllers\Admin\FacturaFelController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\RespaldoController;
use App\Http\Controllers\Admin\RestauracionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ReporteComprobacionController;
use App\Http\Controllers\Admin\ReporteBalanceController;
use App\Http\Controllers\Admin\ReporteComparativoController;
use App\Http\Controllers\Admin\ReporteResultadosController;
use App\Http\Controllers\Admin\FelConfiguracionController;
use App\Http\Controllers\Admin\CierreAnualController;
use App\Http\Controllers\Admin\CuentaDefaultController;
use App\Http\Controllers\Admin\PlantillaCuentaController;
use App\Http\Controllers\Admin\PlantillaCuentaDetalleController;
use App\Http\Controllers\Admin\DiarioController;
use App\Http\Controllers\Admin\PeriodoContableController;
use App\Http\Controllers\Admin\ReporteFlujoCajaController;
use App\Http\Controllers\Admin\ReporteLiquidacionItbmsController;
use App\Http\Controllers\Admin\ReporteCuadreAuxiliaresController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UsuarioCompaniaController;
use App\Http\Controllers\Admin\VentaCotizacionController;
use App\Http\Controllers\Admin\VentaFacturaController;
use App\Http\Controllers\Admin\VentaReciboController;
use App\Http\Controllers\Admin\VentaNotaCreditoController;
use App\Http\Controllers\Admin\VentaNotaDebitoController;
use App\Http\Controllers\Admin\VentaReembolsoController;
use App\Http\Controllers\Admin\BcoBancoController;
use App\Http\Controllers\Admin\BcoCuentaController;
use App\Http\Controllers\Admin\BcoMovimientoController;
use App\Http\Controllers\Admin\BcoTransferenciaController;
use App\Http\Controllers\Admin\BcoConciliacionController;
use App\Http\Controllers\Admin\AfiActivoController;
use App\Http\Controllers\Admin\AfiCategoriaController;
use App\Http\Controllers\Admin\AfiRevaluacionController;
use App\Http\Controllers\Admin\AfiUbicacionController;
use App\Http\Controllers\Admin\DimClaseController;
use App\Http\Controllers\Admin\DimLineaNegocioController;
use App\Http\Controllers\Admin\DimUbicacionController;
use App\Http\Controllers\Admin\PhCuotaController;
use App\Http\Controllers\Admin\PhEdificioController;
use App\Http\Controllers\Admin\PhPagoController;
use App\Http\Controllers\Admin\PhPropietarioController;
use App\Http\Controllers\Admin\PhTipoCuotaController;
use App\Http\Controllers\Admin\PhUnidadController;
use App\Http\Controllers\Admin\TallerAreaController;
use App\Http\Controllers\Admin\TallerChecklistController;
use App\Http\Controllers\Admin\TallerController;
use App\Http\Controllers\Admin\TallerEspecialidadController;
use App\Http\Controllers\Admin\TallerMarcaController;
use App\Http\Controllers\Admin\TallerModeloController;
use App\Http\Controllers\Admin\TallerServicioController;
use App\Http\Controllers\Admin\TallerSintomaController;
use App\Http\Controllers\Admin\TallerSucursalController;
use App\Http\Controllers\Admin\TallerEquipoController;
use App\Http\Controllers\Admin\TallerCitaController;
use App\Http\Controllers\Admin\TallerOrdenController;
use App\Http\Controllers\Admin\TallerPresupuestoController;
use App\Http\Controllers\Admin\TallerTecnicoController;
use App\Http\Controllers\Admin\TallerTipoEquipoController;
use App\Http\Controllers\Admin\BcoChequeController;
use App\Http\Controllers\Admin\CajaController;
use App\Http\Controllers\Admin\CajaOperacionController;
use App\Http\Controllers\Admin\CompraOrdenController;
use App\Http\Controllers\Admin\CompraRecepcionController;
use App\Http\Controllers\Admin\InvAlmacenController;
use App\Http\Controllers\Admin\InvExistenciasConsolidadoController;
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
use App\Http\Controllers\Admin\EduAsistenciaController;
use App\Http\Controllers\Admin\EduComunicadoController;
use App\Http\Controllers\Admin\EduConfiguracionController;
use App\Http\Controllers\Admin\EduConceptoCobroController;
use App\Http\Controllers\Admin\EduDocenteController;
use App\Http\Controllers\Admin\EduEstudianteController;
use App\Http\Controllers\Admin\EduEsquemaCalificacionController;
use App\Http\Controllers\Admin\EduEvaluacionController;
use App\Http\Controllers\Admin\EduGeneracionCobroController;
use App\Http\Controllers\Admin\EduGradoController;
use App\Http\Controllers\Admin\EduGrupoController;
use App\Http\Controllers\Admin\EduHorarioController;
use App\Http\Controllers\Admin\EduInstitucionController;
use App\Http\Controllers\Admin\EduMatriculaController;
use App\Http\Controllers\Admin\EduNivelAcademicoController;
use App\Http\Controllers\Admin\EduPeriodoAcademicoController;
use App\Http\Controllers\Admin\EduPlanCobroController;
use App\Http\Controllers\Admin\EduProgramaController;
use App\Http\Controllers\Admin\EduAsignaturaController;
use App\Http\Controllers\Admin\EduSedeController;
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
// Usuarios de plataforma (flag is_admin): solo super-admin.
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class)->except(['show']);

    // Catálogo de roles globales (solo super_admin).
    Route::resource('roles', RoleController::class)->except(['show']);

    // Administración del menú lateral (core_menu_items, catálogo global).
    Route::get('menu-items', [MenuItemController::class, 'index'])->name('menu-items.index');
    Route::get('menu-items/crear', [MenuItemController::class, 'create'])->name('menu-items.create');
    Route::post('menu-items', [MenuItemController::class, 'store'])->name('menu-items.store');
    Route::get('menu-items/{menuItem}/editar', [MenuItemController::class, 'edit'])->whereNumber('menuItem')->name('menu-items.edit');
    Route::put('menu-items/{menuItem}', [MenuItemController::class, 'update'])->whereNumber('menuItem')->name('menu-items.update');
    Route::post('menu-items/{menuItem}/toggle', [MenuItemController::class, 'toggle'])->whereNumber('menuItem')->name('menu-items.toggle');
    Route::post('menu-items/{menuItem}/mover', [MenuItemController::class, 'mover'])->whereNumber('menuItem')->name('menu-items.mover');
    Route::delete('menu-items/{menuItem}', [MenuItemController::class, 'destroy'])->whereNumber('menuItem')->name('menu-items.destroy');

    // Bitácora de actividad de usuarios (solo super_admin).
    Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
    // Auditoría GLOBAL (todas las compañías), solo super_admin.
    Route::get('auditoria-global', [AuditoriaController::class, 'globalIndex'])->name('auditoria.global');
    Route::get('auditoria/{actividad}', [AuditoriaController::class, 'show'])->whereNumber('actividad')->name('auditoria.show');

    // Asistente IA: chat en lenguaje natural. Solo super_admin. Las
    // herramientas internas se auto-restringen por permiso y compañía activa.
    Route::get('asistente', [ChatController::class, 'index'])->name('asistente');
    Route::post('asistente/mensaje', [ChatController::class, 'enviar'])->name('asistente.enviar');

    // Plantillas de plan de cuentas (maestro GLOBAL, se copia a compañías nuevas).
    // Solo super_admin. La cabecera y su detalle (cuentas) se administran aquí.
    Route::prefix('plantillas-cuentas')->name('plantillas-cuentas.')->group(function () {
        Route::get('/', [PlantillaCuentaController::class, 'index'])->name('index');
        Route::get('crear', [PlantillaCuentaController::class, 'create'])->name('create');
        Route::post('/', [PlantillaCuentaController::class, 'store'])->name('store');
        Route::get('{plantilla}', [PlantillaCuentaController::class, 'show'])->whereNumber('plantilla')->name('show');
        Route::get('{plantilla}/editar', [PlantillaCuentaController::class, 'edit'])->whereNumber('plantilla')->name('edit');
        Route::put('{plantilla}', [PlantillaCuentaController::class, 'update'])->whereNumber('plantilla')->name('update');
        Route::delete('{plantilla}', [PlantillaCuentaController::class, 'destroy'])->whereNumber('plantilla')->name('destroy');

        // Cuentas de la plantilla.
        Route::post('{plantilla}/cuentas', [PlantillaCuentaDetalleController::class, 'store'])->whereNumber('plantilla')->name('detalle.store');
        Route::get('{plantilla}/cuentas/{detalle}/editar', [PlantillaCuentaDetalleController::class, 'edit'])->whereNumber(['plantilla', 'detalle'])->name('detalle.edit');
        Route::put('{plantilla}/cuentas/{detalle}', [PlantillaCuentaDetalleController::class, 'update'])->whereNumber(['plantilla', 'detalle'])->name('detalle.update');
        Route::delete('{plantilla}/cuentas/{detalle}', [PlantillaCuentaDetalleController::class, 'destroy'])->whereNumber(['plantilla', 'detalle'])->name('detalle.destroy');
    });
});

// Usuarios por compañía: super-admin o admin de la compañía (permiso usuarios_compania.gestionar).
// El controlador limita la vista a los usuarios de la compañía activa que administra.
Route::middleware(['auth', 'permission:usuarios_compania.gestionar'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('usuarios-compania', UsuarioCompaniaController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['usuarios-compania' => 'user']);
    Route::get('usuarios-compania/{user}/permisos', [UsuarioCompaniaController::class, 'editarPermisos'])->name('usuarios-compania.permisos.edit');
    Route::put('usuarios-compania/{user}/permisos', [UsuarioCompaniaController::class, 'actualizarPermisos'])->name('usuarios-compania.permisos.update');
});

// Respaldos de la compañía: solo admin de la compañía (permiso respaldos.gestionar).
// Cada respaldo es un ZIP lógico con los datos de la compañía activa; la descarga
// re-verifica que el respaldo pertenezca a esa compañía (aislamiento, anti-IDOR).
Route::middleware(['auth', 'permission:respaldos.gestionar'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('respaldos', [RespaldoController::class, 'index'])->name('respaldos.index');
    Route::post('respaldos', [RespaldoController::class, 'store'])->name('respaldos.store');
    Route::get('respaldos/{respaldo}/estado', [RespaldoController::class, 'estado'])->whereNumber('respaldo')->name('respaldos.estado');
    Route::get('respaldos/{respaldo}/descargar', [RespaldoController::class, 'download'])->whereNumber('respaldo')->name('respaldos.download');
    Route::delete('respaldos/{respaldo}', [RespaldoController::class, 'destroy'])->whereNumber('respaldo')->name('respaldos.destroy');

    // Restauración de un respaldo en una compañía NUEVA (además exige is_admin en
    // el controlador: crear compañía es aprovisionamiento de sistema).
    Route::get('restauraciones', [RestauracionController::class, 'form'])->name('restauraciones.form');
    Route::post('restauraciones', [RestauracionController::class, 'store'])->name('restauraciones.store');
    Route::get('restauraciones/{restauracion}/estado', [RestauracionController::class, 'estado'])->whereNumber('restauracion')->name('restauraciones.estado');
});

// Módulos protegidos por permisos (por compañía)
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // Adjuntos centrales: la autorización por módulo la resuelve el controlador
    // según el registro de tablas permitidas (no por middleware de permiso).
    Route::post('adjuntos', [AdjuntoController::class, 'subir'])->name('adjuntos.subir');
    Route::get('adjuntos/{adjunto}', [AdjuntoController::class, 'descargar'])->whereNumber('adjunto')->name('adjuntos.descargar');
    Route::delete('adjuntos/{adjunto}', [AdjuntoController::class, 'eliminar'])->whereNumber('adjunto')->name('adjuntos.eliminar');

    Route::middleware('permission:zonas.ver')->group(function () {
        Route::resource('zonas', ZonaController::class)->except(['show'])->parameters(['zonas' => 'zona']);
    });

    Route::middleware('permission:companias.ver')->group(function () {
        Route::resource('companias', CompaniaController::class)->except(['show'])->parameters(['companias' => 'compania']);
    });

    Route::middleware('permission:contactos.ver')->group(function () {
        Route::get('contactos/plantilla', [ContactoController::class, 'plantillaImport'])->name('contactos.plantilla');
        Route::post('contactos/importar', [ContactoController::class, 'importar'])->name('contactos.importar');
        Route::get('contactos/plantilla-proveedores', [ContactoController::class, 'plantillaProveedores'])->name('contactos.plantilla-proveedores');
        Route::get('contactos/plantilla-proveedores-xlsx', [ContactoController::class, 'plantillaProveedoresXlsx'])->name('contactos.plantilla-proveedores-xlsx');
        Route::post('contactos/importar-proveedores', [ContactoController::class, 'importarProveedores'])->name('contactos.importar-proveedores');
        Route::resource('contactos', ContactoController::class)->except(['show'])->parameters(['contactos' => 'contacto']);
    });

    Route::middleware('permission:contabilidad.ver')->group(function () {
        Route::post('cuentas-aplicar-plantilla', [CuentaContableController::class, 'aplicarPlantilla'])->name('cuentas.aplicar-plantilla');
        Route::get('cuentas-importar/plantilla', [CuentaContableController::class, 'plantillaImport'])->name('cuentas.importar.plantilla');
        Route::get('cuentas-importar/plantilla-xlsx', [CuentaContableController::class, 'plantillaImportXlsx'])->name('cuentas.importar.plantilla-xlsx');
        Route::post('cuentas-importar', [CuentaContableController::class, 'importar'])->name('cuentas.importar');
        Route::resource('cuentas', CuentaContableController::class)->except(['show'])->parameters(['cuentas' => 'cuenta']);
        Route::get('asientos/{asiento}/copiar', [AsientoController::class, 'copiar'])->name('asientos.copiar');
        Route::post('asientos/{asiento}/postear', [AsientoController::class, 'postear'])->name('asientos.postear');
        Route::post('asientos/{asiento}/anular', [AsientoController::class, 'anular'])->name('asientos.anular');
        Route::get('asientos-importar', [AsientoController::class, 'importarForm'])->name('asientos.importar.form');
        Route::get('asientos-importar/plantilla', [AsientoController::class, 'plantillaImport'])->name('asientos.importar.plantilla');
        Route::post('asientos-importar', [AsientoController::class, 'importar'])->name('asientos.importar');
        Route::resource('asientos', AsientoController::class)->parameters(['asientos' => 'asiento']);
        // Asientos recurrentes (plantillas que generan asientos BORRADOR por vencimiento)
        Route::get('asientos-recurrentes/desde-asiento/{asiento}', [AsientoRecurrenteController::class, 'desdeAsiento'])->name('asientos-recurrentes.desde-asiento');
        Route::post('asientos-recurrentes/generar-todos', [AsientoRecurrenteController::class, 'generarTodos'])->name('asientos-recurrentes.generar-todos');
        Route::post('asientos-recurrentes/{asientos_recurrente}/generar', [AsientoRecurrenteController::class, 'generar'])->name('asientos-recurrentes.generar');
        Route::post('asientos-recurrentes/{asientos_recurrente}/pausar', [AsientoRecurrenteController::class, 'pausar'])->name('asientos-recurrentes.pausar');
        Route::post('asientos-recurrentes/{asientos_recurrente}/reactivar', [AsientoRecurrenteController::class, 'reactivar'])->name('asientos-recurrentes.reactivar');
        Route::resource('asientos-recurrentes', AsientoRecurrenteController::class)
            ->parameters(['asientos-recurrentes' => 'asientos_recurrente']);
        Route::get('cuentas-default', [CuentaDefaultController::class, 'index'])->name('cuentas-default.index');
        Route::put('cuentas-default', [CuentaDefaultController::class, 'update'])->name('cuentas-default.update');
        Route::get('diarios', [DiarioController::class, 'index'])->name('diarios.index');
        Route::post('diarios', [DiarioController::class, 'store'])->name('diarios.store');
        Route::put('diarios/{diario}', [DiarioController::class, 'update'])->name('diarios.update');
        Route::post('diarios/{diario}/toggle', [DiarioController::class, 'toggleActivo'])->name('diarios.toggle');
        Route::get('periodos', [PeriodoContableController::class, 'index'])->name('periodos.index');
        Route::post('periodos/crear-anio', [PeriodoContableController::class, 'crearAnio'])->name('periodos.crear-anio');
        Route::post('periodos/cerrar', [PeriodoContableController::class, 'cerrar'])->name('periodos.cerrar');
        Route::post('periodos/{periodo}/reabrir', [PeriodoContableController::class, 'reabrir'])->name('periodos.reabrir');
        Route::get('cierre-anual', [CierreAnualController::class, 'index'])->name('cierre-anual.index');
        Route::post('cierre-anual/cerrar', [CierreAnualController::class, 'cerrar'])->name('cierre-anual.cerrar');
        Route::post('cierre-anual/reversar', [CierreAnualController::class, 'reversar'])->name('cierre-anual.reversar');
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
        Route::post('cxc/notas/{documento}/corregir', [CxcNotaController::class, 'corregir'])->whereNumber('documento')->name('cxc.notas.corregir');
        Route::get('cxc/facturas/nueva', [CxcFacturaController::class, 'create'])->name('cxc.facturas.create');
        Route::post('cxc/facturas', [CxcFacturaController::class, 'store'])->name('cxc.facturas.store');
        Route::post('cxc/facturas/{documento}/anular', [CxcFacturaController::class, 'anular'])->whereNumber('documento')->name('cxc.facturas.anular');
        Route::post('cxc/facturas/{documento}/corregir', [CxcFacturaController::class, 'corregir'])->whereNumber('documento')->name('cxc.facturas.corregir');
        Route::get('cxc/cobros/nuevo', [CxcCobroController::class, 'create'])->name('cxc.cobros.create');
        Route::post('cxc/cobros', [CxcCobroController::class, 'store'])->name('cxc.cobros.store');
        Route::post('cxc/cobros/{documento}/anular', [CxcCobroController::class, 'anular'])->whereNumber('documento')->name('cxc.cobros.anular');
        Route::post('cxc/cobros/{documento}/corregir', [CxcCobroController::class, 'corregir'])->whereNumber('documento')->name('cxc.cobros.corregir');
    });

    Route::middleware('permission:cxp.ver')->group(function () {
        Route::get('cxp/facturas', [CxpFacturaController::class, 'index'])->name('cxp.facturas.index');
        Route::get('cxp/facturas/{documento}', [CxpFacturaController::class, 'show'])->whereNumber('documento')->name('cxp.facturas.show');
        Route::get('cxp/pagos', [CxpPagoController::class, 'index'])->name('cxp.pagos.index');
        Route::get('cxp/pagos/{documento}', [CxpPagoController::class, 'show'])->whereNumber('documento')->name('cxp.pagos.show');
        Route::get('cxp/pagos/{documento}/imprimir', [CxpPagoController::class, 'imprimir'])->whereNumber('documento')->name('cxp.pagos.imprimir');
        Route::get('cxp/anticipos', [CxpAnticipoController::class, 'index'])->name('cxp.anticipos.index');
        Route::get('cxp/anticipos/{documento}', [CxpAnticipoController::class, 'show'])->whereNumber('documento')->name('cxp.anticipos.show');
        Route::get('cxp/antiguedad', CxpAntiguedadController::class)->name('cxp.antiguedad');
        Route::get('cxp/estado-cuenta', CxpEstadoCuentaController::class)->name('cxp.estado-cuenta');
        Route::get('cxp/notas', [CxpNotaController::class, 'index'])->name('cxp.notas.index');
        Route::get('cxp/notas/{documento}', [CxpNotaController::class, 'show'])->whereNumber('documento')->name('cxp.notas.show');
        Route::get('cxp/recurrentes', [CxpRecurrenteController::class, 'index'])->name('cxp.recurrentes.index');
        Route::get('cxp/recurrentes/{recurrente}', [CxpRecurrenteController::class, 'show'])->whereNumber('recurrente')->name('cxp.recurrentes.show');
    });

    // Registrar factura de compra por QR/CUFE. Permiso granular propio
    // (cxp.registrar_qr) ADEMÁS de cxp.gestionar: un usuario de captura puede
    // escanear facturas (quedan en BORRADOR) sin tener toda la gestión de CxP.
    // Contabilizarlas sigue requiriendo cxp.gestionar.
    Route::middleware('permission:cxp.gestionar|cxp.registrar_qr')->group(function () {
        Route::get('cxp/facturas/desde-cufe', [CxpFacturaController::class, 'desdeCufeForm'])->name('cxp.facturas.desde-cufe.form');
        Route::post('cxp/facturas/desde-cufe', [CxpFacturaController::class, 'desdeCufe'])->name('cxp.facturas.desde-cufe');
        Route::post('cxp/facturas/consultar-cufe', [CxpFacturaController::class, 'consultarCufe'])->name('cxp.facturas.consultar-cufe');
        Route::post('cxp/facturas/cufe-desde-foto', [CxpFacturaController::class, 'cufeDesdeFoto'])->name('cxp.facturas.cufe-desde-foto');
    });

    Route::middleware('permission:cxp.gestionar')->group(function () {
        // Facturas recurrentes (plantillas que generan facturas de compra BORRADOR por vencimiento)
        Route::get('cxp/recurrentes/nueva', [CxpRecurrenteController::class, 'create'])->name('cxp.recurrentes.create');
        Route::post('cxp/recurrentes/generar-todos', [CxpRecurrenteController::class, 'generarTodos'])->name('cxp.recurrentes.generar-todos');
        Route::post('cxp/recurrentes', [CxpRecurrenteController::class, 'store'])->name('cxp.recurrentes.store');
        Route::get('cxp/recurrentes/{recurrente}/editar', [CxpRecurrenteController::class, 'edit'])->whereNumber('recurrente')->name('cxp.recurrentes.edit');
        Route::put('cxp/recurrentes/{recurrente}', [CxpRecurrenteController::class, 'update'])->whereNumber('recurrente')->name('cxp.recurrentes.update');
        Route::delete('cxp/recurrentes/{recurrente}', [CxpRecurrenteController::class, 'destroy'])->whereNumber('recurrente')->name('cxp.recurrentes.destroy');
        Route::post('cxp/recurrentes/{recurrente}/generar', [CxpRecurrenteController::class, 'generar'])->whereNumber('recurrente')->name('cxp.recurrentes.generar');
        Route::post('cxp/recurrentes/{recurrente}/pausar', [CxpRecurrenteController::class, 'pausar'])->whereNumber('recurrente')->name('cxp.recurrentes.pausar');
        Route::post('cxp/recurrentes/{recurrente}/reactivar', [CxpRecurrenteController::class, 'reactivar'])->whereNumber('recurrente')->name('cxp.recurrentes.reactivar');
        Route::get('cxp/notas/crear', [CxpNotaController::class, 'create'])->name('cxp.notas.create');
        Route::post('cxp/notas', [CxpNotaController::class, 'store'])->name('cxp.notas.store');
        Route::post('cxp/notas/{documento}/anular', [CxpNotaController::class, 'anular'])->whereNumber('documento')->name('cxp.notas.anular');
        Route::post('cxp/notas/{documento}/contabilizar', [CxpNotaController::class, 'contabilizar'])->whereNumber('documento')->name('cxp.notas.contabilizar');
        Route::delete('cxp/notas/{documento}', [CxpNotaController::class, 'destroy'])->whereNumber('documento')->name('cxp.notas.destroy');
        Route::get('cxp/facturas/nueva', [CxpFacturaController::class, 'create'])->name('cxp.facturas.create');
        Route::get('cxp/facturas/{factura}/archivo', [CxpFacturaController::class, 'archivo'])->whereNumber('factura')->name('cxp.facturas.archivo');
        Route::post('cxp/facturas/importar', [CxpFacturaController::class, 'importar'])->name('cxp.facturas.importar');
        Route::get('cxp/facturas/importar/{importacion}/progreso', [CxpFacturaController::class, 'importarProgreso'])->whereNumber('importacion')->name('cxp.facturas.importar.progreso');
        Route::get('cxp/facturas/importar/{importacion}/estado', [CxpFacturaController::class, 'importarEstado'])->whereNumber('importacion')->name('cxp.facturas.importar.estado');
        Route::get('cxp/facturas/importar-generico/plantilla', [CxpFacturaController::class, 'importarGenericoPlantilla'])->name('cxp.facturas.importar-generico.plantilla');
        Route::post('cxp/facturas/importar-generico', [CxpFacturaController::class, 'importarGenerico'])->name('cxp.facturas.importar-generico');
        Route::get('cxp/facturas/importar-saldos/plantilla', [CxpFacturaController::class, 'importarSaldosInicialesPlantilla'])->name('cxp.facturas.importar-saldos.plantilla');
        Route::post('cxp/facturas/importar-saldos', [CxpFacturaController::class, 'importarSaldosIniciales'])->name('cxp.facturas.importar-saldos');
        Route::post('cxp/facturas', [CxpFacturaController::class, 'store'])->name('cxp.facturas.store');
        Route::get('cxp/facturas/{documento}/editar', [CxpFacturaController::class, 'edit'])->whereNumber('documento')->name('cxp.facturas.edit');
        Route::put('cxp/facturas/{documento}', [CxpFacturaController::class, 'update'])->whereNumber('documento')->name('cxp.facturas.update');
        Route::post('cxp/facturas/{documento}/contabilizar', [CxpFacturaController::class, 'contabilizar'])->whereNumber('documento')->name('cxp.facturas.contabilizar');
        Route::delete('cxp/facturas/{documento}', [CxpFacturaController::class, 'destroy'])->whereNumber('documento')->name('cxp.facturas.destroy');
        Route::post('cxp/facturas/{documento}/anular', [CxpFacturaController::class, 'anular'])->whereNumber('documento')->name('cxp.facturas.anular');
        Route::post('cxp/facturas/{documento}/corregir', [CxpFacturaController::class, 'corregir'])->whereNumber('documento')->name('cxp.facturas.corregir');
        Route::get('cxp/facturas/{documento}/devolucion', [CxpFacturaController::class, 'devolucionForm'])->whereNumber('documento')->name('cxp.facturas.devolucion');
        Route::post('cxp/facturas/{documento}/devolucion', [CxpFacturaController::class, 'devolucionStore'])->whereNumber('documento')->name('cxp.facturas.devolucion.store');
        Route::post('cxp/facturas/bulk', [CxpFacturaController::class, 'bulk'])->name('cxp.facturas.bulk');
        Route::get('cxp/pagos/importar/plantilla', [CxpPagoController::class, 'importarPlantilla'])->name('cxp.pagos.importar.plantilla');
        Route::post('cxp/pagos/importar', [CxpPagoController::class, 'importar'])->name('cxp.pagos.importar');
        Route::get('cxp/pagos/nuevo', [CxpPagoController::class, 'create'])->name('cxp.pagos.create');
        Route::post('cxp/pagos', [CxpPagoController::class, 'store'])->name('cxp.pagos.store');
        Route::post('cxp/pagos/{documento}/anular', [CxpPagoController::class, 'anular'])->whereNumber('documento')->name('cxp.pagos.anular');
        Route::post('cxp/pagos/{documento}/corregir', [CxpPagoController::class, 'corregir'])->whereNumber('documento')->name('cxp.pagos.corregir');
        Route::get('cxp/anticipos/nuevo', [CxpAnticipoController::class, 'create'])->name('cxp.anticipos.create');
        Route::post('cxp/anticipos', [CxpAnticipoController::class, 'store'])->name('cxp.anticipos.store');
        Route::post('cxp/anticipos/{documento}/aplicar', [CxpAnticipoController::class, 'aplicar'])->whereNumber('documento')->name('cxp.anticipos.aplicar');
        Route::post('cxp/anticipos/{documento}/anular', [CxpAnticipoController::class, 'anular'])->whereNumber('documento')->name('cxp.anticipos.anular');
    });

    Route::middleware('permission:reportes.ver')->group(function () {
        Route::get('reportes/balance-situacion', ReporteBalanceController::class)->name('reportes.balance');
        Route::get('reportes/balance-comprobacion', ReporteComprobacionController::class)->name('reportes.comprobacion');
        Route::get('reportes/balance-comprobacion/detalle', [ReporteComprobacionController::class, 'detalle'])->name('reportes.comprobacion.detalle');
        Route::get('reportes/estado-resultado', ReporteResultadosController::class)->name('reportes.resultado');
        Route::get('reportes/comparativo-mensual', ReporteComparativoController::class)->name('reportes.comparativo');
        Route::get('reportes/flujo-caja', ReporteFlujoCajaController::class)->name('reportes.flujo-caja');
        Route::get('reportes/liquidacion-itbms', ReporteLiquidacionItbmsController::class)->name('reportes.liquidacion-itbms');
        Route::get('reportes/cuadre-auxiliares', ReporteCuadreAuxiliaresController::class)->name('reportes.cuadre-auxiliares');
    });

    Route::middleware('permission:compras.ver')->group(function () {
        Route::get('compras/ordenes', [CompraOrdenController::class, 'index'])->name('compras.ordenes.index');
        Route::get('compras/ordenes/{orden}', [CompraOrdenController::class, 'show'])->whereNumber('orden')->name('compras.ordenes.show');
        Route::get('compras/ordenes/{orden}/imprimir', [CompraOrdenController::class, 'imprimir'])->whereNumber('orden')->name('compras.ordenes.imprimir');
        Route::get('compras/ordenes/{orden}/recepciones/{recepcion}', [CompraRecepcionController::class, 'show'])->whereNumber(['orden', 'recepcion'])->name('compras.ordenes.recepciones.show');
    });

    Route::middleware('permission:compras.gestionar')->group(function () {
        Route::get('compras/ordenes/nueva', [CompraOrdenController::class, 'create'])->name('compras.ordenes.create');
        Route::post('compras/ordenes', [CompraOrdenController::class, 'store'])->name('compras.ordenes.store');
        Route::get('compras/ordenes/{orden}/editar', [CompraOrdenController::class, 'edit'])->whereNumber('orden')->name('compras.ordenes.edit');
        Route::put('compras/ordenes/{orden}', [CompraOrdenController::class, 'update'])->whereNumber('orden')->name('compras.ordenes.update');
        Route::post('compras/ordenes/{orden}/aprobar', [CompraOrdenController::class, 'aprobar'])->whereNumber('orden')->name('compras.ordenes.aprobar');
        Route::post('compras/ordenes/{orden}/anular', [CompraOrdenController::class, 'anular'])->whereNumber('orden')->name('compras.ordenes.anular');
        Route::post('compras/ordenes/{orden}/facturar', [CompraOrdenController::class, 'facturar'])->whereNumber('orden')->name('compras.ordenes.facturar');
        Route::post('compras/ordenes/{orden}/recepciones', [CompraRecepcionController::class, 'store'])->whereNumber('orden')->name('compras.ordenes.recepciones.store');
        Route::post('compras/ordenes/{orden}/recepciones/{recepcion}/anular', [CompraRecepcionController::class, 'anular'])->whereNumber(['orden', 'recepcion'])->name('compras.ordenes.recepciones.anular');
    });

    Route::middleware('permission:caja.ver')->group(function () {
        Route::get('caja', [CajaController::class, 'index'])->name('caja.index');
        Route::get('caja/movimiento/{movimiento}/archivo', [CajaOperacionController::class, 'archivo'])->whereNumber('movimiento')->name('caja.movimiento.archivo');
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
        Route::get('inventario/existencias', InvExistenciasConsolidadoController::class)->name('inventario.existencias.consolidado');
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
        Route::get('items-importar/plantilla-xlsx', [ItemProductoController::class, 'plantillaImportXlsx'])->name('items.importar.plantilla-xlsx');
        Route::get('items-importar/plantilla', [ItemProductoController::class, 'plantillaImportCsv'])->name('items.importar.plantilla');
        Route::post('items-importar', [ItemProductoController::class, 'importar'])->name('items.importar');
        Route::post('inventario/almacenes', [InvAlmacenController::class, 'store'])->name('inventario.almacenes.store');
        Route::put('inventario/almacenes/{almacen}', [InvAlmacenController::class, 'update'])->whereNumber('almacen')->name('inventario.almacenes.update');
        Route::post('inventario/almacenes/{almacen}/toggle', [InvAlmacenController::class, 'toggle'])->whereNumber('almacen')->name('inventario.almacenes.toggle');
        Route::get('inventario/movimientos/nuevo', [InvMovimientoController::class, 'create'])->name('inventario.movimientos.create');
        Route::post('inventario/movimientos', [InvMovimientoController::class, 'store'])->name('inventario.movimientos.store');
        Route::post('inventario/movimientos/{movimiento}/reversar', [InvMovimientoController::class, 'reversar'])->whereNumber('movimiento')->name('inventario.movimientos.reversar');
    });

    Route::middleware('permission:ventas.ver')->group(function () {
        Route::get('ventas/cotizaciones', [VentaCotizacionController::class, 'index'])->name('ventas.cotizaciones.index');
        Route::get('ventas/cotizaciones/{cotizacion}', [VentaCotizacionController::class, 'show'])->whereNumber('cotizacion')->name('ventas.cotizaciones.show');
        Route::get('ventas/cotizaciones/{cotizacion}/imprimir', [VentaCotizacionController::class, 'imprimir'])->whereNumber('cotizacion')->name('ventas.cotizaciones.imprimir');
    });

    Route::middleware('permission:ventas.gestionar')->group(function () {
        Route::get('ventas/cotizaciones/nueva', [VentaCotizacionController::class, 'create'])->name('ventas.cotizaciones.create');
        Route::post('ventas/cotizaciones', [VentaCotizacionController::class, 'store'])->name('ventas.cotizaciones.store');
        Route::get('ventas/cotizaciones/{cotizacion}/editar', [VentaCotizacionController::class, 'edit'])->whereNumber('cotizacion')->name('ventas.cotizaciones.edit');
        Route::put('ventas/cotizaciones/{cotizacion}', [VentaCotizacionController::class, 'update'])->whereNumber('cotizacion')->name('ventas.cotizaciones.update');
        Route::post('ventas/cotizaciones/{cotizacion}/estado', [VentaCotizacionController::class, 'cambiarEstado'])->whereNumber('cotizacion')->name('ventas.cotizaciones.estado');
        Route::post('ventas/cotizaciones/{cotizacion}/anular', [VentaCotizacionController::class, 'anular'])->whereNumber('cotizacion')->name('ventas.cotizaciones.anular');
        Route::post('ventas/cotizaciones/{cotizacion}/facturar', [VentaCotizacionController::class, 'facturar'])->whereNumber('cotizacion')->name('ventas.cotizaciones.facturar');
        Route::post('ventas/cotizaciones/{cotizacion}/email', [VentaCotizacionController::class, 'enviarEmail'])->whereNumber('cotizacion')->name('ventas.cotizaciones.email');
        Route::get('ventas/facturas/nueva', [VentaFacturaController::class, 'create'])->name('ventas.facturas.create');
        Route::post('ventas/facturas', [VentaFacturaController::class, 'store'])->name('ventas.facturas.store');
        Route::post('ventas/facturas/importar', [VentaFacturaController::class, 'importar'])->name('ventas.facturas.importar');
        Route::get('ventas/facturas/importar/{importacion}/progreso', [VentaFacturaController::class, 'importarProgreso'])->name('ventas.facturas.importar.progreso');
        Route::get('ventas/facturas/importar/{importacion}/estado', [VentaFacturaController::class, 'importarEstado'])->name('ventas.facturas.importar.estado');
        Route::get('ventas/facturas/importar-generico/plantilla', [VentaFacturaController::class, 'importarGenericoPlantilla'])->name('ventas.facturas.importar-generico.plantilla');
        Route::post('ventas/facturas/importar-generico', [VentaFacturaController::class, 'importarGenerico'])->name('ventas.facturas.importar-generico');
        Route::get('ventas/facturas/{factura}/editar', [VentaFacturaController::class, 'edit'])->whereNumber('factura')->name('ventas.facturas.edit');
        Route::put('ventas/facturas/{factura}', [VentaFacturaController::class, 'update'])->whereNumber('factura')->name('ventas.facturas.update');
        Route::post('ventas/facturas/{factura}/emitir', [VentaFacturaController::class, 'emitir'])->whereNumber('factura')->name('ventas.facturas.emitir');
        Route::post('ventas/facturas/{factura}/emitir-fel', [VentaFacturaController::class, 'emitirFel'])->whereNumber('factura')->name('ventas.facturas.emitir-fel');
        Route::post('ventas/facturas/{factura}/anular-fel', [VentaFacturaController::class, 'anularFel'])->whereNumber('factura')->name('ventas.facturas.anular-fel');
        Route::post('ventas/facturas/{factura}/anular', [VentaFacturaController::class, 'anular'])->whereNumber('factura')->name('ventas.facturas.anular');
        Route::post('ventas/facturas/{factura}/corregir', [VentaFacturaController::class, 'corregir'])->whereNumber('factura')->name('ventas.facturas.corregir');
        Route::post('ventas/facturas/{factura}/notas', [VentaFacturaController::class, 'actualizarNotas'])->whereNumber('factura')->name('ventas.facturas.notas');
    });

    Route::middleware('permission:ventas.ver')->group(function () {
        Route::get('ventas/facturas', [VentaFacturaController::class, 'index'])->name('ventas.facturas.index');
        Route::get('ventas/facturas/{factura}', [VentaFacturaController::class, 'show'])->whereNumber('factura')->name('ventas.facturas.show');
        Route::get('ventas/facturas/{factura}/imprimir', [VentaFacturaController::class, 'imprimir'])->whereNumber('factura')->name('ventas.facturas.imprimir');
        Route::get('ventas/recibos', [VentaReciboController::class, 'index'])->name('ventas.recibos.index');
        Route::get('ventas/recibos/{recibo}', [VentaReciboController::class, 'show'])->whereNumber('recibo')->name('ventas.recibos.show');
        Route::get('ventas/notas-credito', [VentaNotaCreditoController::class, 'index'])->name('ventas.notas-credito.index');
        Route::get('ventas/notas-credito/{notaCredito}', [VentaNotaCreditoController::class, 'show'])->whereNumber('notaCredito')->name('ventas.notas-credito.show');
        Route::get('ventas/notas-debito/{notaDebito}', [VentaNotaDebitoController::class, 'show'])->whereNumber('notaDebito')->name('ventas.notas-debito.show');
        Route::get('ventas/reembolsos/{reembolso}', [VentaReembolsoController::class, 'show'])->whereNumber('reembolso')->name('ventas.reembolsos.show');
    });

    Route::middleware('permission:ventas.gestionar')->group(function () {
        Route::get('ventas/recibos/importar/plantilla', [VentaReciboController::class, 'importarPlantilla'])->name('ventas.recibos.importar.plantilla');
        Route::post('ventas/recibos/importar', [VentaReciboController::class, 'importar'])->name('ventas.recibos.importar');
        Route::get('ventas/recibos/nuevo', [VentaReciboController::class, 'create'])->name('ventas.recibos.create');
        Route::post('ventas/recibos', [VentaReciboController::class, 'store'])->name('ventas.recibos.store');
        Route::post('ventas/recibos/{recibo}/anular', [VentaReciboController::class, 'anular'])->whereNumber('recibo')->name('ventas.recibos.anular');
        Route::get('ventas/notas-credito/nueva', [VentaNotaCreditoController::class, 'create'])->name('ventas.notas-credito.create');
        Route::post('ventas/notas-credito', [VentaNotaCreditoController::class, 'store'])->name('ventas.notas-credito.store');
        Route::post('ventas/notas-credito/{notaCredito}/anular', [VentaNotaCreditoController::class, 'anular'])->whereNumber('notaCredito')->name('ventas.notas-credito.anular');
        Route::post('ventas/notas-debito', [VentaNotaDebitoController::class, 'store'])->name('ventas.notas-debito.store');
        Route::post('ventas/notas-debito/{notaDebito}/anular', [VentaNotaDebitoController::class, 'anular'])->whereNumber('notaDebito')->name('ventas.notas-debito.anular');
        Route::post('ventas/reembolsos', [VentaReembolsoController::class, 'store'])->name('ventas.reembolsos.store');
        Route::post('ventas/reembolsos/{reembolso}/anular', [VentaReembolsoController::class, 'anular'])->whereNumber('reembolso')->name('ventas.reembolsos.anular');
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
        Route::post('bco/movimientos/{movimiento}/anular', [BcoMovimientoController::class, 'anular'])->whereNumber('movimiento')->name('bco.movimientos.anular');
        Route::post('bco/movimientos/{movimiento}/editar', [BcoMovimientoController::class, 'editar'])->whereNumber('movimiento')->name('bco.movimientos.editar');
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
        Route::get('fel/{documento}/editar', [FacturaFelController::class, 'editBorrador'])->name('fel.edit');
        Route::put('fel/{documento}', [FacturaFelController::class, 'updateBorrador'])->name('fel.update');
        Route::delete('fel/{documento}', [FacturaFelController::class, 'destroyBorrador'])->name('fel.destroy');
        Route::post('fel/{documento}/emitir', [FacturaFelController::class, 'emitirBorrador'])->name('fel.emitir');
    });

    Route::middleware('permission:fel.ver')->group(function () {
        Route::get('fel/{documento}/pdf', [FacturaFelController::class, 'pdf'])->name('fel.pdf');
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

    // ── Dimensiones analíticas (dim_*) ──────────────────────────────────────
    Route::middleware('permission:dimensiones.ver')->group(function () {
        Route::get('dimensiones/clases', [DimClaseController::class, 'index'])->name('dimensiones.clases.index');
        Route::get('dimensiones/lineas-negocio', [DimLineaNegocioController::class, 'index'])->name('dimensiones.lineas-negocio.index');
        Route::get('dimensiones/ubicaciones', [DimUbicacionController::class, 'index'])->name('dimensiones.ubicaciones.index');
    });
    Route::middleware('permission:dimensiones.gestionar')->group(function () {
        Route::post('dimensiones/clases', [DimClaseController::class, 'store'])->name('dimensiones.clases.store');
        Route::put('dimensiones/clases/{clase}', [DimClaseController::class, 'update'])->whereNumber('clase')->name('dimensiones.clases.update');
        Route::delete('dimensiones/clases/{clase}', [DimClaseController::class, 'destroy'])->whereNumber('clase')->name('dimensiones.clases.destroy');
        Route::post('dimensiones/lineas-negocio', [DimLineaNegocioController::class, 'store'])->name('dimensiones.lineas-negocio.store');
        Route::put('dimensiones/lineas-negocio/{lineaNegocio}', [DimLineaNegocioController::class, 'update'])->whereNumber('lineaNegocio')->name('dimensiones.lineas-negocio.update');
        Route::delete('dimensiones/lineas-negocio/{lineaNegocio}', [DimLineaNegocioController::class, 'destroy'])->whereNumber('lineaNegocio')->name('dimensiones.lineas-negocio.destroy');
        Route::post('dimensiones/ubicaciones', [DimUbicacionController::class, 'store'])->name('dimensiones.ubicaciones.store');
        Route::put('dimensiones/ubicaciones/{ubicacion}', [DimUbicacionController::class, 'update'])->whereNumber('ubicacion')->name('dimensiones.ubicaciones.update');
        Route::delete('dimensiones/ubicaciones/{ubicacion}', [DimUbicacionController::class, 'destroy'])->whereNumber('ubicacion')->name('dimensiones.ubicaciones.destroy');
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

    // ── Propiedad Horizontal (ph_*) ─────────────────────────────────────────
    Route::middleware('permission:ph.ver')->group(function () {
        Route::get('ph/edificios', [PhEdificioController::class, 'index'])->name('ph.edificios.index');
        Route::get('ph/edificios/{edificio}', [PhEdificioController::class, 'show'])->whereNumber('edificio')->name('ph.edificios.show');
        Route::get('ph/propietarios', [PhPropietarioController::class, 'index'])->name('ph.propietarios.index');
        Route::get('ph/tipos-cuota', [PhTipoCuotaController::class, 'index'])->name('ph.tipos-cuota.index');
        Route::get('ph/cuotas', [PhCuotaController::class, 'index'])->name('ph.cuotas.index');
        Route::get('ph/pagos', [PhPagoController::class, 'index'])->name('ph.pagos.index');
        Route::get('ph/edificios/{edificio}/unidades', [PhUnidadController::class, 'index'])->whereNumber('edificio')->name('ph.edificios.unidades.index');
    });

    Route::middleware('permission:ph.gestionar')->group(function () {
        Route::get('ph/edificios/nuevo', [PhEdificioController::class, 'create'])->name('ph.edificios.create');
        Route::post('ph/edificios', [PhEdificioController::class, 'store'])->name('ph.edificios.store');
        Route::get('ph/edificios/{edificio}/editar', [PhEdificioController::class, 'edit'])->whereNumber('edificio')->name('ph.edificios.edit');
        Route::put('ph/edificios/{edificio}', [PhEdificioController::class, 'update'])->whereNumber('edificio')->name('ph.edificios.update');
        Route::delete('ph/edificios/{edificio}', [PhEdificioController::class, 'destroy'])->whereNumber('edificio')->name('ph.edificios.destroy');

        Route::get('ph/edificios/{edificio}/unidades/nueva', [PhUnidadController::class, 'create'])->whereNumber('edificio')->name('ph.edificios.unidades.create');
        Route::post('ph/edificios/{edificio}/unidades', [PhUnidadController::class, 'store'])->whereNumber('edificio')->name('ph.edificios.unidades.store');
        Route::get('ph/edificios/{edificio}/unidades/{unidad}/editar', [PhUnidadController::class, 'edit'])->whereNumber(['edificio', 'unidad'])->name('ph.edificios.unidades.edit');
        Route::put('ph/edificios/{edificio}/unidades/{unidad}', [PhUnidadController::class, 'update'])->whereNumber(['edificio', 'unidad'])->name('ph.edificios.unidades.update');
        Route::delete('ph/edificios/{edificio}/unidades/{unidad}', [PhUnidadController::class, 'destroy'])->whereNumber(['edificio', 'unidad'])->name('ph.edificios.unidades.destroy');

        Route::post('ph/propietarios', [PhPropietarioController::class, 'store'])->name('ph.propietarios.store');
        Route::put('ph/propietarios/{propietario}', [PhPropietarioController::class, 'update'])->whereNumber('propietario')->name('ph.propietarios.update');
        Route::delete('ph/propietarios/{propietario}', [PhPropietarioController::class, 'destroy'])->whereNumber('propietario')->name('ph.propietarios.destroy');

        Route::post('ph/tipos-cuota', [PhTipoCuotaController::class, 'store'])->name('ph.tipos-cuota.store');
        Route::put('ph/tipos-cuota/{tipoCuota}', [PhTipoCuotaController::class, 'update'])->whereNumber('tipoCuota')->name('ph.tipos-cuota.update');
        Route::delete('ph/tipos-cuota/{tipoCuota}', [PhTipoCuotaController::class, 'destroy'])->whereNumber('tipoCuota')->name('ph.tipos-cuota.destroy');

        Route::get('ph/cuotas/generar', [PhCuotaController::class, 'generar'])->name('ph.cuotas.generar');
        Route::post('ph/cuotas/generar', [PhCuotaController::class, 'procesarGenerar'])->name('ph.cuotas.procesarGenerar');
        Route::patch('ph/cuotas/{cuota}/anular', [PhCuotaController::class, 'anular'])->whereNumber('cuota')->name('ph.cuotas.anular');

        Route::get('ph/pagos/nuevo', [PhPagoController::class, 'create'])->name('ph.pagos.create');
        Route::post('ph/pagos', [PhPagoController::class, 'store'])->name('ph.pagos.store');
        Route::delete('ph/pagos/{pago}', [PhPagoController::class, 'destroy'])->whereNumber('pago')->name('ph.pagos.destroy');
    });

    // ── Taller ───────────────────────────────────────────────────────────────
    Route::middleware('permission:taller.ver')->group(function () {
        // Presupuestos (ver)
        Route::get('taller/presupuestos', [TallerPresupuestoController::class, 'index'])->name('taller.presupuestos.index');
        Route::get('taller/presupuestos/{presupuesto}', [TallerPresupuestoController::class, 'show'])->whereNumber('presupuesto')->name('taller.presupuestos.show');
        // Citas (ver)
        Route::get('taller/citas', [TallerCitaController::class, 'index'])->name('taller.citas.index');
    });

    Route::middleware('permission:taller.ver')->group(function () {
        Route::get('taller/talleres', [TallerController::class, 'index'])->name('taller.talleres.index');
        Route::get('taller/talleres/{taller}', [TallerController::class, 'show'])->whereNumber('taller')->name('taller.talleres.show');
        Route::get('taller/sucursales', [TallerSucursalController::class, 'index'])->name('taller.sucursales.index');
        Route::get('taller/areas', [TallerAreaController::class, 'index'])->name('taller.areas.index');
        Route::get('taller/tipos-equipo', [TallerTipoEquipoController::class, 'index'])->name('taller.tipos-equipo.index');
        Route::get('taller/marcas', [TallerMarcaController::class, 'index'])->name('taller.marcas.index');
        Route::get('taller/modelos', [TallerModeloController::class, 'index'])->name('taller.modelos.index');
        Route::get('taller/especialidades', [TallerEspecialidadController::class, 'index'])->name('taller.especialidades.index');
        Route::get('taller/sintomas', [TallerSintomaController::class, 'index'])->name('taller.sintomas.index');
        Route::get('taller/servicios', [TallerServicioController::class, 'index'])->name('taller.servicios.index');
        Route::get('taller/checklists', [TallerChecklistController::class, 'index'])->name('taller.checklists.index');
        Route::get('taller/checklists/{checklist}', [TallerChecklistController::class, 'show'])->whereNumber('checklist')->name('taller.checklists.show');
        Route::get('taller/tecnicos', [TallerTecnicoController::class, 'index'])->name('taller.tecnicos.index');
        Route::get('taller/tecnicos/{tecnico}', [TallerTecnicoController::class, 'show'])->whereNumber('tecnico')->name('taller.tecnicos.show');
        Route::get('taller/equipos', [TallerEquipoController::class, 'index'])->name('taller.equipos.index');
        Route::get('taller/equipos/{equipo}', [TallerEquipoController::class, 'show'])->whereNumber('equipo')->name('taller.equipos.show');
        Route::get('taller/ordenes', [TallerOrdenController::class, 'index'])->name('taller.ordenes.index');
        Route::get('taller/ordenes/{orden}', [TallerOrdenController::class, 'show'])->whereNumber('orden')->name('taller.ordenes.show');
    });
    Route::middleware('permission:taller.gestionar')->group(function () {
        // Presupuestos (gestionar)
        Route::get('taller/presupuestos/nuevo', [TallerPresupuestoController::class, 'create'])->name('taller.presupuestos.create');
        Route::post('taller/presupuestos', [TallerPresupuestoController::class, 'store'])->name('taller.presupuestos.store');
        Route::get('taller/presupuestos/{presupuesto}/editar', [TallerPresupuestoController::class, 'edit'])->whereNumber('presupuesto')->name('taller.presupuestos.edit');
        Route::put('taller/presupuestos/{presupuesto}', [TallerPresupuestoController::class, 'update'])->whereNumber('presupuesto')->name('taller.presupuestos.update');
        Route::delete('taller/presupuestos/{presupuesto}', [TallerPresupuestoController::class, 'destroy'])->whereNumber('presupuesto')->name('taller.presupuestos.destroy');
        Route::post('taller/presupuestos/{presupuesto}/cambiar-estado', [TallerPresupuestoController::class, 'cambiarEstado'])->whereNumber('presupuesto')->name('taller.presupuestos.cambiar-estado');
        Route::post('taller/presupuestos/{presupuesto}/detalles', [TallerPresupuestoController::class, 'storeDetalle'])->whereNumber('presupuesto')->name('taller.presupuestos.detalles.store');
        Route::delete('taller/presupuestos/{presupuesto}/detalles/{detalle}', [TallerPresupuestoController::class, 'destroyDetalle'])->whereNumber(['presupuesto', 'detalle'])->name('taller.presupuestos.detalles.destroy');
        // Citas (gestionar)
        Route::get('taller/citas/nueva', [TallerCitaController::class, 'create'])->name('taller.citas.create');
        Route::post('taller/citas', [TallerCitaController::class, 'store'])->name('taller.citas.store');
        Route::get('taller/citas/{cita}/editar', [TallerCitaController::class, 'edit'])->whereNumber('cita')->name('taller.citas.edit');
        Route::put('taller/citas/{cita}', [TallerCitaController::class, 'update'])->whereNumber('cita')->name('taller.citas.update');
        Route::delete('taller/citas/{cita}', [TallerCitaController::class, 'destroy'])->whereNumber('cita')->name('taller.citas.destroy');
    });

    Route::middleware('permission:taller.gestionar')->group(function () {
        Route::get('taller/talleres/nuevo', [TallerController::class, 'create'])->name('taller.talleres.create');
        Route::post('taller/talleres', [TallerController::class, 'store'])->name('taller.talleres.store');
        Route::get('taller/talleres/{taller}/editar', [TallerController::class, 'edit'])->whereNumber('taller')->name('taller.talleres.edit');
        Route::put('taller/talleres/{taller}', [TallerController::class, 'update'])->whereNumber('taller')->name('taller.talleres.update');
        Route::delete('taller/talleres/{taller}', [TallerController::class, 'destroy'])->whereNumber('taller')->name('taller.talleres.destroy');

        Route::get('taller/sucursales/nueva', [TallerSucursalController::class, 'create'])->name('taller.sucursales.create');
        Route::post('taller/sucursales', [TallerSucursalController::class, 'store'])->name('taller.sucursales.store');
        Route::get('taller/sucursales/{sucursal}/editar', [TallerSucursalController::class, 'edit'])->whereNumber('sucursal')->name('taller.sucursales.edit');
        Route::put('taller/sucursales/{sucursal}', [TallerSucursalController::class, 'update'])->whereNumber('sucursal')->name('taller.sucursales.update');
        Route::delete('taller/sucursales/{sucursal}', [TallerSucursalController::class, 'destroy'])->whereNumber('sucursal')->name('taller.sucursales.destroy');

        Route::get('taller/areas/nueva', [TallerAreaController::class, 'create'])->name('taller.areas.create');
        Route::post('taller/areas', [TallerAreaController::class, 'store'])->name('taller.areas.store');
        Route::get('taller/areas/{area}/editar', [TallerAreaController::class, 'edit'])->whereNumber('area')->name('taller.areas.edit');
        Route::put('taller/areas/{area}', [TallerAreaController::class, 'update'])->whereNumber('area')->name('taller.areas.update');
        Route::delete('taller/areas/{area}', [TallerAreaController::class, 'destroy'])->whereNumber('area')->name('taller.areas.destroy');

        Route::get('taller/tipos-equipo/nuevo', [TallerTipoEquipoController::class, 'create'])->name('taller.tipos-equipo.create');
        Route::post('taller/tipos-equipo', [TallerTipoEquipoController::class, 'store'])->name('taller.tipos-equipo.store');
        Route::get('taller/tipos-equipo/{tipoEquipo}/editar', [TallerTipoEquipoController::class, 'edit'])->whereNumber('tipoEquipo')->name('taller.tipos-equipo.edit');
        Route::put('taller/tipos-equipo/{tipoEquipo}', [TallerTipoEquipoController::class, 'update'])->whereNumber('tipoEquipo')->name('taller.tipos-equipo.update');
        Route::delete('taller/tipos-equipo/{tipoEquipo}', [TallerTipoEquipoController::class, 'destroy'])->whereNumber('tipoEquipo')->name('taller.tipos-equipo.destroy');

        Route::get('taller/marcas/nueva', [TallerMarcaController::class, 'create'])->name('taller.marcas.create');
        Route::post('taller/marcas', [TallerMarcaController::class, 'store'])->name('taller.marcas.store');
        Route::get('taller/marcas/{marca}/editar', [TallerMarcaController::class, 'edit'])->whereNumber('marca')->name('taller.marcas.edit');
        Route::put('taller/marcas/{marca}', [TallerMarcaController::class, 'update'])->whereNumber('marca')->name('taller.marcas.update');
        Route::delete('taller/marcas/{marca}', [TallerMarcaController::class, 'destroy'])->whereNumber('marca')->name('taller.marcas.destroy');

        Route::get('taller/modelos/nuevo', [TallerModeloController::class, 'create'])->name('taller.modelos.create');
        Route::post('taller/modelos', [TallerModeloController::class, 'store'])->name('taller.modelos.store');
        Route::get('taller/modelos/{modelo}/editar', [TallerModeloController::class, 'edit'])->whereNumber('modelo')->name('taller.modelos.edit');
        Route::put('taller/modelos/{modelo}', [TallerModeloController::class, 'update'])->whereNumber('modelo')->name('taller.modelos.update');
        Route::delete('taller/modelos/{modelo}', [TallerModeloController::class, 'destroy'])->whereNumber('modelo')->name('taller.modelos.destroy');

        Route::get('taller/especialidades/nueva', [TallerEspecialidadController::class, 'create'])->name('taller.especialidades.create');
        Route::post('taller/especialidades', [TallerEspecialidadController::class, 'store'])->name('taller.especialidades.store');
        Route::get('taller/especialidades/{especialidad}/editar', [TallerEspecialidadController::class, 'edit'])->whereNumber('especialidad')->name('taller.especialidades.edit');
        Route::put('taller/especialidades/{especialidad}', [TallerEspecialidadController::class, 'update'])->whereNumber('especialidad')->name('taller.especialidades.update');
        Route::delete('taller/especialidades/{especialidad}', [TallerEspecialidadController::class, 'destroy'])->whereNumber('especialidad')->name('taller.especialidades.destroy');

        Route::get('taller/sintomas/nuevo', [TallerSintomaController::class, 'create'])->name('taller.sintomas.create');
        Route::post('taller/sintomas', [TallerSintomaController::class, 'store'])->name('taller.sintomas.store');
        Route::get('taller/sintomas/{sintoma}/editar', [TallerSintomaController::class, 'edit'])->whereNumber('sintoma')->name('taller.sintomas.edit');
        Route::put('taller/sintomas/{sintoma}', [TallerSintomaController::class, 'update'])->whereNumber('sintoma')->name('taller.sintomas.update');
        Route::delete('taller/sintomas/{sintoma}', [TallerSintomaController::class, 'destroy'])->whereNumber('sintoma')->name('taller.sintomas.destroy');

        Route::get('taller/servicios/nuevo', [TallerServicioController::class, 'create'])->name('taller.servicios.create');
        Route::post('taller/servicios', [TallerServicioController::class, 'store'])->name('taller.servicios.store');
        Route::get('taller/servicios/{servicio}/editar', [TallerServicioController::class, 'edit'])->whereNumber('servicio')->name('taller.servicios.edit');
        Route::put('taller/servicios/{servicio}', [TallerServicioController::class, 'update'])->whereNumber('servicio')->name('taller.servicios.update');
        Route::delete('taller/servicios/{servicio}', [TallerServicioController::class, 'destroy'])->whereNumber('servicio')->name('taller.servicios.destroy');

        Route::get('taller/checklists/nuevo', [TallerChecklistController::class, 'create'])->name('taller.checklists.create');
        Route::post('taller/checklists', [TallerChecklistController::class, 'store'])->name('taller.checklists.store');
        Route::get('taller/checklists/{checklist}/editar', [TallerChecklistController::class, 'edit'])->whereNumber('checklist')->name('taller.checklists.edit');
        Route::put('taller/checklists/{checklist}', [TallerChecklistController::class, 'update'])->whereNumber('checklist')->name('taller.checklists.update');
        Route::delete('taller/checklists/{checklist}', [TallerChecklistController::class, 'destroy'])->whereNumber('checklist')->name('taller.checklists.destroy');
        Route::post('taller/checklists/{checklist}/detalles', [TallerChecklistController::class, 'storeDetalle'])->whereNumber('checklist')->name('taller.checklists.detalles.store');
        Route::delete('taller/checklists/{checklist}/detalles/{detalle}', [TallerChecklistController::class, 'destroyDetalle'])->whereNumber(['checklist', 'detalle'])->name('taller.checklists.detalles.destroy');

        Route::get('taller/tecnicos/nuevo', [TallerTecnicoController::class, 'create'])->name('taller.tecnicos.create');
        Route::post('taller/tecnicos', [TallerTecnicoController::class, 'store'])->name('taller.tecnicos.store');
        Route::get('taller/tecnicos/{tecnico}/editar', [TallerTecnicoController::class, 'edit'])->whereNumber('tecnico')->name('taller.tecnicos.edit');
        Route::put('taller/tecnicos/{tecnico}', [TallerTecnicoController::class, 'update'])->whereNumber('tecnico')->name('taller.tecnicos.update');
        Route::delete('taller/tecnicos/{tecnico}', [TallerTecnicoController::class, 'destroy'])->whereNumber('tecnico')->name('taller.tecnicos.destroy');
        Route::post('taller/tecnicos/{tecnico}/especialidades', [TallerTecnicoController::class, 'storeEspecialidad'])->whereNumber('tecnico')->name('taller.tecnicos.especialidades.store');
        Route::delete('taller/tecnicos/{tecnico}/especialidades/{especialidad}', [TallerTecnicoController::class, 'destroyEspecialidad'])->whereNumber(['tecnico', 'especialidad'])->name('taller.tecnicos.especialidades.destroy');

        Route::get('taller/equipos/nuevo', [TallerEquipoController::class, 'create'])->name('taller.equipos.create');
        Route::post('taller/equipos', [TallerEquipoController::class, 'store'])->name('taller.equipos.store');
        Route::get('taller/equipos/{equipo}/editar', [TallerEquipoController::class, 'edit'])->whereNumber('equipo')->name('taller.equipos.edit');
        Route::put('taller/equipos/{equipo}', [TallerEquipoController::class, 'update'])->whereNumber('equipo')->name('taller.equipos.update');
        Route::delete('taller/equipos/{equipo}', [TallerEquipoController::class, 'destroy'])->whereNumber('equipo')->name('taller.equipos.destroy');
        Route::post('taller/equipos/{equipo}/clientes', [TallerEquipoController::class, 'storeCliente'])->whereNumber('equipo')->name('taller.equipos.clientes.store');
        Route::delete('taller/equipos/{equipo}/clientes/{clienteEquipo}', [TallerEquipoController::class, 'destroyCliente'])->whereNumber(['equipo', 'clienteEquipo'])->name('taller.equipos.clientes.destroy');
        Route::post('taller/equipos/{equipo}/mediciones', [TallerEquipoController::class, 'storeMedicion'])->whereNumber('equipo')->name('taller.equipos.mediciones.store');

        Route::get('taller/ordenes/nueva', [TallerOrdenController::class, 'create'])->name('taller.ordenes.create');
        Route::post('taller/ordenes', [TallerOrdenController::class, 'store'])->name('taller.ordenes.store');
        Route::get('taller/ordenes/{orden}/editar', [TallerOrdenController::class, 'edit'])->whereNumber('orden')->name('taller.ordenes.edit');
        Route::put('taller/ordenes/{orden}', [TallerOrdenController::class, 'update'])->whereNumber('orden')->name('taller.ordenes.update');
        Route::post('taller/ordenes/{orden}/cambiar-estado', [TallerOrdenController::class, 'cambiarEstado'])->whereNumber('orden')->name('taller.ordenes.cambiar-estado');
        Route::post('taller/ordenes/{orden}/sintomas', [TallerOrdenController::class, 'storeSintoma'])->whereNumber('orden')->name('taller.ordenes.sintomas.store');
        Route::delete('taller/ordenes/{orden}/sintomas/{sintoma}', [TallerOrdenController::class, 'destroySintoma'])->whereNumber(['orden', 'sintoma'])->name('taller.ordenes.sintomas.destroy');
        Route::post('taller/ordenes/{orden}/diagnosticos', [TallerOrdenController::class, 'storeDiagnostico'])->whereNumber('orden')->name('taller.ordenes.diagnosticos.store');
        Route::delete('taller/ordenes/{orden}/diagnosticos/{diagnostico}', [TallerOrdenController::class, 'destroyDiagnostico'])->whereNumber(['orden', 'diagnostico'])->name('taller.ordenes.diagnosticos.destroy');
        Route::post('taller/ordenes/{orden}/servicios', [TallerOrdenController::class, 'storeServicio'])->whereNumber('orden')->name('taller.ordenes.servicios.store');
        Route::delete('taller/ordenes/{orden}/servicios/{servicio}', [TallerOrdenController::class, 'destroyServicio'])->whereNumber(['orden', 'servicio'])->name('taller.ordenes.servicios.destroy');
        Route::post('taller/ordenes/{orden}/mano-obra', [TallerOrdenController::class, 'storeManoObra'])->whereNumber('orden')->name('taller.ordenes.mano-obra.store');
        Route::delete('taller/ordenes/{orden}/mano-obra/{manoObra}', [TallerOrdenController::class, 'destroyManoObra'])->whereNumber(['orden', 'manoObra'])->name('taller.ordenes.mano-obra.destroy');
        Route::post('taller/ordenes/{orden}/repuestos', [TallerOrdenController::class, 'storeRepuesto'])->whereNumber('orden')->name('taller.ordenes.repuestos.store');
        Route::delete('taller/ordenes/{orden}/repuestos/{repuesto}', [TallerOrdenController::class, 'destroyRepuesto'])->whereNumber(['orden', 'repuesto'])->name('taller.ordenes.repuestos.destroy');
        // Control de calidad, Entrega, Facturación
        Route::post('taller/ordenes/{orden}/control-calidad', [TallerOrdenController::class, 'storeControlCalidad'])->whereNumber('orden')->name('taller.ordenes.control-calidad.store');
        Route::post('taller/ordenes/{orden}/entrega', [TallerOrdenController::class, 'storeEntrega'])->whereNumber('orden')->name('taller.ordenes.entrega.store');
        Route::post('taller/ordenes/{orden}/facturacion', [TallerOrdenController::class, 'storeFacturacion'])->whereNumber('orden')->name('taller.ordenes.facturacion.store');
    });

    // ── Presupuestos ─────────────────────────────────────────────────────────
    Route::prefix('presupuestos')->name('presupuestos.')->group(function () {
        Route::middleware('permission:presupuestos.ver')->group(function () {
            Route::get('escenarios', [BudgetEscenarioController::class, 'index'])->name('escenarios.index');
            Route::get('versiones', [BudgetVersionController::class, 'index'])->name('versiones.index');
            Route::get('/', [BudgetPresupuestoController::class, 'index'])->name('index');
            Route::get('{presupuesto}', [BudgetPresupuestoController::class, 'show'])->whereNumber('presupuesto')->name('show');
        });

        Route::middleware('permission:presupuestos.gestionar')->group(function () {
            // Escenarios
            Route::get('escenarios/nuevo', [BudgetEscenarioController::class, 'create'])->name('escenarios.create');
            Route::post('escenarios', [BudgetEscenarioController::class, 'store'])->name('escenarios.store');
            Route::get('escenarios/{escenario}/editar', [BudgetEscenarioController::class, 'edit'])->whereNumber('escenario')->name('escenarios.edit');
            Route::put('escenarios/{escenario}', [BudgetEscenarioController::class, 'update'])->whereNumber('escenario')->name('escenarios.update');
            Route::delete('escenarios/{escenario}', [BudgetEscenarioController::class, 'destroy'])->whereNumber('escenario')->name('escenarios.destroy');

            // Versiones
            Route::get('versiones/nueva', [BudgetVersionController::class, 'create'])->name('versiones.create');
            Route::post('versiones', [BudgetVersionController::class, 'store'])->name('versiones.store');
            Route::get('versiones/{version}/editar', [BudgetVersionController::class, 'edit'])->whereNumber('version')->name('versiones.edit');
            Route::put('versiones/{version}', [BudgetVersionController::class, 'update'])->whereNumber('version')->name('versiones.update');
            Route::delete('versiones/{version}', [BudgetVersionController::class, 'destroy'])->whereNumber('version')->name('versiones.destroy');

            // Presupuestos
            Route::get('nuevo', [BudgetPresupuestoController::class, 'create'])->name('create');
            Route::post('/', [BudgetPresupuestoController::class, 'store'])->name('store');
            Route::get('{presupuesto}/editar', [BudgetPresupuestoController::class, 'edit'])->whereNumber('presupuesto')->name('edit');
            Route::put('{presupuesto}', [BudgetPresupuestoController::class, 'update'])->whereNumber('presupuesto')->name('update');
            Route::delete('{presupuesto}', [BudgetPresupuestoController::class, 'destroy'])->whereNumber('presupuesto')->name('destroy');
            Route::post('{presupuesto}/cambiar-estado', [BudgetPresupuestoController::class, 'cambiarEstado'])->whereNumber('presupuesto')->name('cambiar-estado');
            Route::post('{presupuesto}/calcular-real', [BudgetPresupuestoController::class, 'calcularReal'])->whereNumber('presupuesto')->name('calcular-real');
            Route::post('{presupuesto}/detalle', [BudgetPresupuestoController::class, 'storeDetalle'])->whereNumber('presupuesto')->name('detalle.store');
            Route::delete('{presupuesto}/detalle/{detalle}', [BudgetPresupuestoController::class, 'destroyDetalle'])->whereNumber(['presupuesto', 'detalle'])->name('detalle.destroy');
        });
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

    // ── Educación ────────────────────────────────────────────────────────────
    Route::prefix('edu')->name('edu.')->middleware('permission:edu.ver')->group(function () {
        Route::resource('instituciones', EduInstitucionController::class)->except(['show'])->parameters(['instituciones' => 'institucion']);
        Route::resource('sedes', EduSedeController::class)->except(['show'])->parameters(['sedes' => 'sede']);
        Route::get('configuracion', [EduConfiguracionController::class, 'index'])->name('configuracion.index');
        Route::resource('niveles', EduNivelAcademicoController::class)->except(['show'])->parameters(['niveles' => 'nivel']);
        Route::resource('programas', EduProgramaController::class)->except(['show'])->parameters(['programas' => 'programa']);
        Route::resource('grados', EduGradoController::class)->except(['show'])->parameters(['grados' => 'grado']);
        Route::resource('grupos', EduGrupoController::class)->except(['show'])->parameters(['grupos' => 'grupo']);
        Route::resource('periodos', EduPeriodoAcademicoController::class)->except(['show'])->parameters(['periodos' => 'periodo']);
        Route::resource('asignaturas', EduAsignaturaController::class)->except(['show'])->parameters(['asignaturas' => 'asignatura']);
        Route::resource('esquemas', EduEsquemaCalificacionController::class)->except(['show'])->parameters(['esquemas' => 'esquema']);
        Route::post('esquemas/{esquema}/detalles', [EduEsquemaCalificacionController::class, 'storeDetalle'])->whereNumber('esquema')->name('esquemas.detalles.store');
        Route::delete('esquemas/{esquema}/detalles/{detalle}', [EduEsquemaCalificacionController::class, 'destroyDetalle'])->whereNumber(['esquema', 'detalle'])->name('esquemas.detalles.destroy');
        Route::resource('estudiantes', EduEstudianteController::class)->parameters(['estudiantes' => 'estudiante']);
        Route::resource('docentes', EduDocenteController::class)->parameters(['docentes' => 'docente']);
        Route::resource('matriculas', EduMatriculaController::class)->parameters(['matriculas' => 'matricula']);
        Route::resource('horarios', EduHorarioController::class)->except(['show'])->parameters(['horarios' => 'horario']);
        Route::resource('evaluaciones', EduEvaluacionController::class)->parameters(['evaluaciones' => 'evaluacion']);
        Route::get('asistencias', [EduAsistenciaController::class, 'index'])->name('asistencias.index');
        Route::post('asistencias', [EduAsistenciaController::class, 'store'])->name('asistencias.store');
        Route::resource('conceptos-cobro', EduConceptoCobroController::class)->except(['show'])->parameters(['conceptos-cobro' => 'concepto']);
        Route::resource('planes-cobro', EduPlanCobroController::class)->except(['show'])->parameters(['planes-cobro' => 'plan']);
        Route::resource('generaciones-cobro', EduGeneracionCobroController::class)->except(['show', 'edit', 'update'])->parameters(['generaciones-cobro' => 'generacion']);
        Route::resource('comunicados', EduComunicadoController::class)->parameters(['comunicados' => 'comunicado']);
    });
    Route::prefix('edu')->name('edu.')->middleware('permission:edu.gestionar')->group(function () {
        Route::put('configuracion', [EduConfiguracionController::class, 'update'])->name('configuracion.update');
    });
});

require __DIR__.'/auth.php';
