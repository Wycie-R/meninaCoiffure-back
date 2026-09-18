<?php

namespace App\Http\Controllers;

use App\Models\Personal;
use App\Models\TurnoPersonal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PersonalController extends Controller
{
    /**
     * HU-07: Listar el personal con sus turnos de trabajo
     */
    public function index(Request $request): JsonResponse
    {
        $query = Personal::with('turnos');

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
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'fecha_ingreso' => ['nullable', 'date'],
            'activo' => ['nullable', 'boolean'],
            'turnos' => ['nullable', 'array'],
            'turnos.*.dia_semana' => ['required_with:turnos', 'integer', 'between:1,7'],
            'turnos.*.hora_inicio' => ['required_with:turnos', 'date_format:H:i,H:i:s'],
            'turnos.*.hora_fin' => ['required_with:turnos', 'date_format:H:i,H:i:s'],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
        ]);

        $personal = DB::transaction(function () use ($validated) {
            $nuevoPersonal = Personal::create([
                'nombre' => $validated['nombre'],
                'apellido' => $validated['apellido'],
                'telefono' => $validated['telefono'] ?? null,
                'email' => $validated['email'] ?? null,
                'fecha_ingreso' => $validated['fecha_ingreso'] ?? now()->toDateString(),
                'activo' => $validated['activo'] ?? true,
            ]);

            if (!empty($validated['turnos'])) {
                foreach ($validated['turnos'] as $turno) {
                    $nuevoPersonal->turnos()->create([
                        'dia_semana' => $turno['dia_semana'],
                        'hora_inicio' => $turno['hora_inicio'],
                        'hora_fin' => $turno['hora_fin'],
                    ]);
                }
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
            'data' => $personal->load('turnos'),
        ]);
    }

    /**
     * HU-07: Modificar datos de personal
     */
    public function update(Request $request, Personal $personal): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'apellido' => ['sometimes', 'required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'fecha_ingreso' => ['nullable', 'date'],
            'activo' => ['sometimes', 'boolean'],
        ]);

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
        $personal->update(['activo' => false]);

        return response()->json([
            'message' => "El profesional {$personal->nombre} {$personal->apellido} ha sido desactivado.",
        ]);
    }

    /**
     * HU-07: Asignar o actualizar los turnos de trabajo del profesional
     */
    public function guardarTurnos(Request $request, Personal $personal): JsonResponse
    {
        $validated = $request->validate([
            'turnos' => ['required', 'array'],
            'turnos.*.dia_semana' => ['required', 'integer', 'between:1,7'],
            'turnos.*.hora_inicio' => ['required', 'date_format:H:i,H:i:s'],
            'turnos.*.hora_fin' => ['required', 'date_format:H:i,H:i:s'],
        ], [
            'turnos.required' => 'Debe proporcionar al menos un turno de trabajo.',
            'turnos.*.dia_semana.between' => 'El día de la semana debe ser entre 1 (Lunes) y 7 (Domingo).',
        ]);

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
}
