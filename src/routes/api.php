<?php

use App\Http\Controllers\ReservaController;
use Illuminate\Support\Facades\Route;

Route::post('reservas/{reserva}/servicios', [ReservaController::class, 'agregarServicio']);
Route::apiResource('reservas', ReservaController::class);
