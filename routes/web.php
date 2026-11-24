<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CapturaController;
use App\Http\Controllers\ReportesCapturasController;
use App\Http\Controllers\AuditoriaSellosController;
use App\Http\Controllers\AuditoriaCatastroController;
use App\Http\Controllers\TurnoController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\PeticionAutorizacionController;
use App\Http\Controllers\AccesoController;
use App\Http\Controllers\FlujoStellumController;
use App\Http\Controllers\InventarioEquiposController;
use App\Http\Controllers\DashboardAvanceController;

// =========================================================================
// AUTENTICACIÓN
// =========================================================================
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {

    Route::get('/', function () {
        $user = auth()->user();
        if ($user && $user->hasRole(['Captura', 'Digitalizador']) && !$user->hasRole(['superadmin', 'administrador'])) {
            return redirect()->route('admin.captura.index');
        }
        if ($user && $user->hasRole(['superadmin', 'administrador'])) {
            return redirect()->route('admin.dashboard.avance');
        }
        return redirect()->route('admin.analytics_sellos.reportes.lotes_trabajando');
    })->name('admin.index');

    // =========================================================================
    // SECCIÓN 1: RUTAS PARA CAPTURISTAS (/captura)
    // =========================================================================
    Route::prefix('captura')->group(function () {

        // Estación de captura y validación de PDFs
        Route::name('admin.captura.')->group(function () {
            Route::get('/', [CapturaController::class, 'index'])->name('index');
            Route::get('/buscar-pdf', [CapturaController::class, 'buscarPdf'])->name('buscarPdf');
            Route::get('/ver-pdf', [CapturaController::class, 'verPdf'])->name('verPdf');
            Route::get('/localidades', [CapturaController::class, 'obtenerLocalidades'])->name('localidades');
            Route::post('/renombrar', [CapturaController::class, 'renombrar'])->name('renombrar');
            Route::post('/validar', [CapturaController::class, 'validar'])->name('validar');
            Route::post('/duplicar', [CapturaController::class, 'duplicar'])->name('duplicar');
            Route::post('/inconsistencia', [CapturaController::class, 'inconsistencia'])->name('inconsistencia');
            Route::post('/rotar', [CapturaController::class, 'rotar'])->name('rotar');
            Route::post('/liberar-lote', [CapturaController::class, 'liberarLote'])->name('liberarLote');
            Route::post('/auditoria', [CapturaController::class, 'auditoria'])->name('auditoria');
        });

        // Peticiones de autorización enviadas desde la estación del capturista
        Route::post('/autorizaciones/solicitar', [PeticionAutorizacionController::class, 'solicitar'])
            ->name('admin.autorizaciones.solicitar');
        Route::get('/autorizaciones/{id}/estado', [PeticionAutorizacionController::class, 'estado'])
            ->name('admin.autorizaciones.estado');
    });

    // =========================================================================
    // SECCIÓN 2: RUTAS PARA ADMINISTRACIÓN Y SUPERVISIÓN (/admin)
    // Protegidas: los capturistas NO pueden acceder a ninguna ruta ni endpoint aquí
    // =========================================================================
    Route::prefix('admin')->middleware(['restringir.capturista'])->group(function () {

        // Dashboard Ejecutivo de Avance Global (exclusivo Administrador / Superadmin)
        Route::get('/dashboard-avance', [DashboardAvanceController::class, 'index'])->name('admin.dashboard.avance');

        // Centro de autorizaciones en tiempo real (WebSockets)
        Route::name('admin.autorizaciones.')->prefix('autorizaciones')->group(function () {
            Route::get('/', [PeticionAutorizacionController::class, 'index'])->name('index');
            Route::post('/listar', [PeticionAutorizacionController::class, 'listar'])->name('listar');
            Route::post('/{id}/resolver', [PeticionAutorizacionController::class, 'resolver'])->name('resolver');
        });

        // Indicadores, Productividad y Bitácora: REGISTROS
        Route::name('admin.analytics_capturas.reportes.')->prefix('registros')->group(function () {
            Route::get('/avance', [ReportesCapturasController::class, 'index_capturas'])->name('index');
            Route::get('/productividad', [ReportesCapturasController::class, 'index_reportes_capturas'])->name('usuarios');
            Route::get('/bitacora', [ReportesCapturasController::class, 'detalles_renombre_registros'])->name('detalles_renombre');
            Route::post('/dashboard', [ReportesCapturasController::class, 'dashboard_capturas'])->name('dashboard_capturas');
            Route::post('/descargar-excel', [ReportesCapturasController::class, 'descargar_excel'])->name('descargar_excel');
            Route::post('/descargar-excel-resumen', [ReportesCapturasController::class, 'descargar_excel_resumen'])->name('descargar_excel_resumen');
            Route::post('/descargar-excel-general', [ReportesCapturasController::class, 'descargar_excel_general'])->name('descargar_excel_general');
            Route::post('/descargar-excel-delegacion', [ReportesCapturasController::class, 'descargar_excel_delegacion'])->name('descargar_excel_delegacion');
        });

        // Indicadores, Productividad y Bitácora: NOTARÍAS
        Route::name('admin.analytics_digitalizacion.reportes.')->prefix('notarias')->group(function () {
            Route::get('/avance', [ReportesCapturasController::class, 'index_capturas_notarias'])->name('capturas');
            Route::get('/productividad', [ReportesCapturasController::class, 'index_reportes_capturas_notarias'])->name('reportes_capturas_notarias');
            Route::get('/bitacora', [ReportesCapturasController::class, 'detalles_renombre_notarias'])->name('detalles_renombre');
            Route::post('/dashboard', [ReportesCapturasController::class, 'dashboard_capturas_notarias'])->name('dashboard_capturas_notarias');
            Route::post('/descargar-excel', [ReportesCapturasController::class, 'descargar_excel_capturas_notarias'])->name('descargar_excel_capturas_notarias');
            Route::post('/descargar-excel-general', [ReportesCapturasController::class, 'descargar_excel_general'])->name('descargar_excel_general_notarias');
        });

        // Indicadores, Productividad, Bitácora, Explorador y Sesiones en Vivo: SELLOS
        Route::name('admin.analytics_sellos.reportes.')->prefix('sellos')->group(function () {
            Route::get('/avance', [AuditoriaSellosController::class, 'index_capturas'])->name('index');
            Route::get('/productividad', [AuditoriaSellosController::class, 'index_reportes_capturas'])->name('usuarios');
            Route::get('/bitacora', [AuditoriaSellosController::class, 'detalles_renombre'])->name('detalles_renombre');
            Route::post('/dashboard', [AuditoriaSellosController::class, 'dashboard_sellos'])->name('dashboard_sellos');
            Route::post('/descargar-excel', [AuditoriaSellosController::class, 'descargar_excel'])->name('descargar_excel');
            Route::post('/descargar-excel-resumen', [AuditoriaSellosController::class, 'descargar_excel_resumen'])->name('descargar_excel_resumen');
            Route::post('/descargar-excel-delegaciones', [AuditoriaSellosController::class, 'descargar_excel_delegaciones'])->name('descargar_excel_delegaciones');
            Route::post('/descargar-excel-dias', [AuditoriaSellosController::class, 'descargar_excel_dias'])->name('descargar_excel_dias');
            Route::get('/ver-pdf/{id}', [AuditoriaSellosController::class, 'ver_pdf'])->name('ver_pdf');
            Route::post('/editar-renombre', [AuditoriaSellosController::class, 'editar_renombre'])->name('editar_renombre');
            Route::post('/regresar-pdf', [AuditoriaSellosController::class, 'regresar_pdf'])->name('regresar_pdf');
            Route::post('/regresar-pdf-lote', [AuditoriaSellosController::class, 'regresar_pdf_lote'])->name('regresar_pdf_lote');
            Route::post('/reasignar-kpi', [AuditoriaSellosController::class, 'reasignar_registros_kpi'])->name('reasignar_registros_kpi');

            // Explorador Global de Archivos PDF
            Route::get('/buscar-archivos', [AuditoriaSellosController::class, 'index_buscar_archivos'])->name('buscar_archivos');
            Route::post('/obtener-busqueda-archivos', [AuditoriaSellosController::class, 'obtener_busqueda_archivos'])->name('obtener_busqueda_archivos');

            // Sesiones y Lotes Activos en Tiempo Real (WebSockets)
            Route::get('/sesiones-activas', [AuditoriaSellosController::class, 'index_lotes_trabajando'])->name('lotes_trabajando');
            Route::post('/obtener-sesiones-activas', [AuditoriaSellosController::class, 'obtener_lotes_trabajando'])->name('obtener_lotes_trabajando');
            Route::post('/eliminar-sesion-activa', [AuditoriaSellosController::class, 'eliminar_lote_trabajando'])->name('eliminar_lote_trabajando');
        });

        // Indicadores, Productividad y Bitácora: CATASTRO
        Route::name('admin.analytics_catastro.reportes.')->prefix('catastro')->group(function () {
            Route::get('/avance', [AuditoriaCatastroController::class, 'index_capturas'])->name('index');
            Route::get('/productividad', [AuditoriaCatastroController::class, 'index_reportes_capturas'])->name('usuarios');
            Route::get('/bitacora', [AuditoriaCatastroController::class, 'detalles_renombre'])->name('detalles_renombre');
            Route::post('/dashboard', [AuditoriaCatastroController::class, 'dashboard_catastro'])->name('dashboard_catastro');
            Route::post('/descargar-excel', [AuditoriaCatastroController::class, 'descargar_excel'])->name('descargar_excel');
            Route::post('/descargar-excel-resumen', [AuditoriaCatastroController::class, 'descargar_excel_resumen'])->name('descargar_excel_resumen');
            Route::post('/descargar-excel-municipios', [AuditoriaCatastroController::class, 'descargar_excel_municipios'])->name('descargar_excel_municipios');
            Route::post('/descargar-excel-dias', [AuditoriaCatastroController::class, 'descargar_excel_dias'])->name('descargar_excel_dias');
            Route::get('/ver-pdf/{id}', [AuditoriaCatastroController::class, 'ver_pdf'])->name('ver_pdf');
            Route::post('/editar-renombre', [AuditoriaCatastroController::class, 'editar_renombre'])->name('editar_renombre');
        });

        // CRUDs separados de Control de Acceso: Usuarios, Roles y Permisos
        Route::name('admin.usuarios.')->prefix('usuarios')->group(function () {
            Route::get('/', [AccesoController::class, 'usuarios'])->name('index');
            Route::post('/', [AccesoController::class, 'storeUser'])->name('store');
            Route::put('/{id}', [AccesoController::class, 'updateUser'])->name('update');
            Route::delete('/{id}', [AccesoController::class, 'destroyUser'])->name('destroy');
        });

        Route::name('admin.roles.')->prefix('roles')->group(function () {
            Route::get('/', [AccesoController::class, 'roles'])->name('index');
            Route::post('/', [AccesoController::class, 'storeRole'])->name('store');
            Route::put('/{id}', [AccesoController::class, 'updateRole'])->name('update');
            Route::delete('/{id}', [AccesoController::class, 'destroyRole'])->name('destroy');
        });

        Route::name('admin.permisos.')->prefix('permisos')->group(function () {
            Route::get('/', [AccesoController::class, 'permisos'])->name('index');
            Route::post('/', [AccesoController::class, 'storePermission'])->name('store');
            Route::put('/{id}', [AccesoController::class, 'updatePermission'])->name('update');
            Route::delete('/{id}', [AccesoController::class, 'destroyPermission'])->name('destroy');
        });

        // Turnos adaptativos
        Route::name('admin.turnos.')->prefix('turnos')->group(function () {
            Route::get('/', [TurnoController::class, 'index'])->name('index');
            Route::post('/', [TurnoController::class, 'store'])->name('store');
            Route::put('/{id}', [TurnoController::class, 'update'])->name('update');
            Route::delete('/{id}', [TurnoController::class, 'destroy'])->name('destroy');
            Route::post('/asignar', [TurnoController::class, 'asignarUsuarios'])->name('asignar');
        });

        // Catálogos: Rutas PDF, CRUD Delegaciones y CRUD Municipios/Localidades
        Route::name('admin.catalogos.')->prefix('catalogos')->group(function () {
            Route::get('/', [CatalogoController::class, 'index'])->name('index');
        });

        Route::name('admin.delegaciones.')->prefix('delegaciones')->group(function () {
            Route::get('/', [CatalogoController::class, 'delegaciones'])->name('index');
            Route::post('/', [CatalogoController::class, 'storeDelegacion'])->name('store');
            Route::put('/{id}', [CatalogoController::class, 'updateDelegacion'])->name('update');
            Route::delete('/{id}', [CatalogoController::class, 'destroyDelegacion'])->name('destroy');
        });

        Route::name('admin.municipios.')->prefix('municipios')->group(function () {
            Route::get('/', [CatalogoController::class, 'municipios'])->name('index');
            Route::post('/', [CatalogoController::class, 'storeMunicipio'])->name('store');
            Route::put('/{id}', [CatalogoController::class, 'updateMunicipio'])->name('update');
            Route::delete('/{id}', [CatalogoController::class, 'destroyMunicipio'])->name('destroy');
        });

        Route::name('admin.localidades.')->prefix('localidades')->group(function () {
            Route::post('/', [CatalogoController::class, 'storeLocalidad'])->name('store');
            Route::put('/{id}', [CatalogoController::class, 'updateLocalidad'])->name('update');
            Route::delete('/{id}', [CatalogoController::class, 'destroyLocalidad'])->name('destroy');
        });

        Route::name('admin.catalogo_inconsistencias.')->prefix('catalogo-inconsistencias')->group(function () {
            Route::get('/', [CatalogoController::class, 'catalogoInconsistencias'])->name('index');
            Route::post('/', [CatalogoController::class, 'storeCatalogoInconsistencia'])->name('store');
            Route::put('/{id}', [CatalogoController::class, 'updateCatalogoInconsistencia'])->name('update');
            Route::delete('/{id}', [CatalogoController::class, 'destroyCatalogoInconsistencia'])->name('destroy');
        });

        // Flujo Solo-BD + Stellum: Inconsistencias (R01-R09), Colisiones (revisar=1), Aprobación Masiva y Asignaciones
        Route::name('admin.inconsistencias.')->prefix('inconsistencias')->group(function () {
            Route::get('/', [FlujoStellumController::class, 'inconsistencias'])->name('index');
            Route::post('/{tipo}/{id}', [FlujoStellumController::class, 'actualizarInconsistencia'])->name('actualizar');
        });

        Route::name('admin.colisiones.')->prefix('colisiones')->group(function () {
            Route::get('/', [FlujoStellumController::class, 'colisiones'])->name('index');
            Route::post('/{tipo}/{id}', [FlujoStellumController::class, 'resolverColision'])->name('resolver');
        });

        Route::name('admin.aprobacion.')->prefix('aprobacion-renombres')->group(function () {
            Route::get('/', [FlujoStellumController::class, 'aprobacionRenombres'])->name('index');
            Route::post('/enviar', [FlujoStellumController::class, 'aprobarYEnviar'])->name('enviar');
            Route::get('/orden/{id}/sincronizar', [FlujoStellumController::class, 'sincronizarOrden'])->name('sincronizar');
        });

        Route::name('admin.asignaciones.')->prefix('asignaciones')->group(function () {
            Route::get('/', [FlujoStellumController::class, 'asignaciones'])->name('index');
            Route::post('/', [FlujoStellumController::class, 'guardarAsignacion'])->name('guardar');
            Route::delete('/{id}', [FlujoStellumController::class, 'eliminarAsignacion'])->name('eliminar');
        });

        // Inventario de Equipos sincronizado en tiempo real con Stellum + Asignación de PC por Usuario
        Route::name('admin.equipos.')->prefix('equipos')->group(function () {
            Route::get('/', [InventarioEquiposController::class, 'index'])->name('index');
            Route::match(['get', 'post'], '/api', [InventarioEquiposController::class, 'apiProxy'])->name('api');
            Route::get('/exportar-excel', [InventarioEquiposController::class, 'exportarExcel'])->name('exportar_excel');
            Route::post('/asignar-usuario', [InventarioEquiposController::class, 'asignarUsuario'])->name('asignar_usuario');
            Route::delete('/desasignar-usuario/{userId}', [InventarioEquiposController::class, 'desasignarUsuario'])->name('desasignar_usuario');
        });
    });
});
