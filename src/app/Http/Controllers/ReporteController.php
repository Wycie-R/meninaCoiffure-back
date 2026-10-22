<?php

namespace App\Http\Controllers;

use App\Services\ReporteService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * HU-23 y HU-24: Reportes de gestión para la propietaria (solo admin).
 * Todos aceptan fecha_desde y fecha_hasta; por defecto, del primer día del mes a hoy.
 */
class ReporteController extends Controller
{
    /** Un período más largo haría pesada la consulta y no aporta a la gestión diaria. */
    public const DIAS_MAXIMOS = 366;

    public function __construct(
        protected ReporteService $reportes
    ) {}

    public function resumen(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->periodo($request);

        return response()->json(['data' => $this->reportes->resumen($desde, $hasta)]);
    }

    public function servicios(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->periodo($request);

        return response()->json(['data' => $this->reportes->servicios($desde, $hasta)]);
    }

    public function personal(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->periodo($request);

        return response()->json(['data' => $this->reportes->personal($desde, $hasta)]);
    }

    public function ingresosDiarios(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->periodo($request);

        return response()->json(['data' => $this->reportes->ingresosDiarios($desde, $hasta)]);
    }

    /**
     * Valida y completa el período del reporte.
     *
     * @return array{0: string, 1: string} [desde, hasta] en formato YYYY-MM-DD
     */
    protected function periodo(Request $request): array
    {
        $datos = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
        ], [
            'fecha_desde.date' => 'La fecha inicial no es una fecha válida.',
            'fecha_hasta.date' => 'La fecha final no es una fecha válida.',
        ]);

        // Se trabaja con fechas puras en UTC: en America/Asuncion el cambio de horario ocurre a
        // medianoche y la diferencia en días saldría fraccionaria
        $hasta = Carbon::parse($datos['fecha_hasta'] ?? now()->toDateString(), 'UTC')->startOfDay();
        $desde = isset($datos['fecha_desde']) ? Carbon::parse($datos['fecha_desde'], 'UTC')->startOfDay() : $hasta->copy()->startOfMonth();

        if ($hasta->lt($desde)) {
            throw ValidationException::withMessages([
                'fecha_hasta' => ['La fecha final no puede ser anterior a la inicial.'],
            ]);
        }

        if ($desde->diffInDays($hasta) + 1 > self::DIAS_MAXIMOS) {
            throw ValidationException::withMessages([
                'fecha_hasta' => ['El período del reporte no puede superar los ' . self::DIAS_MAXIMOS . ' días.'],
            ]);
        }

        return [$desde->toDateString(), $hasta->toDateString()];
    }
}
