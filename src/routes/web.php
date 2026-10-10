<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard/Index');
});

Route::get('/reservas', function () {
    return Inertia::render('Reservas/Index');
});

Route::get('/reservas/crear', function () {
    return Inertia::render('Reservas/Create');
});

Route::get('/servicios', function () {
    return Inertia::render('Servicios/Index');
});

// Documentación interactiva de la API con Swagger UI
Route::get('/docs', function () {
    return view('swagger');
});
Route::get('/api/docs', function () {
    return view('swagger');
});
Route::get('/api/documentation', function () {
    return view('swagger');
});

