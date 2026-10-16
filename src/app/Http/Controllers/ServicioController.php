<?php

namespace App\Http\Controllers;

use App\Models\PrecioServicio;
use App\Models\Servicio;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioController extends Controller
{
    /**
     * HU-01 / HU-10: Listado y consulta de servicios con filtros y precios vigentes
     */
    public function index(Request $request): JsonResponse
    {
        $query = Servicio::with(['categoria', 'precios']);

        // Filtro por categoría
        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->input('categoria_id'));
        }

        // Búsqueda por nombre o descripción
        if ($request->filled('buscar')) {
            $termino = $request->input('buscar');
            $query->where(function ($q) use ($termino) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%' . strtolower($termino) . '%'])
                  ->orWhereRaw('LOWER(descripcion) LIKE ?', ['%' . strtolower($termino) . '%']);
            });
        }

        // Por defecto, filtrar solo servicios activos para clientes a menos que se indique lo contrario
        if ($request->boolean('solo_activos', true)) {
            $query->where('activo', true);
        }

        $servicios = $query->orderBy('nombre')->get();

        // Mapear para adjuntar el precio vigente calculado
        $fechaConsulta = $request->input('fecha', now()->toDateString());
        $resultado = $servicios->map(function ($servicio) use ($fechaConsulta) {
            $precioVigente = $servicio->precioVigente($fechaConsulta);
            return [
                'id' => $servicio->id,
                'nombre' => $servicio->nombre,
                'descripcion' => $servicio->descripcion,
                'duracion_minutos' => $servicio->duracion_minutos,
                'activo' => $servicio->activo,
                'categoria' => $servicio->categoria ? [
                    'id' => $servicio->categoria->id,
                    'nombre' => $servicio->categoria->nombre,
                ] : null,
                'precio_vigente' => $precioVigente ? (float) $precioVigente->precio : null,
                'precio_vigente_desde' => $precioVigente ? $precioVigente->vigente_desde : null,
            ];
        });

        return response()->json([
            'data' => $resultado,
        ]);
    }

    /**
     * HU-10: Crear nuevo servicio asignando categoría y precio inicial vigente
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categoria_id' => ['required', 'integer', 'exists:categorias_servicio,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'duracion_minutos' => ['required', 'integer', 'min:5', 'max:480'],
            'precio' => ['required', 'numeric', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ], [
            'categoria_id.required' => 'La categoría es obligatoria.',
            'categoria_id.exists' => 'La categoría seleccionada no existe.',
            'nombre.required' => 'El nombre del servicio es obligatorio.',
            'duracion_minutos.required' => 'La duración en minutos es obligatoria.',
            'precio.required' => 'El precio inicial es obligatorio.',
            'precio.numeric' => 'El precio debe ser un número válido.',
        ]);

        $servicio = DB::transaction(function () use ($validated) {
            $nuevoServicio = Servicio::create([
                'categoria_id' => $validated['categoria_id'],
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'duracion_minutos' => $validated['duracion_minutos'],
                'activo' => $validated['activo'] ?? true,
            ]);

            // Asignar precio inicial vigente desde hoy
            PrecioServicio::create([
                'servicio_id' => $nuevoServicio->id,
                'precio' => $validated['precio'],
                'vigente_desde' => now()->toDateString(),
                'vigente_hasta' => null,
            ]);

            return $nuevoServicio->load(['categoria', 'precios']);
        });

        return response()->json([
            'message' => 'Servicio creado exitosamente con su precio inicial.',
            'data' => [
                'id' => $servicio->id,
                'nombre' => $servicio->nombre,
                'descripcion' => $servicio->descripcion,
                'duracion_minutos' => $servicio->duracion_minutos,
                'activo' => $servicio->activo,
                'categoria' => $servicio->categoria,
                'precio_actual' => (float) $validated['precio'],
            ],
        ], 201);
    }

    /**
     * HU-01 / HU-10: Ver detalle de un servicio
     */
    public function show(Servicio $servicio): JsonResponse
    {
        $precio = $servicio->precioVigente();

        return response()->json([
            'data' => [
                'id' => $servicio->id,
                'nombre' => $servicio->nombre,
                'descripcion' => $servicio->descripcion,
                'duracion_minutos' => $servicio->duracion_minutos,
                'activo' => $servicio->activo,
                'categoria' => $servicio->categoria,
                'precio_actual' => $precio ? (float) $precio->precio : null,
                'historial_precios' => $servicio->precios()->orderByDesc('vigente_desde')->get(),
            ],
        ]);
    }

    /**
     * HU-10: Actualizar datos de un servicio
     */
    public function update(Request $request, Servicio $servicio): JsonResponse
    {
        $validated = $request->validate([
            'categoria_id' => ['sometimes', 'integer', 'exists:categorias_servicio,id'],
            'nombre' => ['sometimes', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'duracion_minutos' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $servicio->update($validated);

        return response()->json([
            'message' => 'Servicio actualizado exitosamente.',
            'data' => $servicio->fresh(['categoria']),
        ]);
    }

    /**
     * HU-10: Cambiar el precio de un servicio manteniendo el historial de vigencia
     */
    public function cambiarPrecio(Request $request, Servicio $servicio): JsonResponse
    {
        $validated = $request->validate([
            'precio' => ['required', 'numeric', 'min:0'],
            'vigente_desde' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'precio.required' => 'El nuevo precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser numérico.',
            'vigente_desde.after_or_equal' => 'El nuevo precio no puede regir desde una fecha pasada.',
        ]);

        $fechaDesde = Carbon::parse($validated['vigente_desde'] ?? now())->toDateString();

        DB::transaction(function () use ($servicio, $validated, $fechaDesde) {
            $precioActual = $servicio->precioVigente($fechaDesde);

            // Un segundo cambio que rige desde el mismo día corrige ese precio en vez de
            // cerrar uno de un día y dejar un rango invertido (desde D, hasta D-1)
            if ($precioActual && Carbon::parse($precioActual->vigente_desde)->toDateString() === $fechaDesde) {
                $precioActual->update(['precio' => $validated['precio']]);
                return;
            }

            // Cerrar el precio vigente hasta el día anterior al cambio
            if ($precioActual) {
                $precioActual->update([
                    'vigente_hasta' => Carbon::parse($fechaDesde)->subDay()->toDateString(),
                ]);
            }

            // Si ya hay un precio programado más adelante, el nuevo rige solo hasta el día previo a ese
            $siguiente = $servicio->precios()
                ->where('vigente_desde', '>', $fechaDesde)
                ->orderBy('vigente_desde')
                ->first();

            PrecioServicio::create([
                'servicio_id' => $servicio->id,
                'precio' => $validated['precio'],
                'vigente_desde' => $fechaDesde,
                'vigente_hasta' => $siguiente
                    ? Carbon::parse($siguiente->vigente_desde)->subDay()->toDateString()
                    : null,
            ]);
        });

        return response()->json([
            'message' => 'Precio del servicio actualizado exitosamente manteniendo el historial.',
            'data' => [
                'servicio_id' => $servicio->id,
                'nuevo_precio' => (float) $validated['precio'],
                'vigente_desde' => $fechaDesde,
            ],
        ]);
    }

    /**
     * HU-10: Eliminación de un servicio que nunca se usó (por ejemplo, uno cargado por error).
     * Un servicio con reservas no se borra porque es parte del historial: se desactiva con
     * PUT { activo: false } y deja de ofrecerse sin perder sus precios ni sus atenciones.
     */
    public function destroy(Servicio $servicio): JsonResponse
    {
        if ($servicio->detalles()->exists()) {
            throw ValidationException::withMessages([
                'servicio' => ["El servicio '{$servicio->nombre}' ya se usó en reservas y no se puede eliminar. Desactívalo para que deje de ofrecerse."],
            ]);
        }

        $servicio->delete();

        return response()->json([
            'message' => "El servicio '{$servicio->nombre}' ha sido eliminado.",
        ]);
    }
}
