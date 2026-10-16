<?php

namespace App\Http\Controllers;

use App\Models\Personal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonalController extends Controller
{
    protected const REGLAS_TURNOS = [
        'turnos.*.dia_semana' => ['required', 'integer', 'between:1,7'],
        'turnos.*.hora_inicio' => ['required', 'date_format:H:i,H:i:s'],
        'turnos.*.hora_fin' => ['required', 'date_format:H:i,H:i:s'],
    ];

    protected const MENSAJES_TURNOS = [
        'turnos.array' => 'Los turnos deben enviarse como una lista.',
        'turnos.*.dia_semana.between' => 'El día de la semana debe ser entre 1 (Lunes) y 7 (Domingo).',
        'turnos.*.hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM.',
        'turnos.*.hora_fin.date_format' => 'La hora de fin debe tener el formato HH:MM.',
    ];

    /**
     * HU-07: Listar el personal con sus turnos de trabajo
     */
    public function index(Request $request): JsonResponse
    {
        $query = Personal::with(['turnos', 'especialidades']);

        if ($request->boolean('solo_activos', false)) {
            $query->where('activo', true);
        }

        $personal = $query->orderBy('nombre')->get();

        return response()->json([
            'data' => $personal,
        ]);
    }

    /**
     * HU-07: Alta de personal
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizarEmail($request);

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('personal', 'email')],
            'fecha_ingreso' => ['nullable', 'date'],
            'activo' => ['nullable', 'boolean'],
            'turnos' => ['nullable', 'array'],
            ...self::REGLAS_TURNOS,
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'email.unique' => 'Ya hay una persona del equipo registrada con ese correo.',
            ...self::MENSAJES_TURNOS,
        ]);

        $turnos = $validated['turnos'] ?? [];
        $this->validarFranjas($turnos);

        $personal = DB::transaction(function () use ($validated, $turnos) {
            $nuevoPersonal = Personal::create([
                'nombre' => $validated['nombre'],
                'apellido' => $validated['apellido'],
                'telefono' => $validated['telefono'] ?? null,
                'email' => $validated['email'] ?? null,
                'fecha_ingreso' => $validated['fecha_ingreso'] ?? now()->toDateString(),
                'activo' => $validated['activo'] ?? true,
            ]);

            foreach ($turnos as $turno) {
                $nuevoPersonal->turnos()->create([
                    'dia_semana' => $turno['dia_semana'],
                    'hora_inicio' => $turno['hora_inicio'],
                    'hora_fin' => $turno['hora_fin'],
                ]);
            }

            return $nuevoPersonal->load('turnos');
        });

        return response()->json([
            'message' => 'Personal registrado exitosamente.',
            'data' => $personal,
        ], 201);
    }

    /**
     * HU-07: Ver datos y turnos de un profesional
     */
    public function show(Personal $personal): JsonResponse
    {
        return response()->json([
            'data' => $personal->load(['turnos', 'especialidades']),
        ]);
    }

    /**
     * HU-07: Modificar datos de personal
     */
    public function update(Request $request, Personal $personal): JsonResponse
    {
        $this->normalizarEmail($request);

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'apellido' => ['sometimes', 'required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('personal', 'email')->ignore($personal->id)],
            'fecha_ingreso' => ['nullable', 'date'],
            'activo' => ['sometimes', 'boolean'],
        ], [
            'email.unique' => 'Ya hay una persona del equipo registrada con ese correo.',
        ]);

        if (array_key_exists('activo', $validated) && !$validated['activo'] && $personal->activo) {
            $this->validarSinReservasFuturas($personal);
        }

        $personal->update($validated);

        return response()->json([
            'message' => 'Datos de personal actualizados exitosamente.',
            'data' => $personal->fresh('turnos'),
        ]);
    }

    /**
     * HU-07: Inactivación lógica del empleado
     */
    public function destroy(Personal $personal): JsonResponse
    {
        $this->validarSinReservasFuturas($personal);

        $personal->update(['activo' => false]);

        return response()->json([
            'message' => "El profesional {$personal->nombre} {$personal->apellido} ha sido desactivado.",
        ]);
    }

    /**
     * HU-08: Asignar o actualizar los turnos de trabajo del profesional.
     * Una lista vacía deja al profesional sin turnos (no recibe reservas).
     */
    public function guardarTurnos(Request $request, Personal $personal): JsonResponse
    {
        $validated = $request->validate([
            'turnos' => ['present', 'array'],
            ...self::REGLAS_TURNOS,
        ], [
            'turnos.present' => 'Debe enviar la lista de turnos (puede estar vacía).',
            ...self::MENSAJES_TURNOS,
        ]);

        $this->validarFranjas($validated['turnos']);

        DB::transaction(function () use ($personal, $validated) {
            // Reemplazar turnos anteriores
            $personal->turnos()->delete();

            foreach ($validated['turnos'] as $turno) {
                $personal->turnos()->create([
                    'dia_semana' => $turno['dia_semana'],
                    'hora_inicio' => $turno['hora_inicio'],
                    'hora_fin' => $turno['hora_fin'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Turnos de trabajo actualizados exitosamente.',
            'data' => $personal->fresh('turnos'),
        ]);
    }

    /**
     * Cada franja debe terminar después de empezar y no pisarse con otra del mismo día.
     * Las franjas contiguas (09:00-12:00 y 12:00-14:00) son válidas.
     */
    protected function validarFranjas(array $turnos): void
    {
        $porDia = [];

        foreach ($turnos as $indice => $turno) {
            $inicio = substr($turno['hora_inicio'], 0, 5);
            $fin = substr($turno['hora_fin'], 0, 5);

            if ($fin <= $inicio) {
                throw ValidationException::withMessages([
                    "turnos.{$indice}.hora_fin" => ['La hora de fin del turno debe ser posterior a la hora de inicio.'],
                ]);
            }

            $porDia[$turno['dia_semana']][] = [$inicio, $fin];
        }

        foreach ($porDia as $franjas) {
            usort($franjas, fn ($a, $b) => strcmp($a[0], $b[0]));

            for ($i = 1; $i < count($franjas); $i++) {
                if ($franjas[$i][0] < $franjas[$i - 1][1]) {
                    throw ValidationException::withMessages([
                        'turnos' => ["Las franjas {$franjas[$i - 1][0]}–{$franjas[$i - 1][1]} y {$franjas[$i][0]}–{$franjas[$i][1]} del mismo día se superponen."],
                    ]);
                }
            }
        }
    }

    /**
     * El correo identifica a la persona (índice único): se guarda en minúsculas para que
     * "Carla@..." y "carla@..." no creen dos registros de la misma profesional.
     */
    protected function normalizarEmail(Request $request): void
    {
        if ($request->filled('email')) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
    }

    /**
     * No se desactiva a quien tiene citas por atender: primero hay que reasignarlas o cancelarlas.
     */
    protected function validarSinReservasFuturas(Personal $personal): void
    {
        $ahora = now();

        $pendientes = $personal->reservas()
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->where(function ($q) use ($ahora) {
                $q->where('fecha', '>', $ahora->toDateString())
                  ->orWhere(function ($q) use ($ahora) {
                      $q->where('fecha', $ahora->toDateString())
                        ->where('hora_inicio', '>', $ahora->format('H:i:s'));
                  });
            })
            ->count();

        if ($pendientes > 0) {
            $texto = $pendientes === 1 ? '1 reserva por atender' : "{$pendientes} reservas por atender";

            throw ValidationException::withMessages([
                'activo' => ["{$personal->nombre} tiene {$texto}. Reasígnalas o cancélalas antes de desactivar su cuenta de personal."],
            ]);
        }
    }
}
