<?php

namespace App\Http\Controllers;

use App\Models\CierreSalon;
use App\Models\Cliente;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\SolicitudCita;
use App\Services\AvisoTelegram;
use App\Services\ReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Mis citas": lo que ve y pide una clienta con cuenta. Todo se filtra por la ficha vinculada
 * a la sesión; ningún id de clienta llega desde la URL o el cuerpo del pedido.
 */
class MiCuentaController extends Controller
{
    /** Citas pasadas que se muestran (las más recientes). */
    private const LIMITE_PASADAS = 10;

    /** Dirección que se guarda cuando la clienta solo compartió su ubicación del mapa. */
    public const DIRECCION_POR_MAPA = 'Ubicación compartida desde el celular (ver "Cómo llegar")';

    public function __construct(
        protected ReservaService $reservaService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $ficha = $this->fichaDeLaSesion($request);

        return response()->json([
            'data' => [
                'nombre' => $ficha->nombre,
                'apellido' => $ficha->apellido,
                'email' => $ficha->email,
                // Lo que ya dejó guardado: el formulario de pedido se completa solo
                'telefono' => $ficha->telefono,
                'direccion_domicilio' => $ficha->direccion_domicilio,
                'latitud' => $ficha->latitud,
                'longitud' => $ficha->longitud,
            ],
        ]);
    }

    /**
     * Horarios que la clienta puede elegir para su pedido: los libres de cualquier profesional.
     * No se dice quién está libre ni por qué un horario no está: solo si se puede pedir.
     */
    public function horarios(Request $request): JsonResponse
    {
        $this->fichaDeLaSesion($request);

        $datos = $request->validate([
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'servicios' => ['required', 'array', 'min:1', 'max:' . SolicitudCita::MAXIMO_SERVICIOS],
            'servicios.*' => ['integer', Rule::exists('servicios', 'id')->where('activo', true)],
            'a_domicilio' => ['nullable', 'boolean'],
            'fuera_de_horario' => ['nullable', 'boolean'],
        ], [
            'fecha.after_or_equal' => 'La fecha no puede ser anterior a hoy.',
            'servicios.required' => 'Elige al menos un servicio.',
        ]);

        $fecha = substr($datos['fecha'], 0, 10);
        $duracion = $this->duracionDe($datos['servicios']);
        $fueraDeHorario = (bool) ($datos['fuera_de_horario'] ?? false);
        $aDomicilio = (bool) ($datos['a_domicilio'] ?? false);
        $resultado = $this->reservaService->horariosDelSalon($fecha, $duracion, $fueraDeHorario, $aDomicilio);

        $horarios = [];
        foreach ($resultado['horarios'] as $horario) {
            $horarios[] = ['hora' => $horario['hora'], 'disponible' => $horario['disponible']];
        }

        // Sin franjas es que el salón no abre ese día: la clienta puede pedirlo fuera de horario,
        // salvo que la dueña haya cerrado ese día puntual (entonces no se atiende de ninguna forma)
        $salonAbre = count($this->reservaService->franjasLocal($fecha)) > 0;
        $cierre = CierreSalon::delDia($fecha);
        $cerrado = null;
        if ($cierre !== null) {
            $cerrado = ['motivo' => $cierre->motivo];
        }

        return response()->json([
            'data' => [
                'fecha' => $fecha,
                'duracion_minutos' => $duracion,
                'salon_abre' => $salonAbre,
                'cerrado' => $cerrado,
                'fuera_de_horario' => $fueraDeHorario,
                'franjas' => $resultado['franjas'],
                'horarios' => $horarios,
            ],
        ]);
    }

    public function citas(Request $request): JsonResponse
    {
        $ficha = $this->fichaDeLaSesion($request);
        $hoy = now()->toDateString();

        $proximas = Reserva::with(['personal', 'detalles.servicio'])
            ->where('cliente_id', $ficha->id)
            ->where('fecha', '>=', $hoy)
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        $pasadas = Reserva::with(['personal', 'detalles.servicio'])
            ->where('cliente_id', $ficha->id)
            ->where(function ($consulta) use ($hoy) {
                $consulta->where('fecha', '<', $hoy)
                    ->orWhereIn('estado', Reserva::ESTADOS_FINALES);
            })
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->limit(self::LIMITE_PASADAS)
            ->get();

        $listaProximas = [];
        foreach ($proximas as $reserva) {
            $listaProximas[] = $this->citaParaLaClienta($reserva);
        }

        $listaPasadas = [];
        foreach ($pasadas as $reserva) {
            $listaPasadas[] = $this->citaParaLaClienta($reserva);
        }

        return response()->json([
            'data' => [
                'proximas' => $listaProximas,
                'pasadas' => $listaPasadas,
            ],
        ]);
    }

