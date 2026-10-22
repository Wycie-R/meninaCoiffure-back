<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservaRequest;
use App\Http\Requests\UpdateReservaRequest;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Services\ReservaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        description: 'Cantidad de registros por página (máximo 100)',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 15, maximum: 100)
    )]
    #[OA\Parameter(name: 'fecha_desde', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'fecha_hasta', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'estado', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: Reserva::ESTADOS))]
    #[OA\Parameter(name: 'personal_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'cliente_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Listado de reservas obtenido exitosamente'
    )]
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'estado' => ['nullable', 'string', Rule::in(Reserva::ESTADOS)],
            'personal_id' => ['nullable', 'integer'],
            'cliente_id' => ['nullable', 'integer'],
        ], [
            'per_page.max' => 'Se pueden pedir como máximo 100 reservas por página.',
            'fecha_hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ]);

        // HU-06: un profesional ve solo su propia agenda
        $usuario = $request->user();
        if (!$usuario->esAdmin()) {
            $filtros['personal_id'] = $usuario->personal_id ?? 0;
        }

        $reservas = Reserva::with(ReservaService::RELACIONES)
            ->when($filtros['fecha_desde'] ?? null, fn ($q, $fecha) => $q->where('fecha', '>=', $fecha))
            ->when($filtros['fecha_hasta'] ?? null, fn ($q, $fecha) => $q->where('fecha', '<=', $fecha))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['personal_id'] ?? null, fn ($q, $id) => $q->where('personal_id', $id))
            ->when($filtros['cliente_id'] ?? null, fn ($q, $id) => $q->where('cliente_id', $id))
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->paginate($filtros['per_page'] ?? 15)
            ->withQueryString();

        return response()->json($reservas);
    }

    /**
     * Crear una nueva reserva usando la capa de servicio.
     */
    #[OA\Post(
        path: '/api/reservas',
        summary: 'Crear una nueva reserva',
        description: 'Registra una reserva con sus detalles de servicios. Aplica validaciones: no solapamiento del personal, horario dentro del horario del salón (o fuera de horario autorizado), servicios activos y congelamiento de precios vigentes.',
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
                new OA\Property(property: 'estado', type: 'string', enum: ['pendiente', 'confirmada'], example: 'pendiente'),
                new OA\Property(property: 'a_domicilio', type: 'boolean', example: false, description: 'true si la cita se atiende fuera del salón'),
                new OA\Property(property: 'direccion_domicilio', type: 'string', nullable: true, example: null, description: 'Obligatoria si a_domicilio es true'),
                new OA\Property(property: 'fuera_de_horario', type: 'boolean', example: false, description: 'true si la dueña autoriza atender fuera del horario del salón (dentro de 06:00–22:00)')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Reserva creada exitosamente con precios congelados'
    )]
    #[OA\Response(
        response: 422,
        description: 'Error de validación (solapamiento horario, fuera del horario del salón o servicio inactivo)'
    )]
    public function store(StoreReservaRequest $request): JsonResponse
    {
        $usuario = $request->user();
        if (!$usuario->esAdmin() && (int) $request->input('personal_id') !== (int) $usuario->personal_id) {
            abort(403, 'Solo puedes agendar reservas en tu propia agenda.');
        }

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
    public function show(Request $request, Reserva $reserva): JsonResponse
    {
        $this->autorizarReserva($request, $reserva);

        return response()->json([
            'data' => $reserva->load(ReservaService::RELACIONES),
        ]);
    }

    /**
     * Actualizar una reserva existente.
     */
    #[OA\Put(
        path: '/api/reservas/{reserva}',
        summary: 'Actualizar una reserva existente',
        description: 'Actualiza campos de la reserva. Si se modifican horarios o profesional, re-valida el horario del salón y los solapamientos. Regla 5: Bloquea modificaciones si la reserva ya está completada.',
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
                new OA\Property(property: 'estado', type: 'string', enum: Reserva::ESTADOS, example: 'confirmada'),
                new OA\Property(property: 'descuento_id', type: 'integer', nullable: true, example: null),
                new OA\Property(property: 'a_domicilio', type: 'boolean', example: false),
                new OA\Property(property: 'direccion_domicilio', type: 'string', nullable: true, example: null),
                new OA\Property(property: 'fuera_de_horario', type: 'boolean', example: false)
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
        $this->autorizarReserva($request, $reserva);

        // Un profesional no puede pasarse una cita a la agenda de otra persona
        if (!$request->user()->esAdmin() && $request->has('personal_id') && (int) $request->input('personal_id') !== (int) $reserva->personal_id) {
            abort(403, 'Solo la administración puede reasignar una reserva a otro profesional.');
        }

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
    public function destroy(Request $request, Reserva $reserva): JsonResponse
    {
        $this->autorizarReserva($request, $reserva);

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
        $this->autorizarReserva($request, $reserva);

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

    /**
     * HU-02: Horarios libres de un profesional en una fecha para una cita de cierta duración.
     * La duración se indica en minutos o se calcula a partir de los servicios elegidos.
     */
    #[OA\Get(
        path: '/api/disponibilidad',
        summary: 'Horarios disponibles de un profesional',
        description: 'Recorre el horario del salón ese día (o la jornada 06:00–22:00 si fuera_de_horario es true) y marca cada inicio como disponible, ocupado (otra reserva) o pasado.',
        tags: ['Reservas']
    )]
    #[OA\Response(response: 200, description: 'Franjas del salón y horarios con su disponibilidad')]
    public function disponibilidad(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'personal_id' => ['required', 'integer', 'exists:personal,id'],
            'fecha' => ['required', 'date'],
            'duracion_minutos' => ['required_without:servicios', 'nullable', 'integer', 'min:5', 'max:600'],
            'servicios' => ['required_without:duracion_minutos', 'array'],
            'servicios.*' => ['integer', 'exists:servicios,id'],
            'fuera_de_horario' => ['nullable', 'boolean'],
            'intervalo' => ['nullable', 'integer', Rule::in([15, 30, 60])],
        ], [
            'duracion_minutos.required_without' => 'Indica la duración de la cita o los servicios elegidos.',
            'servicios.required_without' => 'Indica la duración de la cita o los servicios elegidos.',
        ]);

        $duracion = isset($datos['duracion_minutos'])
            ? (int) $datos['duracion_minutos']
            : (int) Servicio::whereIn('id', $datos['servicios'])->sum('duracion_minutos');

        $fecha = Carbon::parse($datos['fecha'])->toDateString();

        $resultado = $this->reservaService->horariosDisponibles(
            (int) $datos['personal_id'],
            $fecha,
            max($duracion, 5),
            (bool) ($datos['fuera_de_horario'] ?? false),
            (int) ($datos['intervalo'] ?? 30),
        );

        return response()->json([
            'data' => [
                'personal_id' => (int) $datos['personal_id'],
                'fecha' => $fecha,
                'duracion_minutos' => $duracion,
                ...$resultado,
            ],
        ]);
    }
}

