<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CapturaController;
use App\Http\Controllers\ReportesCapturasController;
use App\Http\Controllers\AuditoriaSellosController;
use App\Http\Controllers\AuditoriaCatastroController;
use App\Http\Controllers\NotariasController;
use App\Http\Controllers\TurnoController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\PeticionAutorizacionController;

// Autenticación
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {

    Route::get('/', function () {
        return redirect()->route('admin.analytics_sellos.reportes.lotes_trabajando');
    })->name('admin.index');

    // 1. ESTACIÓN DE CAPTURA WEB
    Route::name('admin.captura.')->prefix('captura')->group(function () {
        Route::get('/', [CapturaController::class, 'index'])->name('index');
        Route::get('/buscar-lote', [CapturaController::class, 'buscarLote'])->name('buscarLote');
        Route::get('/ver-pdf', [CapturaController::class, 'verPdf'])->name('verPdf');
        Route::get('/localidades', [CapturaController::class, 'obtenerLocalidades'])->name('localidades');
        Route::post('/renombrar', [CapturaController::class, 'renombrar'])->name('renombrar');
        Route::post('/validar', [CapturaController::class, 'validar'])->name('validar');
        Route::post('/duplicar', [CapturaController::class, 'duplicar'])->name('duplicar');
        Route::post('/inconsistencia', [CapturaController::class, 'inconsistencia'])->name('inconsistencia');
        Route::post('/mover-sellos', [CapturaController::class, 'moverSellos'])->name('moverSellos');
        Route::post('/rotar', [CapturaController::class, 'rotar'])->name('rotar');
        Route::post('/liberar-lote', [CapturaController::class, 'liberarLote'])->name('liberarLote');
        Route::post('/auditoria', [CapturaController::class, 'auditoria'])->name('auditoria');
    });

    // 2. CENTRO DE AUTORIZACIONES EN TIEMPO REAL (Reemplaza a PinAutorizacion)
    Route::name('admin.autorizaciones.')->prefix('autorizaciones')->group(function () {
        Route::get('/', [PeticionAutorizacionController::class, 'index'])->name('index');
        Route::post('/listar', [PeticionAutorizacionController::class, 'listar'])->name('listar');
        Route::post('/solicitar', [PeticionAutorizacionController::class, 'solicitar'])->name('solicitar');
        Route::get('/{id}/estado', [PeticionAutorizacionController::class, 'estado'])->name('estado');
        Route::post('/{id}/resolver', [PeticionAutorizacionController::class, 'resolver'])->name('resolver');
    });

    // 3. INDICADORES Y PRODUCTIVIDAD: REGISTROS (auditoria_registros)
    Route::name('admin.analytics_capturas.reportes.')->prefix('panel_control/analytics_capturas/reportes')->group(function () {
        Route::get('/index', [ReportesCapturasController::class, 'index_capturas'])->name('index');
        Route::get('/usuarios', [ReportesCapturasController::class, 'index_reportes_capturas'])->name('usuarios');
        Route::post('/dashboard_capturas', [ReportesCapturasController::class, 'dashboard_capturas'])->name('dashboard_capturas');
        Route::post('/descargar_excel', [ReportesCapturasController::class, 'descargar_excel'])->name('descargar_excel');
        Route::post('/descargar_excel_resumen', [ReportesCapturasController::class, 'descargar_excel_resumen'])->name('descargar_excel_resumen');
        Route::post('/descargar_excel_general', [ReportesCapturasController::class, 'descargar_excel_general'])->name('descargar_excel_general');
        Route::post('/descargar_excel_delegacion', [ReportesCapturasController::class, 'descargar_excel_delegacion'])->name('descargar_excel_delegacion');
    });

    // 4. INDICADORES Y PRODUCTIVIDAD: NOTARÍAS (auditoria_notarias)
    Route::name('admin.analytics_digitalizacion.reportes.')->prefix('panel_control/analytics_digitalizacion/reportes')->group(function () {
        Route::get('/capturas', [ReportesCapturasController::class, 'index_capturas_notarias'])->name('capturas');
        Route::get('/reportes_capturas_notarias', [ReportesCapturasController::class, 'index_reportes_capturas_notarias'])->name('reportes_capturas_notarias');
        Route::post('/dashboard_capturas_notarias', [ReportesCapturasController::class, 'dashboard_capturas_notarias'])->name('dashboard_capturas_notarias');
        Route::post('/descargar_excel_capturas_notarias', [ReportesCapturasController::class, 'descargar_excel_capturas_notarias'])->name('descargar_excel_capturas_notarias');
        Route::post('/descargar_excel_general_notarias', [ReportesCapturasController::class, 'descargar_excel_general'])->name('descargar_excel_general_notarias');
    });

    // 5. INDICADORES, PRODUCTIVIDAD, BITÁCORA, EXPLORADOR GLOBAL Y SESIONES EN VIVO: SELLOS (auditoria_sellos)
    Route::name('admin.analytics_sellos.reportes.')->prefix('panel_control/analytics_sellos/reportes')->group(function () {
        Route::get('/index', [AuditoriaSellosController::class, 'index_capturas'])->name('index');
        Route::get('/usuarios', [AuditoriaSellosController::class, 'index_reportes_capturas'])->name('usuarios');
        Route::post('/dashboard_sellos', [AuditoriaSellosController::class, 'dashboard_sellos'])->name('dashboard_sellos');
        Route::post('/descargar_excel', [AuditoriaSellosController::class, 'descargar_excel'])->name('descargar_excel');
        Route::post('/descargar_excel_resumen', [AuditoriaSellosController::class, 'descargar_excel_resumen'])->name('descargar_excel_resumen');
        Route::post('/descargar_excel_delegaciones', [AuditoriaSellosController::class, 'descargar_excel_delegaciones'])->name('descargar_excel_delegaciones');
        Route::post('/descargar_excel_dias', [AuditoriaSellosController::class, 'descargar_excel_dias'])->name('descargar_excel_dias');
        Route::get('/detalles_renombre', [AuditoriaSellosController::class, 'detalles_renombre'])->name('detalles_renombre');
        Route::get('/ver_pdf/{id}', [AuditoriaSellosController::class, 'ver_pdf'])->name('ver_pdf');
        Route::post('/editar_renombre', [AuditoriaSellosController::class, 'editar_renombre'])->name('editar_renombre');
        Route::post('/regresar_pdf', [AuditoriaSellosController::class, 'regresar_pdf'])->name('regresar_pdf');
        Route::post('/regresar_pdf_lote', [AuditoriaSellosController::class, 'regresar_pdf_lote'])->name('regresar_pdf_lote');
        Route::post('/reasignar_registros_kpi', [AuditoriaSellosController::class, 'reasignar_registros_kpi'])->name('reasignar_registros_kpi');

        // Explorador Global de Archivos PDF
        Route::get('/buscar_archivos', [AuditoriaSellosController::class, 'index_buscar_archivos'])->name('buscar_archivos');
        Route::post('/obtener_busqueda_archivos', [AuditoriaSellosController::class, 'obtener_busqueda_archivos'])->name('obtener_busqueda_archivos');

        // Sesiones y Lotes Activos en Tiempo Real (WebSockets)
        Route::get('/lotes_trabajando', [AuditoriaSellosController::class, 'index_lotes_trabajando'])->name('lotes_trabajando');
        Route::post('/obtener_lotes_trabajando', [AuditoriaSellosController::class, 'obtener_lotes_trabajando'])->name('obtener_lotes_trabajando');
        Route::post('/eliminar_lote_trabajando', [AuditoriaSellosController::class, 'eliminar_lote_trabajando'])->name('eliminar_lote_trabajando');
    });

    // 6. INDICADORES, PRODUCTIVIDAD Y BITÁCORA: CATASTRO (auditoria_catastro)
    Route::name('admin.analytics_catastro.reportes.')->prefix('panel_control/analytics_catastro/reportes')->group(function () {
        Route::get('/index', [AuditoriaCatastroController::class, 'index_capturas'])->name('index');
        Route::get('/usuarios', [AuditoriaCatastroController::class, 'index_reportes_capturas'])->name('usuarios');
        Route::post('/dashboard_catastro', [AuditoriaCatastroController::class, 'dashboard_catastro'])->name('dashboard_catastro');
        Route::post('/descargar_excel', [AuditoriaCatastroController::class, 'descargar_excel'])->name('descargar_excel');
        Route::post('/descargar_excel_resumen', [AuditoriaCatastroController::class, 'descargar_excel_resumen'])->name('descargar_excel_resumen');
        Route::post('/descargar_excel_municipios', [AuditoriaCatastroController::class, 'descargar_excel_municipios'])->name('descargar_excel_municipios');
        Route::post('/descargar_excel_dias', [AuditoriaCatastroController::class, 'descargar_excel_dias'])->name('descargar_excel_dias');
        Route::get('/detalles_renombre', [AuditoriaCatastroController::class, 'detalles_renombre'])->name('detalles_renombre');
        Route::get('/ver_pdf/{id}', [AuditoriaCatastroController::class, 'ver_pdf'])->name('ver_pdf');
        Route::post('/editar_renombre', [AuditoriaCatastroController::class, 'editar_renombre'])->name('editar_renombre');
    });

    // 7. CRUD DE TURNOS ADAPTATIVOS Y ASIGNACIÓN DE CAPTURISTAS
    Route::name('admin.turnos.')->prefix('turnos')->group(function () {
        Route::get('/', [TurnoController::class, 'index'])->name('index');
        Route::post('/', [TurnoController::class, 'store'])->name('store');
        Route::put('/{id}', [TurnoController::class, 'update'])->name('update');
        Route::delete('/{id}', [TurnoController::class, 'destroy'])->name('destroy');
        Route::post('/asignar', [TurnoController::class, 'asignarUsuarios'])->name('asignar');
    });

    // 8. CATÁLOGOS (DELEGACIONES, MUNICIPIOS, LOCALIDADES Y RUTAS PDF)
    Route::name('admin.catalogos.')->prefix('catalogos')->group(function () {
        Route::get('/', [CatalogoController::class, 'index'])->name('index');
    });
});
