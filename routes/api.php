<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CapturaController;
use App\Http\Controllers\AuditoriaSellosController;
use App\Http\Controllers\AuditoriaCatastroController;
use App\Http\Controllers\NotariasController;

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
