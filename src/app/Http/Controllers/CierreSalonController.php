<?php

namespace App\Http\Controllers;

use App\Models\CierreSalon;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Días en que el salón no trabaja: uno puntual ("este miércoles no abrimos") o un período
 * (vacaciones, un accidente). No cambian el horario semanal: solo esos días quedan sin
 * horarios, ni siquiera fuera de horario.
 */
class CierreSalonController extends Controller
{
    /** Un período más largo casi seguro es un error al elegir la fecha (por ejemplo, el año). */
    private const MAXIMO_DIAS = 92;

    /** Cierres que todavía no terminaron (públicos: la página del salón y Mis citas los muestran). */
    public function index(): JsonResponse
    {
        $hoy = now()->toDateString();

        // Un período que empezó antes de hoy y sigue vigente también se lista
        $cierres = CierreSalon::where(function ($consulta) use ($hoy) {
            $consulta->whereDate('fecha', '>=', $hoy)
                ->orWhereDate('fecha_hasta', '>=', $hoy);
        })
            ->orderBy('fecha')
            ->limit(60)
            ->get(['id', 'fecha', 'fecha_hasta', 'motivo']);

        return response()->json(['data' => $cierres]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha'],
            'motivo' => ['nullable', 'string', 'max:120'],
        ], [
            'fecha.required' => 'Elige el día que el salón no trabaja.',
            'fecha.date' => 'La fecha no es válida.',
            'fecha.after_or_equal' => 'Solo se cierran días de hoy en adelante.',
            'fecha_hasta.date' => 'La fecha de regreso no es válida.',
            'fecha_hasta.after_or_equal' => 'El último día no puede ser anterior al primero.',
            'motivo.max' => 'El motivo no puede superar los 120 caracteres.',
        ]);

        $desde = substr($datos['fecha'], 0, 10);
        $hasta = $desde;
        if (!empty($datos['fecha_hasta'])) {
            $hasta = substr($datos['fecha_hasta'], 0, 10);
        }

        // Carbon 3 devuelve la diferencia como decimal: se pasa a entero (desde y hasta son días enteros)
        $cantidadDeDias = (int) Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1;
        if ($cantidadDeDias > self::MAXIMO_DIAS) {
            throw ValidationException::withMessages([
                'fecha_hasta' => ['Un cierre puede durar como máximo ' . self::MAXIMO_DIAS . ' días. Revisa las fechas.'],
            ]);
        }

        $this->exigirSinCruces($desde, $hasta);

        $motivo = trim((string) ($datos['motivo'] ?? ''));
        if ($motivo === '') {
            $motivo = null;
        }

        // Un solo día se guarda sin fecha_hasta, igual que los cierres de siempre
        $fechaHasta = null;
        if ($hasta !== $desde) {
            $fechaHasta = $hasta;
        }

        $cierre = CierreSalon::create(['fecha' => $desde, 'fecha_hasta' => $fechaHasta, 'motivo' => $motivo]);

        // Se avisa, sin cancelar nada: la dueña decide si reprograma esas citas o las cancela
        $citasAfectadas = Reserva::whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->count();

        $mensaje = 'Listo: ese día el salón no recibe citas.';
        if ($fechaHasta !== null) {
            $mensaje = "Listo: esos {$cantidadDeDias} días el salón no recibe citas.";
        }
        if ($citasAfectadas > 0) {
            $mensaje = "Listo. Ojo: en esas fechas ya hay {$citasAfectadas} cita(s) agendada(s); reprográmalas o avisa a las clientas.";
        }

        return response()->json([
            'message' => $mensaje,
            'data' => $cierre,
            'citas_afectadas' => $citasAfectadas,
        ], 201);
    }

    public function destroy(CierreSalon $cierre): JsonResponse
    {
        $mensaje = 'El salón vuelve a atender ese día con su horario de siempre.';
        if ($cierre->fecha_hasta !== null) {
            $mensaje = 'El salón vuelve a atender esos días con su horario de siempre.';
        }

        $cierre->delete();

        return response()->json(['message' => $mensaje]);
    }

    /**
     * Dos cierres no pueden cubrir el mismo día: al quitar uno, el otro seguiría cerrando
     * parte de las fechas sin que se note.
     */
    private function exigirSinCruces(string $desde, string $hasta): void
    {
        // Se cruza si empieza antes de que termine el nuevo y termina después de que empiece
        $cruce = CierreSalon::whereDate('fecha', '<=', $hasta)
            ->where(function ($consulta) use ($desde) {
                $consulta->whereDate('fecha', '>=', $desde)
                    ->orWhereDate('fecha_hasta', '>=', $desde);
            })
            ->orderBy('fecha')
            ->first();

        if ($cruce === null) {
            return;
        }

        $mensaje = 'Ese día ya está marcado como cerrado.';
        if ($desde !== $hasta || $cruce->fecha_hasta !== null) {
            $mensaje = 'Esas fechas se cruzan con otro cierre que ya está cargado. Quítalo o elige otras fechas.';
        }

        throw ValidationException::withMessages(['fecha' => [$mensaje]]);
    }
}
