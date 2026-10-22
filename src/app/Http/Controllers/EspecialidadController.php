<?php

namespace App\Http\Controllers;

use App\Models\Especialidad;
use App\Models\Personal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * HU-07: Especialidades del personal (qué sabe hacer cada profesional del salón).
 * La consulta es de cualquier rol; el alta, la edición y la asignación, solo de la administración.
 */
class EspecialidadController extends Controller
{
    protected const MENSAJES = [
        'nombre.required' => 'El nombre de la especialidad es obligatorio.',
        'nombre.max' => 'El nombre no puede superar los 255 caracteres.',
        'nombre.unique' => 'Ya existe una especialidad con ese nombre.',
        'descripcion.max' => 'La descripción no puede superar los 1000 caracteres.',
    ];

    public function index(): JsonResponse
    {
        $especialidades = Especialidad::query()
            ->withCount('personal')
            ->with(['personal' => fn ($q) => $q->select('personal.id', 'nombre', 'apellido', 'activo')->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => $especialidades->map(fn (Especialidad $especialidad) => $this->formatear($especialidad))->values(),
        ]);
    }

    /**
     * La ruta fija el parámetro como {especialidad} (Laravel singularizaría en inglés a
     * "especialidade"); el enlace implícito del modelo va por ese nombre.
     */
    public function show(Especialidad $especialidad): JsonResponse
    {
        return response()->json([
            'data' => $this->formatear($this->cargar($especialidad)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validar($request);

        $especialidad = Especialidad::create($validated);

        return response()->json([
            'message' => 'Especialidad registrada exitosamente.',
            'data' => $this->formatear($this->cargar($especialidad)),
        ], 201);
    }

    public function update(Request $request, Especialidad $especialidad): JsonResponse
    {
        $validated = $this->validar($request, $especialidad);

        $especialidad->update($validated);

        return response()->json([
            'message' => 'Especialidad actualizada exitosamente.',
            'data' => $this->formatear($this->cargar($especialidad)),
        ]);
    }

    /**
     * Al borrar una especialidad se quitan también sus asignaciones al personal.
     */
    public function destroy(Especialidad $especialidad): JsonResponse
    {
        DB::transaction(function () use ($especialidad) {
            $especialidad->personal()->detach();
            $especialidad->delete();
        });

        return response()->json([
            'message' => "La especialidad {$especialidad->nombre} fue eliminada.",
        ]);
    }

    /**
     * Reemplaza las especialidades de un profesional por la lista enviada (vacía = ninguna).
     */
    public function asignar(Request $request, Personal $personal): JsonResponse
    {
        $validated = $request->validate([
            'especialidades' => ['present', 'array'],
            'especialidades.*' => ['integer', 'distinct', 'exists:especialidades,id'],
        ], [
            'especialidades.present' => 'Debe enviar la lista de especialidades (puede estar vacía).',
            'especialidades.array' => 'Las especialidades deben enviarse como una lista.',
            'especialidades.*.integer' => 'Cada especialidad debe indicarse por su identificador.',
            'especialidades.*.distinct' => 'La lista de especialidades tiene elementos repetidos.',
            'especialidades.*.exists' => 'Una de las especialidades seleccionadas no existe.',
        ]);

        $personal->especialidades()->sync($validated['especialidades']);

        return response()->json([
            'message' => 'Especialidades del profesional actualizadas exitosamente.',
            'data' => $personal->fresh('especialidades'),
        ]);
    }

    /**
     * El nombre es único sin distinguir mayúsculas: "Maquillaje" y "maquillaje" son la misma especialidad.
     */
    protected function validar(Request $request, ?Especialidad $especialidad = null): array
    {
        if ($request->has('nombre') && is_string($request->input('nombre'))) {
            $request->merge(['nombre' => trim($request->input('nombre'))]);
        }

        $regla = $especialidad ? ['sometimes', 'required'] : ['required'];

        return $request->validate([
            'nombre' => [
                'bail', ...$regla, 'string', 'max:255',
                Rule::unique('especialidades', 'nombre')->ignore($especialidad?->id),
                function (string $atributo, mixed $valor, \Closure $fallar) use ($especialidad) {
                    $repetida = Especialidad::whereRaw('LOWER(nombre) = ?', [mb_strtolower($valor)])
                        ->when($especialidad, fn ($q) => $q->where('id', '!=', $especialidad->id))
                        ->exists();

                    if ($repetida) {
                        $fallar('Ya existe una especialidad con ese nombre.');
                    }
                },
            ],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ], self::MENSAJES);
    }

    protected function cargar(Especialidad $especialidad): Especialidad
    {
        return $especialidad
            ->loadCount('personal')
            ->load(['personal' => fn ($q) => $q->select('personal.id', 'nombre', 'apellido', 'activo')->orderBy('nombre')]);
    }

    protected function formatear(Especialidad $especialidad): array
    {
        return [
            'id' => $especialidad->id,
            'nombre' => $especialidad->nombre,
            'descripcion' => $especialidad->descripcion,
            'personal_count' => (int) $especialidad->personal_count,
            'personal' => $especialidad->personal
                ->map(fn (Personal $persona) => $persona->only(['id', 'nombre', 'apellido', 'activo']))
                ->values(),
            'created_at' => $especialidad->created_at,
            'updated_at' => $especialidad->updated_at,
        ];
    }
}
