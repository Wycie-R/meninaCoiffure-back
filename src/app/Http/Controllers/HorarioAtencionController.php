<?php

namespace App\Http\Controllers;

use App\Models\HorarioAtencion;
use App\Models\TurnoPersonal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HU-08: Horario de atención del local. Una cita en el salón debe caber en él (ReservaService);
 * sin filas, el horario del local no restringe las reservas.
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
     * Reemplaza todo el horario del local por la lista enviada (vacía = sin restricción).
     * Informa los turnos del personal activo que quedan fuera, sin bloquear el guardado:
     * esas horas aún sirven para citas a domicilio.
     */
    public function guardar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'horarios' => ['present', 'array'],
            'horarios.*.dia_semana' => ['required', 'integer', 'between:1,7'],
            'horarios.*.hora_apertura' => ['required', 'date_format:H:i,H:i:s'],
            'horarios.*.hora_cierre' => ['required', 'date_format:H:i,H:i:s'],
        ], [
            'horarios.present' => 'Debe enviar la lista de horarios (puede estar vacía).',
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
            'turnos_fuera_de_horario' => $this->turnosFueraDeHorario($horarios),
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
     * Igual que los turnos del personal: el cierre va después de la apertura y las franjas
     * del mismo día no se pisan. Las contiguas (08:00-12:00 y 12:00-17:00) son válidas.
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
     * Turnos del personal activo que no caben enteros en el horario del local de su día
     * (las franjas contiguas del local se consideran una sola).
     */
    protected function turnosFueraDeHorario(array $horarios): array
    {
        // Sin horario del local no hay restricción
        if ($horarios === []) {
            return [];
        }

        $franjasPorDia = [];
        foreach (collect($horarios)->sortBy('hora_apertura') as $franja) {
            $dia = $franja['dia_semana'];
            $ultima = isset($franjasPorDia[$dia]) ? array_key_last($franjasPorDia[$dia]) : null;

            if ($ultima !== null && $franja['hora_apertura'] <= $franjasPorDia[$dia][$ultima][1]) {
                $franjasPorDia[$dia][$ultima][1] = max($franjasPorDia[$dia][$ultima][1], $franja['hora_cierre']);
            } else {
                $franjasPorDia[$dia][] = [$franja['hora_apertura'], $franja['hora_cierre']];
            }
        }

        return TurnoPersonal::query()
            ->with('personal:id,nombre,apellido')
            ->whereHas('personal', fn ($q) => $q->where('activo', true))
            ->orderBy('personal_id')
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->get()
            ->filter(function (TurnoPersonal $turno) use ($franjasPorDia) {
                $inicio = $this->normalizarHora($turno->hora_inicio);
                $fin = $this->normalizarHora($turno->hora_fin);

                foreach ($franjasPorDia[(int) $turno->dia_semana] ?? [] as [$apertura, $cierre]) {
                    if ($apertura <= $inicio && $cierre >= $fin) {
                        return false;
                    }
                }

                return true;
            })
            ->map(fn (TurnoPersonal $turno) => [
                'personal_id' => $turno->personal_id,
                'nombre' => $turno->personal->nombre,
                'apellido' => $turno->personal->apellido,
                'dia_semana' => (int) $turno->dia_semana,
                'hora_inicio' => $this->normalizarHora($turno->hora_inicio),
                'hora_fin' => $this->normalizarHora($turno->hora_fin),
            ])
            ->values()
            ->all();
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