    public function solicitudes(Request $request): JsonResponse
    {
        $ficha = $this->fichaDeLaSesion($request);

        $solicitudes = SolicitudCita::with(['servicios', 'reserva'])
            ->where('cliente_id', $ficha->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $lista = [];
        foreach ($solicitudes as $solicitud) {
            $lista[] = $this->solicitudParaLaClienta($solicitud);
        }

        return response()->json(['data' => $lista]);
    }

    public function pedirCita(Request $request): JsonResponse
    {
        $ficha = $this->fichaDeLaSesion($request);

        $datos = $request->validate([
            'servicios' => ['required', 'array', 'min:1', 'max:' . SolicitudCita::MAXIMO_SERVICIOS],
            'servicios.*' => ['required', 'integer', 'distinct', Rule::exists('servicios', 'id')->where('activo', true)],
            'fecha_preferida' => ['required', 'date', 'after_or_equal:today'],
            // La clienta elige la hora; la franja queda por compatibilidad y se deduce de la hora
            'hora_preferida' => ['required', 'date_format:H:i'],
            'fuera_de_horario' => ['nullable', 'boolean'],
            'franja' => ['nullable', 'string', Rule::in(SolicitudCita::FRANJAS)],
            'a_domicilio' => ['nullable', 'boolean'],
            // A domicilio basta con la dirección escrita o con la ubicación del celular (se revisa abajo)
            'direccion_domicilio' => ['nullable', 'string', 'max:255'],
            // Ubicación del celular ("Estoy en casa"): opcional, las dos coordenadas juntas
            'latitud' => ['nullable', 'required_with:longitud', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'required_with:latitud', 'numeric', 'between:-180,180'],
            'comentario' => ['nullable', 'string', 'max:500'],
            // Con el teléfono la dueña puede coordinar; se guarda en la ficha
            'telefono' => ['nullable', 'string', 'max:30'],
        ], [
            'servicios.required' => 'Elige al menos un servicio.',
            'servicios.min' => 'Elige al menos un servicio.',
            'servicios.max' => 'Puedes pedir hasta ' . SolicitudCita::MAXIMO_SERVICIOS . ' servicios en una misma cita.',
            'servicios.*.distinct' => 'Elegiste el mismo servicio dos veces.',
            'servicios.*.exists' => 'Uno de los servicios ya no está disponible.',
            'fecha_preferida.required' => 'Elige el día que prefieres.',
            'fecha_preferida.date' => 'La fecha no tiene un formato válido.',
            'fecha_preferida.after_or_equal' => 'La fecha no puede ser anterior a hoy.',
            'hora_preferida.required' => 'Elige el horario que prefieres.',
            'hora_preferida.date_format' => 'El horario no tiene un formato válido.',
            'franja.in' => 'La franja debe ser mañana, tarde o indistinto.',
            'latitud.between' => 'La ubicación no es válida. Vuelve a intentarlo.',
            'longitud.between' => 'La ubicación no es válida. Vuelve a intentarlo.',
            'latitud.required_with' => 'La ubicación llegó incompleta. Vuelve a intentarlo.',
            'longitud.required_with' => 'La ubicación llegó incompleta. Vuelve a intentarlo.',
            'direccion_domicilio.max' => 'La dirección no puede superar los 255 caracteres.',
            'comentario.max' => 'El comentario no puede superar los 500 caracteres.',
            'telefono.max' => 'El teléfono no puede superar los 30 caracteres.',
        ]);

        $pendientes = SolicitudCita::where('cliente_id', $ficha->id)->where('estado', 'pendiente')->count();
        if ($pendientes >= SolicitudCita::MAXIMO_PENDIENTES) {
            throw ValidationException::withMessages([
                'servicios' => ['Ya tienes ' . SolicitudCita::MAXIMO_PENDIENTES . ' pedidos esperando respuesta. Espera a que el salón los responda.'],
            ]);
        }

        $aDomicilio = (bool) ($datos['a_domicilio'] ?? false);
        $direccionEscrita = trim((string) ($datos['direccion_domicilio'] ?? ''));
        $tieneUbicacion = isset($datos['latitud']) && isset($datos['longitud']);

        $direccion = null;
        if ($aDomicilio) {
            if ($direccionEscrita === '' && !$tieneUbicacion) {
                throw ValidationException::withMessages([
                    'direccion_domicilio' => ['Escribe tu dirección o toca "Estoy en casa" para compartir tu ubicación.'],
                ]);
            }

            // La base exige una dirección en toda cita a domicilio: con solo el mapa se deja dicho
            $direccion = $direccionEscrita;
            if ($direccion === '') {
                $direccion = self::DIRECCION_POR_MAPA;
            }
        }

        $fecha = substr($datos['fecha_preferida'], 0, 10);
        $hora = $datos['hora_preferida'];
        $fueraDeHorario = (bool) ($datos['fuera_de_horario'] ?? false);
        $this->exigirHorarioLibre($fecha, $hora, $datos['servicios'], $fueraDeHorario, $aDomicilio);

        $franja = 'tarde';
        if ($hora < '12:00') {
            $franja = 'manana';
        }

        // Teléfono, dirección y ubicación quedan en la ficha para el próximo pedido
        $telefono = trim((string) ($datos['telefono'] ?? ''));
        if ($telefono !== '') {
            $ficha->telefono = $telefono;
        }
        // El texto de "solo mapa" no reemplaza una dirección que la clienta ya había escrito
        if ($aDomicilio && $direccionEscrita !== '') {
            $ficha->direccion_domicilio = $direccionEscrita;
        }
        if (isset($datos['latitud'])) {
            $ficha->latitud = $datos['latitud'];
            $ficha->longitud = $datos['longitud'];
        }

        // La solicitud, sus servicios y los datos de la ficha se guardan juntos
        $solicitud = DB::transaction(function () use ($ficha, $datos, $aDomicilio, $direccion, $fecha, $hora, $franja, $fueraDeHorario) {
            $ficha->save();

            $nueva = SolicitudCita::create([
                'cliente_id' => $ficha->id,
                'fecha_preferida' => $fecha,
                'hora_preferida' => $hora,
                'franja' => $franja,
                'fuera_de_horario' => $fueraDeHorario,
                'a_domicilio' => $aDomicilio,
                'direccion_domicilio' => $direccion,
                'comentario' => $datos['comentario'] ?? null,
                'estado' => 'pendiente',
            ]);
            $nueva->servicios()->attach($datos['servicios']);

            return $nueva;
        });
        $solicitud->load(['servicios', 'reserva']);

        // Se avisa después de responder: la clienta no espera a Telegram y, si falla, el pedido ya está guardado
        $solicitudId = $solicitud->id;
        dispatch(function () use ($solicitudId) {
            $pedido = SolicitudCita::find($solicitudId);
            if ($pedido !== null) {
                app(AvisoTelegram::class)->avisarNuevaSolicitud($pedido);
            }
        })->afterResponse();

        return response()->json([
            'message' => 'Recibimos tu pedido. El salón te va a confirmar la cita.',
            'data' => $this->solicitudParaLaClienta($solicitud),
        ], 201);
    }

    /**
     * La clienta retira su pedido mientras nadie lo respondió. No se borra: queda como cancelada.
     */
    public function cancelarSolicitud(Request $request, SolicitudCita $solicitud): JsonResponse
    {
        $ficha = $this->fichaDeLaSesion($request);

        // Un pedido ajeno responde igual que uno inexistente: no se revela que existe
        if ((int) $solicitud->cliente_id !== (int) $ficha->id) {
            abort(404);
        }

        if ($solicitud->estado !== 'pendiente') {
            throw ValidationException::withMessages([
                'estado' => ['Este pedido ya fue respondido por el salón y no se puede retirar.'],
            ]);
        }

        $solicitud->estado = 'cancelada';
        $solicitud->save();
        $solicitud->load(['servicios', 'reserva']);

        return response()->json([
            'message' => 'Retiraste tu pedido.',
            'data' => $this->solicitudParaLaClienta($solicitud),
        ]);
    }

    /**
     * El pedido no ocupa la agenda, pero no se acepta un horario que ya nadie puede atender
     * (por ejemplo, si se ocupó mientras la clienta completaba el formulario).
     */
    private function exigirHorarioLibre(string $fecha, string $hora, array $servicios, bool $fueraDeHorario, bool $aDomicilio): void
    {
        $resultado = $this->reservaService->horariosDelSalon($fecha, $this->duracionDe($servicios), $fueraDeHorario, $aDomicilio);

        foreach ($resultado['horarios'] as $horario) {
            if ($horario['hora'] === $hora && $horario['disponible']) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'hora_preferida' => ['Ese horario ya no está libre. Elige otro, por favor.'],
        ]);
    }

