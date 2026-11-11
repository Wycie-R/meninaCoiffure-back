<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Quien entra sin sesión ve la página pública del salón; el equipo va directo a su panel
Route::get('/', function () {
    if (auth()->check()) {
        return redirect(auth()->user()->paginaInicial());
    }

    return redirect('/salon');
});

// Página pública: catálogo, horario, promociones vigentes y contacto por WhatsApp
Route::get('/salon', function () {
    // Si una clienta entró con su cuenta, el encabezado la saluda y la lleva a sus citas
    $cuenta = null;
    if (auth()->check() && auth()->user()->hasRole('cliente')) {
        $cuenta = ['nombre' => auth()->user()->name];
    }

    return Inertia::render('Salon/Index', [
        'whatsapp' => config('app.salon_whatsapp'),
        'cuenta' => $cuenta,
    ]);
});

// Mis citas: solo para clientas con cuenta
Route::get('/mi-cuenta', function () {
    return Inertia::render('MiCuenta/Index');
})->middleware(['auth', 'rol:cliente']);

Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return Inertia::render('Auth/Login');
    })->name('login');

    // Una clienta crea su cuenta para pedir citas
    Route::get('/registro', function () {
        return Inertia::render('Auth/Registro');
    });

    Route::get('/recuperar-contrasena', function () {
        return Inertia::render('Auth/RecuperarContrasena');
    });

    // Enlace del correo de recuperación: el token es de un solo uso y vence (config/auth.php)
    Route::get('/restablecer-contrasena/{token}', function (Request $request, string $token) {
        return Inertia::render('Auth/RestablecerContrasena', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    });
});

// Pantallas del equipo: una clienta con sesión no entra al panel
Route::middleware(['auth', 'rol:admin,profesional'])->group(function () {
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

// Panel en vivo del balanceo A/B con HAProxy (para la exposición); no usa Vite para no depender del build
Route::get('/balanceo', function () {
    return view('balanceo');
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

