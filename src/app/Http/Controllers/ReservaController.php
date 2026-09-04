<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservaRequest;
use App\Http\Requests\UpdateReservaRequest;
use App\Models\Reserva;
use App\Services\ReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ReservaController extends Controller
{
    public function __construct(
        protected ReservaService $reservaService
    ) {}

    /**
     * Listado con paginación y carga ansiosa de relaciones.
     */
    #[OA\Get(
        path: '/api/reservas',
        summary: 'Listado paginado de reservas',
        description: 'Retorna un listado paginado con carga ansiosa de cliente, personal y detalles de servicios asociados.',
        tags: ['Reservas']
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'Número de página',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'per_page',
        in: 'query',
        description: 'Cantidad de registros por página',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 15)
    )]
    #[OA\Response(
        response: 200,
        description: 'Listado de reservas obtenido exitosamente'
    )]
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
    #[OA\Post(
        path: '/api/reservas',
        summary: 'Crear una nueva reserva',
        description: 'Registra una reserva con sus detalles de servicios. Aplica validaciones: no solapamiento del personal, horario dentro del turno laboral, servicios activos y congelamiento de precios vigentes.',
        tags: ['Reservas']
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Datos para registrar la reserva',
        content: new OA\JsonContent(
            required: ['cliente_id', 'personal_id', 'fecha', 'hora_inicio', 'hora_fin', 'servicios'],
            properties: [
                new OA\Property(property: 'cliente_id', type: 'integer', example: 1, description: 'ID del cliente'),
                new OA\Property(property: 'personal_id', type: 'integer', example: 1, description: 'ID del personal/estilista'),
                new OA\Property(property: 'fecha', type: 'string', format: 'date', example: '2026-09-07', description: 'Fecha de la reserva (YYYY-MM-DD)'),
                new OA\Property(property: 'hora_inicio', type: 'string', example: '10:00', description: 'Hora de inicio (HH:MM o HH:MM:SS)'),
                new OA\Property(property: 'hora_fin', type: 'string', example: '11:00', description: 'Hora de finalización (HH:MM o HH:MM:SS)'),
                new OA\Property(
                    property: 'servicios',
                    type: 'array',
                    description: 'Lista de IDs de servicios',
                    items: new OA\Items(type: 'integer', example: 1)
                ),
                new OA\Property(property: 'descuento_id', type: 'integer', nullable: true, example: null, description: 'ID del descuento opcional'),
                new OA\Property(property: 'estado', type: 'string', enum: ['pendiente', 'confirmada', 'cancelada', 'completada'], example: 'pendiente')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Reserva creada exitosamente con precios congelados'
    )]
    #[OA\Response(
        response: 422,
        description: 'Error de validación (solapamiento horario, fuera de turno o servicio inactivo)'
    )]
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
    #[OA\Get(
        path: '/api/reservas/{reserva}',
        summary: 'Ver detalle de una reserva',
        description: 'Obtiene la información completa de una reserva, su cliente, personal y servicios detallados.',
        tags: ['Reservas']
    )]
    #[OA\Parameter(
        name: 'reserva',
        in: 'path',
        required: true,
        description: 'ID de la reserva a consultar',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Detalle de la reserva obtenido exitosamente'
    )]
    #[OA\Response(
        response: 404,
        description: 'Reserva no encontrada'
    )]
    public function show(Reserva $reserva): JsonResponse
    {
        return response()->json([
            'data' => $reserva->load(['cliente', 'personal', 'detalles.servicio']),
        ]);
    }

    /**
     * Actualizar una reserva existente.
     */
    #[OA\Put(
        path: '/api/reservas/{reserva}',
        summary: 'Actualizar una reserva existente',
        description: 'Actualiza campos de la reserva. Si se modifican horarios o profesional, re-valida turnos y solapamientos. Regla 5: Bloquea modificaciones si la reserva ya está completada.',
        tags: ['Reservas']
    )]
    #[OA\Parameter(
        name: 'reserva',
        in: 'path',
        required: true,
        description: 'ID de la reserva a actualizar',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Datos a actualizar en la reserva',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'cliente_id', type: 'integer', example: 1),
                new OA\Property(property: 'personal_id', type: 'integer', example: 1),
                new OA\Property(property: 'fecha', type: 'string', format: 'date', example: '2026-09-07'),
                new OA\Property(property: 'hora_inicio', type: 'string', example: '10:30'),
                new OA\Property(property: 'hora_fin', type: 'string', example: '11:30'),
                new OA\Property(property: 'estado', type: 'string', enum: ['pendiente', 'confirmada', 'cancelada', 'completada'], example: 'confirmada'),
                new OA\Property(property: 'descuento_id', type: 'integer', nullable: true, example: null)
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Reserva actualizada exitosamente'
    )]
    #[OA\Response(
        response: 422,
        description: 'Error de validación o reserva en estado completada'
    )]
    #[OA\Response(
        response: 404,
        description: 'Reserva no encontrada'
    )]
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
    #[OA\Delete(
        path: '/api/reservas/{reserva}',
        summary: 'Cancelar una reserva (baja lógica)',
        description: 'Modifica el estado de la reserva a cancelada. Regla 5: Bloquea la cancelación si la reserva ya está completada.',
        tags: ['Reservas']
    )]
    #[OA\Parameter(
        name: 'reserva',
        in: 'path',
        required: true,
        description: 'ID de la reserva a cancelar',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Reserva cancelada exitosamente'
    )]
    #[OA\Response(
        response: 422,
        description: 'Error: No se puede cancelar una reserva completada'
    )]
    #[OA\Response(
        response: 404,
        description: 'Reserva no encontrada'
    )]
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
    #[OA\Post(
        path: '/api/reservas/{reserva}/servicios',
        summary: 'Agregar servicio en el momento a una reserva',
        description: 'Permite agregar un nuevo servicio a una reserva existente en curso, congelando el precio vigente a la fecha de la reserva y marcando agregado_en_el_momento = true.',
        tags: ['Reservas']
    )]
    #[OA\Parameter(
        name: 'reserva',
        in: 'path',
        required: true,
        description: 'ID de la reserva a la cual sumar el servicio',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Datos del servicio adicional a agregar',
        content: new OA\JsonContent(
            required: ['servicio_id'],
            properties: [
                new OA\Property(property: 'servicio_id', type: 'integer', example: 3, description: 'ID del servicio a agregar'),
                new OA\Property(property: 'cantidad', type: 'integer', example: 1, default: 1, description: 'Cantidad del servicio')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Servicio agregado a la reserva exitosamente con precio congelado'
    )]
    #[OA\Response(
        response: 422,
        description: 'Error de validación (servicio inexistente o inactivo)'
    )]
    #[OA\Response(
        response: 404,
        description: 'Reserva no encontrada'
    )]
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

