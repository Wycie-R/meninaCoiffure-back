<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservaRequest;
use App\Models\Personal;
use App\Models\SolicitudCita;
use App\Services\ReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Bandeja de la dueña: los pedidos de cita de las clientas se convierten en reservas
 * con las mismas reglas que una reserva cargada a mano, o se rechazan con un motivo.
 */
class SolicitudCitaController extends Controller
{
    public function __construct(
        protected ReservaService $reservaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'estado' => ['nullable', 'string', Rule::in(SolicitudCita::ESTADOS)],
        ]);
        $estado = $filtros['estado'] ?? 'pendiente';

        $consulta = SolicitudCita::with(['cliente', 'servicios', 'reserva'])->where('estado', $estado);

        // Las pendientes, por el día que pidieron (lo más urgente primero); el resto, lo último respondido
        if ($estado === 'pendiente') {
            $consulta->orderBy('fecha_preferida')->orderBy('created_at');
        } else {
            $consulta->orderByDesc('updated_at');
        }

        $solicitudes = $consulta->limit(50)->get();

        return response()->json([
            'data' => $solicitudes,
            'pendientes' => SolicitudCita::where('estado', 'pendiente')->count(),
        ]);
    }

    public function show(SolicitudCita $solicitud): JsonResponse
    {
        $solicitud->load(['cliente', 'servicios', 'reserva']);

        $datos = $solicitud->toArray();
        // Sugerencia para agendar: quiénes están libres hoy en el horario que pidió la clienta
        $datos['profesionales_libres'] = $this->profesionalesLibres($solicitud);

        return response()->json(['data' => $datos]);
    }

    /**
     * Recibe lo mismo que POST /api/reservas. Si la reserva no se puede crear (choque de horario,
     * fuera del horario del salón, servicio inactivo) no se crea nada y la solicitud sigue pendiente.
     */
    public function aceptar(StoreReservaRequest $request, SolicitudCita $solicitud): JsonResponse
    {
        $datos = $request->validated();
        // La reserva siempre es de quien la pidió, aunque el cuerpo traiga otra clienta
        $datos['cliente_id'] = $solicitud->cliente_id;

        $reserva = DB::transaction(function () use ($datos, $solicitud) {
            // Se bloquea la fila: dos pestañas aceptando a la vez no crean dos reservas
            $solicitudBloqueada = SolicitudCita::whereKey($solicitud->id)->lockForUpdate()->first();
            $this->exigirPendiente($solicitudBloqueada);

            $nuevaReserva = $this->reservaService->crearReserva($datos);

            $solicitudBloqueada->estado = 'aceptada';
            $solicitudBloqueada->reserva_id = $nuevaReserva->id;
            $solicitudBloqueada->save();

            return $nuevaReserva;
        });

        return response()->json([
            'message' => 'Solicitud aceptada: la reserva quedó en la agenda.',
            'data' => $reserva,
        ], 201);
    }

    public function rechazar(Request $request, SolicitudCita $solicitud): JsonResponse
    {
        $datos = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'max:255'],
        ], [
            'motivo_rechazo.required' => 'Escribe el motivo: la clienta lo va a ver.',
            'motivo_rechazo.max' => 'El motivo no puede superar los 255 caracteres.',
        ]);

        $this->exigirPendiente($solicitud);

        $solicitud->estado = 'rechazada';
        $solicitud->motivo_rechazo = $datos['motivo_rechazo'];
        $solicitud->save();
        $solicitud->load(['cliente', 'servicios']);

        return response()->json([
            'message' => 'Solicitud rechazada.',
            'data' => $solicitud,
        ]);
    }

    private function profesionalesLibres(SolicitudCita $solicitud): array
    {
        if ($solicitud->estado !== 'pendiente' || $solicitud->hora_preferida === null) {
            return [];
        }

        $duracion = 0;
        foreach ($solicitud->servicios as $servicio) {
            $duracion += (int) $servicio->duracion_minutos;
        }

        $resultado = $this->reservaService->horariosDelSalon(
            $solicitud->fecha_preferida->toDateString(),
            max($duracion, 5),
            (bool) $solicitud->fuera_de_horario,
            (bool) $solicitud->a_domicilio,
        );

        $horaPedida = substr((string) $solicitud->hora_preferida, 0, 5);
        $libres = [];
        foreach ($resultado['horarios'] as $horario) {
            if ($horario['hora'] === $horaPedida) {
                $libres = $horario['profesionales'];
            }
        }

        return $this->ordenarPorEspecialidad($libres, $solicitud);
    }

    /**
     * La sugerencia pone primero a quien tiene todas las especialidades que piden los servicios,
     * así la dueña no asigna sin querer a alguien que no hace ese trabajo.
     */
    private function ordenarPorEspecialidad(array $profesionalesIds, SolicitudCita $solicitud): array
    {
        $requeridas = [];
        foreach ($solicitud->servicios as $servicio) {
            if ($servicio->especialidad_id !== null) {
                $requeridas[] = (int) $servicio->especialidad_id;
            }
        }

        if (count($requeridas) === 0 || count($profesionalesIds) === 0) {
            return $profesionalesIds;
        }

        $conTodas = [];
        $sinTodas = [];
        $profesionales = Personal::with('especialidades')->whereIn('id', $profesionalesIds)->get()->keyBy('id');
        foreach ($profesionalesIds as $id) {
            $tiene = [];
            foreach ($profesionales[$id]->especialidades as $especialidad) {
                $tiene[] = (int) $especialidad->id;
            }

            if (count(array_diff($requeridas, $tiene)) === 0) {
                $conTodas[] = $id;
            } else {
                $sinTodas[] = $id;
            }
        }

        return array_merge($conTodas, $sinTodas);
    }

    private function exigirPendiente(SolicitudCita $solicitud): void
    {
        if ($solicitud->estado !== 'pendiente') {
            throw ValidationException::withMessages([
                'estado' => ['Esta solicitud ya fue respondida o la clienta la retiró.'],
            ]);
        }
    }
}
