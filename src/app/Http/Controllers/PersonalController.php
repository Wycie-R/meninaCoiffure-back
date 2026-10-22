<?php

namespace App\Http\Controllers;

use App\Models\Personal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonalController extends Controller
{
    /**
     * HU-07: Listar el personal con sus especialidades.
     * Todas atienden bajo demanda dentro del horario del salón.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Personal::with('especialidades');

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
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'email.unique' => 'Ya hay una persona del equipo registrada con ese correo.',
        ]);

        $personal = Personal::create([
            'nombre' => $validated['nombre'],
            'apellido' => $validated['apellido'],
            'telefono' => $validated['telefono'] ?? null,
            'email' => $validated['email'] ?? null,
            'fecha_ingreso' => $validated['fecha_ingreso'] ?? now()->toDateString(),
            'activo' => $validated['activo'] ?? true,
        ]);

        return response()->json([
            'message' => 'Personal registrado exitosamente.',
            'data' => $personal,
        ], 201);
    }

    /**
     * HU-07: Ver datos y especialidades de un profesional
     */
    public function show(Personal $personal): JsonResponse
    {
        return response()->json([
            'data' => $personal->load('especialidades'),
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
            'data' => $personal->fresh('especialidades'),
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
