<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CapturaController;
use App\Http\Controllers\AuditoriaSellosController;
use App\Http\Controllers\AuditoriaCatastroController;
use App\Http\Controllers\NotariasController;
use App\Http\Controllers\Api\BiometricoApiController;

// Endpoints de sincronización de auditoría y sesiones en tiempo real
Route::prefix('auditoria')->group(function () {
    Route::post('registrar', [CapturaController::class, 'registrar']);
    Route::post('sesion', [CapturaController::class, 'registrarSesion']);
    Route::get('sesiones', [CapturaController::class, 'obtenerSesiones']);
    Route::get('usuarios/{id}/resumen-dia', [CapturaController::class, 'obtenerResumenDia']);

    Route::post('notarias/registrar', [NotariasController::class, 'registrar']);
    Route::post('notarias/sesion', [NotariasController::class, 'registrarSesion']);
    Route::get('notarias/sesiones', [NotariasController::class, 'obtenerSesiones']);

    Route::post('registro-sellos', [AuditoriaSellosController::class, 'registrar']);
    Route::post('registro-catastro', [AuditoriaCatastroController::class, 'registrar']);
});

// Catálogo de localidades para filtrado rápido
Route::get('/catalogos/localidades', [CapturaController::class, 'obtenerLocalidades']);

// API Segura para Estación Local de Chequeo Biométrico (Python)
Route::prefix('biometrico')
    ->middleware(['biometrico.token', 'throttle:120,1'])
    ->group(function () {
        Route::get('ping', [BiometricoApiController::class, 'ping']);
        Route::post('auth-admin', [BiometricoApiController::class, 'authAdmin']);
        Route::get('usuarios', [BiometricoApiController::class, 'usuarios']);
        Route::get('huellas', [BiometricoApiController::class, 'huellas']);
        Route::post('huellas', [BiometricoApiController::class, 'storeHuella']);
        Route::delete('huellas/{id}', [BiometricoApiController::class, 'destroyHuella']);
        Route::post('asistencia/checar', [BiometricoApiController::class, 'checar']);
        Route::get('asistencia/hoy', [BiometricoApiController::class, 'hoy']);
        Route::get('asistencia', [BiometricoApiController::class, 'historialAsistencia']);
    });
