<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaServicioController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConsumoRecursoController;
use App\Http\Controllers\DescuentoController;
use App\Http\Controllers\EspecialidadController;
use App\Http\Controllers\HistorialVisitaController;
use App\Http\Controllers\HorarioAtencionController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\MovimientoStockController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\PreferenciaClienteController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\ServicioRecursoController;
use App\Http\Controllers\StockRecursoController;
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
    // Consulta del equipo, especialidades, horario del local e insumos
    Route::get('personal', [PersonalController::class, 'index']);
    Route::get('personal/{personal}', [PersonalController::class, 'show']);
    Route::get('especialidades', [EspecialidadController::class, 'index']);
    Route::get('horarios-atencion', [HorarioAtencionController::class, 'index']);
    Route::get('recursos', [StockRecursoController::class, 'index']);
    Route::get('servicios/{servicio}/recursos', [ServicioRecursoController::class, 'index']);
    Route::get('metodos-pago', [MetodoPagoController::class, 'index']);
    Route::get('descuentos', [DescuentoController::class, 'index']);

    // Clientes y expediente (HU-05 y HU-13)
    Route::apiResource('clientes', ClienteController::class)->except(['destroy']);
    Route::get('clientes/{cliente}/preferencias', [PreferenciaClienteController::class, 'index']);
    Route::post('clientes/{cliente}/preferencias', [PreferenciaClienteController::class, 'store']);
    Route::put('preferencias/{preferencia}', [PreferenciaClienteController::class, 'update']);
    Route::delete('preferencias/{preferencia}', [PreferenciaClienteController::class, 'destroy']);
    Route::get('clientes/{cliente}/historial', [HistorialVisitaController::class, 'index']);
    Route::put('historial-visitas/{historial}', [HistorialVisitaController::class, 'update']);

    // Reservas y disponibilidad (HU-02, HU-03, HU-04, HU-06, HU-12 y HU-14)
    Route::get('disponibilidad', [ReservaController::class, 'disponibilidad']);
    Route::post('reservas/{reserva}/servicios', [ReservaController::class, 'agregarServicio']);
    Route::apiResource('reservas', ReservaController::class);

    // Consumo real de insumos por atención (HU-18)
    Route::get('reservas/{reserva}/consumos', [ConsumoRecursoController::class, 'index']);
    Route::put('detalles-reserva/{detalle}/consumos', [ConsumoRecursoController::class, 'update']);

    // Solo administración
    Route::middleware('rol:admin')->group(function () {
        // HU-09, HU-10 y HU-11: Catálogo y precios con historial
        Route::apiResource('categorias-servicio', CategoriaServicioController::class)->except(['index', 'show']);
        Route::post('servicios/{servicio}/cambiar-precio', [ServicioController::class, 'cambiarPrecio']);
        Route::apiResource('servicios', ServicioController::class)->except(['index', 'show']);

        // HU-07 y HU-08: Personal, turnos, especialidades y horario del local
        Route::post('personal', [PersonalController::class, 'store']);
        Route::put('personal/{personal}', [PersonalController::class, 'update']);
        Route::delete('personal/{personal}', [PersonalController::class, 'destroy']);
        Route::post('personal/{personal}/turnos', [PersonalController::class, 'guardarTurnos']);
        Route::put('personal/{personal}/especialidades', [EspecialidadController::class, 'asignar']);
        Route::apiResource('especialidades', EspecialidadController::class)->except(['index'])
            ->parameters(['especialidades' => 'especialidad']);
        Route::put('horarios-atencion', [HorarioAtencionController::class, 'guardar']);

        Route::delete('clientes/{cliente}', [ClienteController::class, 'destroy']);

        // HU-19 a HU-22: Ingresos, métodos de pago y descuentos
        Route::apiResource('pagos', PagoController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::apiResource('metodos-pago', MetodoPagoController::class)->except(['index']);
        Route::apiResource('descuentos', DescuentoController::class)->except(['index']);

        // HU-15 a HU-17: Inventario de insumos
        Route::apiResource('recursos', StockRecursoController::class)->except(['index']);
        Route::get('movimientos-stock', [MovimientoStockController::class, 'index']);
        Route::post('movimientos-stock', [MovimientoStockController::class, 'store']);
        Route::put('servicios/{servicio}/recursos', [ServicioRecursoController::class, 'update']);

        // HU-23 y HU-24: Reportes
        Route::prefix('reportes')->group(function () {
            Route::get('resumen', [ReporteController::class, 'resumen']);
            Route::get('servicios', [ReporteController::class, 'servicios']);
            Route::get('personal', [ReporteController::class, 'personal']);
            Route::get('ingresos-diarios', [ReporteController::class, 'ingresosDiarios']);
            Route::get('stock', [ReporteController::class, 'stock']);
        });
    });
});
