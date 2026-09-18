<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaServicioController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\ServicioController;
use Illuminate\Support\Facades\Route;

// HU-25 y T-02: Autenticación por rol y sesiones centralizadas
Route::prefix('auth')->middleware(['web'])->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
});

// HU-10 y HU-01: Categorías y Servicios con catálogo y precios vigentes
Route::apiResource('categorias-servicio', CategoriaServicioController::class);
Route::post('servicios/{servicio}/cambiar-precio', [ServicioController::class, 'cambiarPrecio']);
Route::apiResource('servicios', ServicioController::class);

// HU-07: Personal y turnos laborales
Route::post('personal/{personal}/turnos', [PersonalController::class, 'guardarTurnos']);
Route::apiResource('personal', PersonalController::class);

// Reservas
Route::post('reservas/{reserva}/servicios', [ReservaController::class, 'agregarServicio']);
Route::apiResource('reservas', ReservaController::class);

