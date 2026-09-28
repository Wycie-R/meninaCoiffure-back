<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
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