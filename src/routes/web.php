<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Quien entra sin sesión ve la página pública del salón; el equipo va directo a su panel
Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/dashboard');
    }

    return redirect('/salon');
});

// Página pública: catálogo, horario, promociones vigentes y contacto por WhatsApp
Route::get('/salon', function () {
    return Inertia::render('Salon/Index', [
        'whatsapp' => config('app.salon_whatsapp'),
    ]);
});

Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return Inertia::render('Auth/Login');
    })->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard/Index');
    })->name('dashboard');

    Route::get('/reservas', function () {
        return Inertia::render('Reservas/Index');
    });

    Route::get('/reservas/crear', function () {
        return Inertia::render('Reservas/Create');
    })->middleware('permiso:puede_crear_reservas');

    Route::get('/servicios', function () {
        return Inertia::render('Servicios/Index');
    });

    Route::get('/servicios/crear', function () {
        return Inertia::render('Servicios/Create');
    })->middleware('rol:admin');

    Route::get('/clientes', function () {
        return Inertia::render('Clientes/Index');
    });

    Route::get('/categorias', function () {
        return Inertia::render('Categorias/Index');
    });

    Route::get('/personal', function () {
        return Inertia::render('Personal/Index');
    });

    Route::get('/reportes', function () {
        return Inertia::render('Reportes/Index');
    })->middleware('rol:admin');

    Route::get('/configuracion', function () {
        return Inertia::render('Configuracion/Index');
    })->middleware('rol:admin');
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

