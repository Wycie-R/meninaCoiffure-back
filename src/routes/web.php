<?php

use App\Http\Controllers\KeycloakController;
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
        // Con Keycloak, "Crear cuenta" abre directo el formulario de registro
        'proveedor' => config('services.keycloak.proveedor'),
    ]);
});

// Mis citas: solo para clientas con cuenta
Route::get('/mi-cuenta', function () {
    return Inertia::render('MiCuenta/Index');
})->middleware(['auth', 'rol:cliente']);

Route::middleware('guest')->group(function () {
    // Con AUTH_PROVEEDOR=keycloak se va directo al formulario de Keycloak (con el tema del salón):
    // el paso intermedio con un solo botón sobraba. Solo se queda acá para mostrar un error del login
    Route::get('/login', function () {
        $hayError = session()->has('error_login');
        if (config('services.keycloak.proveedor') === 'keycloak' && !$hayError) {
            return redirect('/auth/keycloak/redirigir');
        }

        return Inertia::render('Auth/Login', [
            'proveedor' => config('services.keycloak.proveedor'),
            'error' => session('error_login'),
        ]);
    })->name('login');

    // Login con Keycloak (Authorization Code + PKCE); responden 404 si el proveedor es local
    Route::get('/auth/keycloak/redirigir', [KeycloakController::class, 'redirigir']);
    Route::get('/auth/keycloak/callback', [KeycloakController::class, 'callback']);
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

