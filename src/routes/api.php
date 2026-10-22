<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaServicioController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\DescuentoController;
use App\Http\Controllers\EspecialidadController;
use App\Http\Controllers\HistorialVisitaController;
use App\Http\Controllers\HorarioAtencionController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\PreferenciaClienteController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\ServicioController;
use Illuminate\Support\Facades\Route;

// HU-25 y T-02: Autenticación por rol y sesiones centralizadas
Route::prefix('auth')->middleware(['web'])->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
});

// HU-01: Consulta pública del catálogo (servicios, precios vigentes y categorías)
Route::apiResource('categorias-servicio', CategoriaServicioController::class)->only(['index', 'show']);
Route::apiResource('servicios', ServicioController::class)->only(['index', 'show']);

// El resto exige sesión iniciada (la sesión vive en PostgreSQL, compartida entre nodos)
// y un rol del salón: admin (la dueña) o profesional (solo opera sobre su propia agenda)
Route::middleware(['web', 'auth', 'rol:admin,profesional'])->group(function () {
    // Consulta del equipo, especialidades, horario del salón, métodos de pago y promociones
    Route::get('personal', [PersonalController::class, 'index']);
    Route::get('personal/{personal}', [PersonalController::class, 'show']);
    Route::get('especialidades', [EspecialidadController::class, 'index']);
    Route::get('horarios-atencion', [HorarioAtencionController::class, 'index']);
    Route::get('metodos-pago', [MetodoPagoController::class, 'index']);
    Route::get('descuentos', [DescuentoController::class, 'index']);

    // Cada profesional ve siempre su agenda. Lo demás depende de los permisos que la dueña
    // le habilita en Configuración (middleware permiso; la administración siempre pasa)
    Route::get('reservas', [ReservaController::class, 'index']);
    Route::get('reservas/{reserva}', [ReservaController::class, 'show']);
    // PUT revisa el permiso según lo que cambia (reprogramar o cambiar el estado) en el controlador
    Route::match(['put', 'patch'], 'reservas/{reserva}', [ReservaController::class, 'update']);

    // Reservas y disponibilidad (HU-02, HU-03, HU-04, HU-06, HU-12 y HU-14)
    Route::middleware('permiso:puede_crear_reservas')->group(function () {
        Route::get('disponibilidad', [ReservaController::class, 'disponibilidad']);
        Route::post('reservas', [ReservaController::class, 'store']);
        Route::post('reservas/{reserva}/servicios', [ReservaController::class, 'agregarServicio']);
    });
    Route::delete('reservas/{reserva}', [ReservaController::class, 'destroy'])
        ->middleware('permiso:puede_cambiar_estado');

    // Clientes: buscar o dar de alta a una clienta sirve para agendar; el expediente es aparte
    Route::middleware('permiso:puede_ver_clientes,puede_crear_reservas')->group(function () {
        Route::get('clientes', [ClienteController::class, 'index']);
        Route::post('clientes', [ClienteController::class, 'store']);
    });

    // Expediente de la clienta (HU-05 y HU-13)
    Route::middleware('permiso:puede_ver_clientes')->group(function () {
        Route::get('clientes/{cliente}', [ClienteController::class, 'show']);
        Route::match(['put', 'patch'], 'clientes/{cliente}', [ClienteController::class, 'update']);
        Route::get('clientes/{cliente}/preferencias', [PreferenciaClienteController::class, 'index']);
        Route::post('clientes/{cliente}/preferencias', [PreferenciaClienteController::class, 'store']);
        Route::put('preferencias/{preferencia}', [PreferenciaClienteController::class, 'update']);
        Route::delete('preferencias/{preferencia}', [PreferenciaClienteController::class, 'destroy']);
        Route::get('clientes/{cliente}/historial', [HistorialVisitaController::class, 'index']);
        Route::put('historial-visitas/{historial}', [HistorialVisitaController::class, 'update']);
    });

    // Solo administración
    Route::middleware('rol:admin')->group(function () {
        // HU-09, HU-10 y HU-11: Catálogo y precios con historial
        Route::apiResource('categorias-servicio', CategoriaServicioController::class)->except(['index', 'show']);
        Route::post('servicios/{servicio}/cambiar-precio', [ServicioController::class, 'cambiarPrecio']);
        Route::apiResource('servicios', ServicioController::class)->except(['index', 'show']);

        // HU-07 y HU-08: Personal, especialidades y horario del salón
        Route::post('personal', [PersonalController::class, 'store']);
        Route::put('personal/{personal}', [PersonalController::class, 'update']);
        Route::delete('personal/{personal}', [PersonalController::class, 'destroy']);
        Route::put('personal/{personal}/especialidades', [EspecialidadController::class, 'asignar']);
        Route::apiResource('especialidades', EspecialidadController::class)->except(['index'])
            ->parameters(['especialidades' => 'especialidad']);
        Route::put('horarios-atencion', [HorarioAtencionController::class, 'guardar']);

        Route::delete('clientes/{cliente}', [ClienteController::class, 'destroy']);

        // HU-19 a HU-22: Ingresos, métodos de pago y descuentos
        Route::apiResource('pagos', PagoController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::apiResource('metodos-pago', MetodoPagoController::class)->except(['index']);
        Route::apiResource('descuentos', DescuentoController::class)->except(['index']);

        // Configuración: la dueña habilita o quita permisos a cada empleada
        Route::get('configuracion/permisos', [ConfiguracionController::class, 'permisos']);
        Route::put('configuracion/permisos/{user}', [ConfiguracionController::class, 'actualizarPermisos']);

        // HU-23 y HU-24: Reportes
        Route::prefix('reportes')->group(function () {
            Route::get('resumen', [ReporteController::class, 'resumen']);
            Route::get('servicios', [ReporteController::class, 'servicios']);
            Route::get('personal', [ReporteController::class, 'personal']);
            Route::get('ingresos-diarios', [ReporteController::class, 'ingresosDiarios']);
        });
    });
});
