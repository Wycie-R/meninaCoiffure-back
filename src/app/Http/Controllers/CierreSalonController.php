<?php

namespace App\Http\Controllers;

use App\Models\CierreSalon;
use App\Models\Reserva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Días puntuales en que el salón no trabaja ("este miércoles no abrimos"). No cambian el
 * horario semanal: solo ese día queda sin horarios, ni siquiera fuera de horario.
 */
class CierreSalonController extends Controller
{
    /** Cierres de hoy en adelante (públicos: la página del salón y Mis citas los muestran). */
    public function index(): JsonResponse
    {
        $cierres = CierreSalon::whereDate('fecha', '>=', now()->toDateString())
            ->orderBy('fecha')
            ->limit(60)
            ->get(['id', 'fecha', 'motivo']);

        return response()->json(['data' => $cierres]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'after_or_equal:today', Rule::unique('cierres_salon', 'fecha')],
            'motivo' => ['nullable', 'string', 'max:120'],
        ], [
            'fecha.required' => 'Elige el día que el salón no trabaja.',
            'fecha.date' => 'La fecha no es válida.',
            'fecha.after_or_equal' => 'Solo se cierran días de hoy en adelante.',
            'fecha.unique' => 'Ese día ya está marcado como cerrado.',
            'motivo.max' => 'El motivo no puede superar los 120 caracteres.',
        ]);

        $fecha = substr($datos['fecha'], 0, 10);
        $motivo = trim((string) ($datos['motivo'] ?? ''));
        if ($motivo === '') {
            $motivo = null;
        }

        $cierre = CierreSalon::create(['fecha' => $fecha, 'motivo' => $motivo]);

        // Se avisa, sin cancelar nada: la dueña decide si reprograma esas citas o las cancela
        $citasDeEseDia = Reserva::whereDate('fecha', $fecha)
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->count();

        $mensaje = 'Listo: ese día el salón no recibe citas.';
        if ($citasDeEseDia > 0) {
            $mensaje = "Listo. Ojo: ese día ya hay {$citasDeEseDia} cita(s) agendada(s); reprográmalas o avisa a las clientas.";
        }

        return response()->json([
            'message' => $mensaje,
            'data' => $cierre,
            'citas_afectadas' => $citasDeEseDia,
        ], 201);
    }

    public function destroy(CierreSalon $cierre): JsonResponse
    {
        $cierre->delete();

        return response()->json(['message' => 'El salón vuelve a atender ese día con su horario de siempre.']);
    }
}
