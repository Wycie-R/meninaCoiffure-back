<?php

namespace App\Http\Controllers;

use App\Models\HorarioAtencion;
use App\Models\Reserva;
use App\Services\ReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HU-08: Horario de atención del salón. Toda cita debe caber en él (Regla 2 en ReservaService),
 * salvo las marcadas como fuera de horario. El salón siempre tiene al menos una franja.
 */
class HorarioAtencionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->horarios(),
        ]);
    }

    /**
     * Reemplaza todo el horario del salón por la lista enviada (al menos una franja).
     * Informa las reservas por atender que quedan fuera del horario nuevo, sin bloquear
     * el guardado: la dueña decide si las reprograma o las marca como fuera de horario.
     */
    public function guardar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'horarios' => ['required', 'array', 'min:1'],
            'horarios.*.dia_semana' => ['required', 'integer', 'between:1,7'],
            'horarios.*.hora_apertura' => ['required', 'date_format:H:i,H:i:s'],
            'horarios.*.hora_cierre' => ['required', 'date_format:H:i,H:i:s'],
        ], [
            'horarios.required' => 'El salón debe tener al menos un día de atención.',
            'horarios.min' => 'El salón debe tener al menos un día de atención.',
            'horarios.array' => 'Los horarios deben enviarse como una lista.',
            'horarios.*.dia_semana.required' => 'El día de la semana es obligatorio.',
            'horarios.*.dia_semana.between' => 'El día de la semana debe ser entre 1 (Lunes) y 7 (Domingo).',
            'horarios.*.hora_apertura.required' => 'La hora de apertura es obligatoria.',
            'horarios.*.hora_apertura.date_format' => 'La hora de apertura debe tener el formato HH:MM.',
            'horarios.*.hora_cierre.required' => 'La hora de cierre es obligatoria.',
            'horarios.*.hora_cierre.date_format' => 'La hora de cierre debe tener el formato HH:MM.',
        ]);

        $horarios = array_map(fn (array $franja) => [
            'dia_semana' => (int) $franja['dia_semana'],
            'hora_apertura' => $this->normalizarHora($franja['hora_apertura']),
            'hora_cierre' => $this->normalizarHora($franja['hora_cierre']),
        ], $validated['horarios']);

        $this->validarFranjas($horarios);

        DB::transaction(function () use ($horarios) {
            HorarioAtencion::query()->delete();

            foreach ($horarios as $franja) {
                HorarioAtencion::create($franja);
            }
        });

        return response()->json([
            'message' => 'Horario de atención del local actualizado exitosamente.',
            'data' => $this->horarios(),
            'reservas_fuera_de_horario' => $this->reservasFueraDeHorario(),
        ]);
    }

    protected function horarios(): Collection
    {
        return HorarioAtencion::query()
            ->orderBy('dia_semana')
            ->orderBy('hora_apertura')
            ->get(['id', 'dia_semana', 'hora_apertura', 'hora_cierre']);
    }

    /**
     * El cierre va después de la apertura y las franjas del mismo día no se pisan. Las contiguas (08:00-12:00 y 12:00-17:00) son válidas.
     */
    protected function validarFranjas(array $horarios): void
    {
        $porDia = [];

        foreach ($horarios as $indice => $franja) {
            $apertura = substr($franja['hora_apertura'], 0, 5);
            $cierre = substr($franja['hora_cierre'], 0, 5);

            if ($cierre <= $apertura) {
                throw ValidationException::withMessages([
                    "horarios.{$indice}.hora_cierre" => ['La hora de cierre debe ser posterior a la hora de apertura.'],
                ]);
            }

            $porDia[$franja['dia_semana']][] = [$apertura, $cierre];
        }

        foreach ($porDia as $franjas) {
            usort($franjas, fn ($a, $b) => strcmp($a[0], $b[0]));

            for ($i = 1; $i < count($franjas); $i++) {
                if ($franjas[$i][0] < $franjas[$i - 1][1]) {
                    throw ValidationException::withMessages([
                        'horarios' => ["Las franjas {$franjas[$i - 1][0]}–{$franjas[$i - 1][1]} y {$franjas[$i][0]}–{$franjas[$i][1]} del mismo día se superponen."],
                    ]);
                }
            }
        }
    }

    /**
     * Reservas por atender (pendientes o confirmadas, desde hoy) que no caben en el horario
     * guardado y no están marcadas como fuera de horario.
     */
    protected function reservasFueraDeHorario(): array
    {
        $reservaService = app(ReservaService::class);
        $hoy = now()->toDateString();

        $reservasPorAtender = Reserva::query()
            ->with('personal:id,nombre,apellido')
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->where('fuera_de_horario', false)
            ->where('fecha', '>=', $hoy)
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        $fueraDeHorario = [];
        foreach ($reservasPorAtender as $reserva) {
            $fecha = substr((string) $reserva->fecha, 0, 10);
            $franjasDelSalon = $reservaService->franjasLocal($fecha);
            $inicio = $this->normalizarHora($reserva->hora_inicio);
            $fin = $this->normalizarHora($reserva->hora_fin);

            $cabe = false;
            foreach ($franjasDelSalon as [$apertura, $cierre]) {
                if ($apertura <= $inicio && $cierre >= $fin) {
                    $cabe = true;
                }
            }

            if (!$cabe) {
                $fueraDeHorario[] = [
                    'id' => $reserva->id,
                    'fecha' => $fecha,
                    'hora_inicio' => $inicio,
                    'hora_fin' => $fin,
                    'personal' => $reserva->personal,
                ];
            }
        }

        return $fueraDeHorario;
    }

    /**
     * Las horas se guardan y comparan como HH:MM:SS.
     */
    protected function normalizarHora(string $hora): string
    {
        $hora = trim($hora);

        return preg_match('/^\d{2}:\d{2}$/', $hora) ? "{$hora}:00" : $hora;
    }
}