    private function duracionDe(array $servicios): int
    {
        $minutos = (int) Servicio::whereIn('id', $servicios)->sum('duracion_minutos');

        return max($minutos, 5);
    }

    private function fichaDeLaSesion(Request $request): Cliente
    {
        $ficha = $request->user()->cliente;

        if ($ficha === null) {
            abort(404, 'Tu cuenta todavía no tiene una ficha de clienta. Comunícate con el salón.');
        }

        return $ficha;
    }

    /**
     * Solo lo que le sirve a la clienta: sin montos internos, pagos ni datos de otras personas.
     */
    private function citaParaLaClienta(Reserva $reserva): array
    {
        // Con id y si sigue activo: "Volver a pedir" solo ofrece lo que todavía se puede pedir
        $servicios = [];
        foreach ($reserva->detalles as $detalle) {
            if ($detalle->servicio !== null) {
                $servicios[] = [
                    'id' => $detalle->servicio->id,
                    'nombre' => $detalle->servicio->nombre,
                    'activo' => (bool) $detalle->servicio->activo,
                ];
            }
        }

        $profesional = null;
        if ($reserva->personal !== null) {
            $profesional = $reserva->personal->nombre;
        }

        return [
            'id' => $reserva->id,
            'fecha' => $reserva->fecha,
            'hora_inicio' => substr((string) $reserva->hora_inicio, 0, 5),
            'hora_fin' => substr((string) $reserva->hora_fin, 0, 5),
            'estado' => $reserva->estado,
            'a_domicilio' => $reserva->a_domicilio,
            'direccion_domicilio' => $reserva->direccion_domicilio,
            'profesional' => $profesional,
            'servicios' => $servicios,
            'total' => $reserva->total,
        ];
    }

