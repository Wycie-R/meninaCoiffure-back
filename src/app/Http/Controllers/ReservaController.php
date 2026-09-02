<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservaRequest;
use App\Http\Requests\UpdateReservaRequest;
use App\Models\Reserva;
use App\Services\ReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservaController extends Controller
{
    public function __construct(
        protected ReservaService $reservaService
    ) {}

    /**
     * Listado con paginación y carga ansiosa de relaciones.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        $reservas = Reserva::with(['cliente', 'personal', 'detalles.servicio'])
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->paginate($perPage);

        return response()->json($reservas);
    }

    /**
     * Crear una nueva reserva usando la capa de servicio.
     */
    public function store(StoreReservaRequest $request): JsonResponse
    {
        $reserva = $this->reservaService->crearReserva($request->validated());

        return response()->json([
            'message' => 'Reserva creada exitosamente.',
            'data' => $reserva,
        ], 201);
    }

    /**
     * Ver detalle de una reserva específica.
     */
    public function show(Reserva $reserva): JsonResponse
    {
        return response()->json([
            'data' => $reserva->load(['cliente', 'personal', 'detalles.servicio']),
        ]);
    }

    /**
     * Actualizar una reserva existente.
     */
    public function update(UpdateReservaRequest $request, Reserva $reserva): JsonResponse
    {
        $reservaActualizada = $this->reservaService->actualizarReserva($reserva, $request->validated());

        return response()->json([
            'message' => 'Reserva actualizada exitosamente.',
            'data' => $reservaActualizada,
        ]);
    }

    /**
     * Cancelar una reserva existente (cancelación lógica por estado).
     */
    public function destroy(Reserva $reserva): JsonResponse
    {
        $reservaCancelada = $this->reservaService->cancelarReserva($reserva);

        return response()->json([
            'message' => 'Reserva cancelada exitosamente.',
            'data' => $reservaCancelada,
        ]);
    }

    /**
     * Endpoint para sumar servicio en el momento.
     */
    public function agregarServicio(Request $request, Reserva $reserva): JsonResponse
    {
        $validated = $request->validate([
            'servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'cantidad' => ['nullable', 'integer', 'min:1'],
        ], [
            'servicio_id.required' => 'El servicio_id es obligatorio.',
            'servicio_id.exists' => 'El servicio seleccionado no existe.',
            'cantidad.integer' => 'La cantidad debe ser un número entero.',
            'cantidad.min' => 'La cantidad debe ser al menos 1.',
        ]);

        $detalle = $this->reservaService->agregarDetalle(
            $reserva,
            $validated['servicio_id'],
            $validated['cantidad'] ?? 1
        );

        return response()->json([
            'message' => 'Servicio agregado a la reserva exitosamente.',
            'data' => $detalle,
        ], 201);
    }
}