    private function solicitudParaLaClienta(SolicitudCita $solicitud): array
    {
        $servicios = [];
        foreach ($solicitud->servicios as $servicio) {
            $servicios[] = ['id' => $servicio->id, 'nombre' => $servicio->nombre, 'activo' => (bool) $servicio->activo];
        }

        // Al aceptarse, la clienta ve el día y la hora que le asignó el salón
        $citaAsignada = null;
        if ($solicitud->reserva !== null) {
            $citaAsignada = [
                'fecha' => $solicitud->reserva->fecha,
                'hora_inicio' => substr((string) $solicitud->reserva->hora_inicio, 0, 5),
            ];
        }

        return [
            'id' => $solicitud->id,
            // Con id, para "Volver a pedir" lo mismo desde Mis citas
            'servicios' => $servicios,
            'fecha_preferida' => $solicitud->fecha_preferida?->toDateString(),
            'hora_preferida' => $this->horaCorta($solicitud->hora_preferida),
            'fuera_de_horario' => (bool) $solicitud->fuera_de_horario,
            'franja' => $solicitud->franja,
            'a_domicilio' => $solicitud->a_domicilio,
            'direccion_domicilio' => $solicitud->direccion_domicilio,
            'comentario' => $solicitud->comentario,
            'estado' => $solicitud->estado,
            'motivo_rechazo' => $solicitud->motivo_rechazo,
            'cita_asignada' => $citaAsignada,
            'creada' => $solicitud->created_at?->toDateTimeString(),
        ];
    }

    private function horaCorta(?string $hora): ?string
    {
        if ($hora === null) {
            return null;
        }

        return substr($hora, 0, 5);
    }
}
