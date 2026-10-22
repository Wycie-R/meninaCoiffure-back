<?php

namespace App\Services;

use App\Events\ReservaCompletada;
use App\Models\Descuento;
use App\Models\DetalleReserva;
use App\Models\HorarioAtencion;
use App\Models\Personal;
use App\Models\PrecioServicio;
use App\Models\Reserva;
use App\Models\Servicio;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservaService
{
    /** Relaciones que acompañan a una reserva en las respuestas de la API. */
    public const RELACIONES = ['cliente', 'personal', 'detalles.servicio', 'descuento', 'pagos'];

    /**
     * Minutos de tolerancia para registrar una cita que ya empezó: clientes que llegan
     * sin cita y se atienden por orden de llegada.
     */
    public const TOLERANCIA_SIN_CITA = 30;

    /** Límites de una atención fuera del horario del salón (temprano a la mañana o a la noche). */
    public const JORNADA_MAXIMA = ['06:00:00', '22:00:00'];

    /**
     * Transiciones de estado permitidas por PUT. Los estados finales no aparecen como origen.
     * La cancelación se delega en cancelarReserva para aplicar sus propias reglas.
     */
    protected const TRANSICIONES = [
        'pendiente' => ['confirmada', 'completada', 'no_asistio', 'cancelada'],
        'confirmada' => ['completada', 'no_asistio', 'cancelada'],
    ];

    /** Estados a los que solo se llega cuando la cita ya empezó. */
    protected const ESTADOS_TRAS_INICIO = ['completada', 'no_asistio'];

    /**
     * Crear una nueva reserva con sus detalles y precios congelados.
     *
     * @param array $datos
     * @return Reserva
     * @throws ValidationException
     */
    public function crearReserva(array $datos): Reserva
    {
        return DB::transaction(function () use ($datos) {
            $personalId = $datos['personal_id'];
            $fecha = $datos['fecha'];
            $horaInicio = $this->normalizarHora($datos['hora_inicio']);
            $horaFin = $this->normalizarHora($datos['hora_fin']);
            $domicilio = $this->datosDomicilio($datos);
            $fueraDeHorario = (bool) ($datos['fuera_de_horario'] ?? false);

            // Bloquea al profesional hasta el fin de la transacción: dos pedidos simultáneos
            // (por ejemplo, en nodos distintos detrás de HAProxy) no pueden pasar ambos la Regla 1
            $this->validarPersonalActivo($personalId, bloquear: true);
            $this->validarHorarioFuturo($fecha, $horaInicio);

            // Regla 1: la profesional no puede tener dos citas activas a la vez (traslados incluidos)
            $this->validarSolapamiento($personalId, $fecha, $horaInicio, $horaFin, $domicilio['minutos_traslado']);

            // Regla 2: dentro del horario del salón, salvo atención fuera de horario autorizada
            $this->validarHorarioSalon($fecha, $horaInicio, $horaFin, $fueraDeHorario);

            if (!empty($datos['descuento_id'])) {
                $this->validarDescuentoVigente((int) $datos['descuento_id'], $fecha);
            }

            // Validar servicios requeridos
            $servicios = $datos['servicios'] ?? [];
            if (empty($servicios)) {
                throw ValidationException::withMessages([
                    'servicios' => ['Debe seleccionar al menos un servicio para la reserva.'],
                ]);
            }

            // Pre-validar Reglas 3 y 4 para todos los servicios antes de crear cabecera
            $detallesParaCrear = [];
            foreach ($servicios as $item) {
                $servicioId = is_array($item) ? ($item['servicio_id'] ?? $item['id'] ?? null) : $item;
                $cantidad = is_array($item) ? ($item['cantidad'] ?? 1) : 1;

                if (!$servicioId) {
                    throw ValidationException::withMessages([
                        'servicios' => ['El servicio especificado es inválido.'],
                    ]);
                }

                $precioAplicado = $this->obtenerPrecioVigente($servicioId, $fecha);

                $detallesParaCrear[] = [
                    'servicio_id' => $servicioId,
                    'precio_aplicado' => $precioAplicado,
                    'cantidad' => $cantidad,
                    'agregado_en_el_momento' => false,
                ];
            }

            // Crear cabecera de la reserva
            $reserva = Reserva::create([
                'cliente_id' => $datos['cliente_id'],
                'personal_id' => $personalId,
                'fecha' => $fecha,
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin,
                'estado' => $datos['estado'] ?? 'pendiente',
                'descuento_id' => $datos['descuento_id'] ?? null,
                'fuera_de_horario' => $fueraDeHorario,
                ...$domicilio,
            ]);

            // Insertar detalles asociados
            foreach ($detallesParaCrear as $detalle) {
                $reserva->detalles()->create($detalle);
            }

            return $reserva->load(self::RELACIONES);
        });
    }

    /**
     * Actualizar una reserva existente.
     *
     * @param Reserva $reserva
     * @param array $datos
     * @return Reserva
     * @throws ValidationException
     */
    public function actualizarReserva(Reserva $reserva, array $datos): Reserva
    {
        // Regla 5: Una reserva completada es inmutable
        if ($reserva->estado === 'completada') {
            throw ValidationException::withMessages([
                'estado' => ['Una reserva en estado completada es inmutable y no puede modificarse ni cambiar a otro estado.'],
            ]);
        }

        // Regla de Negocio: Una reserva cancelada no puede reactivarse ni modificarse
        if ($reserva->estado === 'cancelada') {
            throw ValidationException::withMessages([
                'estado' => ['Una reserva en estado cancelada no puede ser modificada ni reactivarse.'],
            ]);
        }

        if ($reserva->estado === 'no_asistio') {
            throw ValidationException::withMessages([
                'estado' => ['La reserva quedó registrada como no asistida y ya no puede modificarse.'],
            ]);
        }

        $nuevoEstado = $datos['estado'] ?? $reserva->estado;

        // Cancelar por PUT sigue las mismas reglas que DELETE (no se cancela una cita ya empezada)
        if ($nuevoEstado === 'cancelada') {
            return $this->cancelarReserva($reserva)->load(self::RELACIONES);
        }

        return DB::transaction(function () use ($reserva, $datos, $nuevoEstado) {
            $estadoAnterior = $reserva->estado;

            if ($nuevoEstado !== $estadoAnterior) {
                $this->validarTransicion($reserva, $nuevoEstado);
            }

            $personalId = $datos['personal_id'] ?? $reserva->personal_id;
            $fecha = $datos['fecha'] ?? $reserva->fecha;
            $horaInicio = isset($datos['hora_inicio']) ? $this->normalizarHora($datos['hora_inicio']) : $reserva->hora_inicio;
            $horaFin = isset($datos['hora_fin']) ? $this->normalizarHora($datos['hora_fin']) : $reserva->hora_fin;

            $cambiaDomicilio = array_key_exists('a_domicilio', $datos)
                || array_key_exists('direccion_domicilio', $datos)
                || array_key_exists('minutos_traslado', $datos);

            $domicilio = $this->datosDomicilio([
                'a_domicilio' => $datos['a_domicilio'] ?? $reserva->a_domicilio,
                'direccion_domicilio' => array_key_exists('direccion_domicilio', $datos)
                    ? $datos['direccion_domicilio']
                    : $reserva->direccion_domicilio,
                'minutos_traslado' => $datos['minutos_traslado'] ?? $reserva->minutos_traslado,
            ]);

            // Si no se envía, la reserva conserva lo que la dueña autorizó al crearla
            if (array_key_exists('fuera_de_horario', $datos)) {
                $fueraDeHorario = (bool) $datos['fuera_de_horario'];
            } else {
                $fueraDeHorario = (bool) $reserva->fuera_de_horario;
            }

            $horarioCambiado = (
                (isset($datos['fecha']) && $datos['fecha'] !== $reserva->fecha) ||
                (isset($datos['hora_inicio']) && $this->normalizarHora($datos['hora_inicio']) !== $this->normalizarHora($reserva->hora_inicio)) ||
                (isset($datos['hora_fin']) && $this->normalizarHora($datos['hora_fin']) !== $this->normalizarHora($reserva->hora_fin)) ||
                (isset($datos['personal_id']) && $datos['personal_id'] !== $reserva->personal_id) ||
                $fueraDeHorario !== (bool) $reserva->fuera_de_horario ||
                $domicilio['a_domicilio'] !== $reserva->a_domicilio ||
                $domicilio['minutos_traslado'] !== (int) $reserva->minutos_traslado
            );

            if ($horarioCambiado) {
                if ($horaFin <= $horaInicio) {
                    throw ValidationException::withMessages([
                        'hora_fin' => ['La hora de fin debe ser posterior a la hora de inicio.'],
                    ]);
                }

                $this->validarPersonalActivo($personalId, bloquear: true);
                $this->validarHorarioFuturo($fecha, $horaInicio);

                // Revalidar Regla 1 excluyendo la reserva actual
                $this->validarSolapamiento($personalId, $fecha, $horaInicio, $horaFin, $domicilio['minutos_traslado'], $reserva->id);

                // Revalidar Regla 2
                $this->validarHorarioSalon($fecha, $horaInicio, $horaFin, $fueraDeHorario);
            }

            $camposActualizables = [
                'cliente_id', 'personal_id', 'fecha', 'hora_inicio', 'hora_fin', 'estado', 'descuento_id',
            ];

            $datosActualizar = array_intersect_key($datos, array_flip($camposActualizables));
            if (array_key_exists('fuera_de_horario', $datos)) {
                $datosActualizar['fuera_de_horario'] = $fueraDeHorario;
            }
            if (isset($datosActualizar['hora_inicio'])) {
                $datosActualizar['hora_inicio'] = $horaInicio;
            }
            if (isset($datosActualizar['hora_fin'])) {
                $datosActualizar['hora_fin'] = $horaFin;
            }
            if ($cambiaDomicilio) {
                $datosActualizar = [...$datosActualizar, ...$domicilio];
            }
            if (!empty($datosActualizar['descuento_id'])) {
                $this->validarDescuentoVigente((int) $datosActualizar['descuento_id'], $fecha);
            }

            $reserva->update($datosActualizar);

            // Modificar la reserva: la lista de servicios enviada reemplaza a la actual
            if (array_key_exists('servicios', $datos)) {
                $this->reemplazarServicios($reserva, $datos['servicios'], substr((string) $fecha, 0, 10));
            }

            // HU-13: al completar, la visita pasa al historial de la clienta
            if ($nuevoEstado === 'completada' && $estadoAnterior !== 'completada') {
                ReservaCompletada::dispatch($reserva->fresh(), $datos['notas'] ?? null);
            }

            return $reserva->fresh(self::RELACIONES);
        });
    }

    /**
     * Reemplaza los servicios de una reserva por la lista enviada.
     * Los que ya estaban conservan su precio congelado y su cantidad (Regla 4); los nuevos toman el
     * precio vigente a la fecha de la cita (Reglas 3 y 4); los que no vienen en la lista se quitan.
     * Lo ya pagado no puede quedar por encima del nuevo total.
     */
    protected function reemplazarServicios(Reserva $reserva, array $servicios, string $fecha): void
    {
        $idsPedidos = [];
        foreach ($servicios as $item) {
            if (is_array($item)) {
                $servicioId = $item['servicio_id'] ?? $item['id'] ?? null;
            } else {
                $servicioId = $item;
            }

            if (!$servicioId) {
                throw ValidationException::withMessages([
                    'servicios' => ['El servicio especificado es inválido.'],
                ]);
            }

            $idsPedidos[] = (int) $servicioId;
        }

        if (count($idsPedidos) === 0) {
            throw ValidationException::withMessages([
                'servicios' => ['La reserva debe tener al menos un servicio.'],
            ]);
        }

        $lineasActuales = $reserva->detalles()->get();
        $idsActuales = [];
        foreach ($lineasActuales as $linea) {
            $idsActuales[] = (int) $linea->servicio_id;
        }

        // Primero se validan los servicios nuevos (activos y con precio): si alguno falla, no se toca nada
        $lineasNuevas = [];
        foreach ($idsPedidos as $servicioId) {
            if (!in_array($servicioId, $idsActuales, true)) {
                $lineasNuevas[] = [
                    'servicio_id' => $servicioId,
                    'precio_aplicado' => $this->obtenerPrecioVigente($servicioId, $fecha),
                    'cantidad' => 1,
                    'agregado_en_el_momento' => false,
                ];
            }
        }

        foreach ($lineasActuales as $linea) {
            if (!in_array((int) $linea->servicio_id, $idsPedidos, true)) {
                $linea->delete();
            }
        }

        foreach ($lineasNuevas as $linea) {
            $reserva->detalles()->create($linea);
        }

        $reserva->load(['detalles', 'descuento', 'pagos']);

        if ($reserva->pagado > $reserva->total) {
            $pagado = number_format($reserva->pagado, 0, ',', '.');
            $total = number_format($reserva->total, 0, ',', '.');

            throw ValidationException::withMessages([
                'servicios' => ["Ya se pagaron Gs. {$pagado} y el nuevo total sería Gs. {$total}. Quita un pago antes de sacar servicios."],
            ]);
        }
    }

    /**
     * Agregar un nuevo servicio a la reserva en el momento.
     *
     * @param Reserva $reserva
     * @param int $servicioId
     * @param int $cantidad
     * @return DetalleReserva
     * @throws ValidationException
     */
    public function agregarDetalle(Reserva $reserva, int $servicioId, int $cantidad = 1): DetalleReserva
    {
        return DB::transaction(function () use ($reserva, $servicioId, $cantidad) {
            // Regla de Negocio: Solo reservas activas admiten nuevos servicios
            if (in_array($reserva->estado, Reserva::ESTADOS_FINALES)) {
                throw ValidationException::withMessages([
                    'estado' => ["No se pueden agregar servicios a una reserva en estado {$reserva->estado}."],
                ]);
            }

            // Regla 3 y Regla 4: Validar servicio activo y precio vigente congelado
            $precioAplicado = $this->obtenerPrecioVigente($servicioId, $reserva->fecha);

            return DetalleReserva::create([
                'reserva_id' => $reserva->id,
                'servicio_id' => $servicioId,
                'precio_aplicado' => $precioAplicado,
                'cantidad' => $cantidad,
                'agregado_en_el_momento' => true,
            ])->load('servicio');
        });
    }

    /**
     * Cancelar una reserva existente.
     *
     * @param Reserva $reserva
     * @return Reserva
     * @throws ValidationException
     */
    public function cancelarReserva(Reserva $reserva): Reserva
    {
        return DB::transaction(function () use ($reserva) {
            // Regla 5: Solo se puede cancelar si el estado actual es distinto de completada
            if ($reserva->estado === 'completada') {
                throw ValidationException::withMessages([
                    'estado' => ['No se puede cancelar una reserva que ya ha sido completada.'],
                ]);
            }

            // Regla de Negocio: No se puede cancelar una reserva que ya está cancelada
            if ($reserva->estado === 'cancelada') {
                throw ValidationException::withMessages([
                    'estado' => ['La reserva ya se encuentra cancelada.'],
                ]);
            }

            if ($reserva->estado === 'no_asistio') {
                throw ValidationException::withMessages([
                    'estado' => ['La reserva quedó registrada como no asistida y no se puede cancelar.'],
                ]);
            }

            // Regla de Negocio: No se puede cancelar una reserva cuya cita ya transcurrió
            if ($this->inicioDe($reserva)->isPast()) {
                throw ValidationException::withMessages([
                    'estado' => ['No se puede cancelar una reserva cuya fecha y hora de atención ya ha transcurrido.'],
                ]);
            }

            $reserva->update(['estado' => 'cancelada']);

            return $reserva->fresh();
        });
    }

    /**
     * HU-02: Horarios en los que una profesional puede recibir una cita de cierta duración.
     * Recorre el horario del salón (o la jornada máxima, si es fuera de horario) cada $intervalo
     * minutos y marca cada inicio como disponible, ocupado (Regla 1, con traslados) o pasado.
     * El traslado de una cita a domicilio ocupa a la profesional antes y después, pero la cita
     * en sí es la que debe caber en el horario: el viaje puede empezar antes de abrir.
     * Si el salón no abre ese día y no es fuera de horario, no hay franjas ni horarios.
     *
     * @return array{franjas: array<int, array{0: string, 1: string}>, horarios: array<int, array{hora: string, disponible: bool, motivo: ?string}>}
     */
    public function horariosDisponibles(int $personalId, string $fecha, int $duracion, bool $fueraDeHorario = false, bool $aDomicilio = false, int $traslado = 0, int $intervalo = 30, ?int $excluirReservaId = null): array
    {
        // El traslado solo cuenta para citas a domicilio
        if (!$aDomicilio) {
            $traslado = 0;
        }

        if ($fueraDeHorario) {
            $franjas = [self::JORNADA_MAXIMA];
        } else {
            $franjas = $this->franjasLocal($fecha);
        }

        // Al modificar una reserva, su propio horario no cuenta como ocupado
        $ocupados = $this->bloquesOcupados($personalId, $fecha, $excluirReservaId);
        $limitePasado = now()->subMinutes(self::TOLERANCIA_SIN_CITA);
        $horarios = [];

        foreach ($franjas as [$inicioFranja, $finFranja]) {
            $desde = $this->aMinutos($inicioFranja);
            $hasta = $this->aMinutos($finFranja);

            for ($inicio = $desde; $inicio + $duracion <= $hasta; $inicio += $intervalo) {
                $fin = $inicio + $duracion;
                $motivo = null;

                if (Carbon::parse($fecha)->startOfDay()->addMinutes($inicio)->lt($limitePasado)) {
                    $motivo = 'pasado';
                } elseif ($this->chocaConBloques($inicio - $traslado, $fin + $traslado, $ocupados)) {
                    $motivo = 'ocupado';
                }

                $horarios[] = ['hora' => $this->aHora($inicio), 'disponible' => $motivo === null, 'motivo' => $motivo];
            }
        }

        return [
            'franjas' => array_map(fn ($f) => [substr($f[0], 0, 5), substr($f[1], 0, 5)], $franjas),
            'horarios' => $horarios,
        ];
    }

    /**
     * Valida que el cambio de estado esté permitido y respete el momento de la cita.
     */
    protected function validarTransicion(Reserva $reserva, string $nuevoEstado): void
    {
        if (!in_array($nuevoEstado, self::TRANSICIONES[$reserva->estado] ?? [], true)) {
            throw ValidationException::withMessages([
                'estado' => ["No se puede pasar una reserva de {$reserva->estado} a {$nuevoEstado}."],
            ]);
        }

        if (in_array($nuevoEstado, self::ESTADOS_TRAS_INICIO, true) && $this->inicioDe($reserva)->isFuture()) {
            $accion = $nuevoEstado === 'completada' ? 'completar' : 'marcar como no asistida';

            throw ValidationException::withMessages([
                'estado' => ["No se puede {$accion} una reserva antes de la hora de inicio de la cita."],
            ]);
        }
    }

    /**
     * El personal debe existir y estar activo para recibir reservas.
     */
    protected function validarPersonalActivo(int $personalId, bool $bloquear = false): void
    {
        $consulta = Personal::query();
        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        $personal = $consulta->find($personalId);

        if (!$personal || !$personal->activo) {
            throw ValidationException::withMessages([
                'personal_id' => ['El profesional seleccionado no está activo y no puede recibir reservas.'],
            ]);
        }
    }

    /**
     * No se reservan horarios que ya pasaron. Se toleran unos minutos para registrar
     * a quien llega sin cita y se atiende por orden de llegada.
     */
    protected function validarHorarioFuturo(string $fecha, string $horaInicio): void
    {
        if (Carbon::parse("{$fecha} {$horaInicio}")->lt(now()->subMinutes(self::TOLERANCIA_SIN_CITA))) {
            throw ValidationException::withMessages([
                'horario' => ['El horario seleccionado ya pasó. Elige un horario posterior a la hora actual.'],
            ]);
        }
    }

    /**
     * Regla 1: la profesional no puede tener dos reservas activas que se superpongan.
     * Una cita a domicilio ocupa también su traslado antes y después, así que se comparan
     * bloques [inicio - traslado, fin + traslado] de la cita nueva y de las existentes.
     */
    protected function validarSolapamiento(int $personalId, string $fecha, string $horaInicio, string $horaFin, int $traslado = 0, ?int $excluirReservaId = null): void
    {
        $bloquesOcupados = $this->bloquesOcupados($personalId, $fecha, $excluirReservaId);
        $inicioEnMinutos = $this->aMinutos($horaInicio) - $traslado;
        $finEnMinutos = $this->aMinutos($horaFin) + $traslado;

        if ($this->chocaConBloques($inicioEnMinutos, $finEnMinutos, $bloquesOcupados)) {
            throw ValidationException::withMessages([
                'horario' => ['El profesional ya cuenta con una reserva activa en el rango horario seleccionado.'],
            ]);
        }
    }

    /**
     * Regla 2: la cita debe caber en el horario del salón, sea en el local o a domicilio.
     * Las franjas contiguas se unen (08:00-13:00 + 13:00-17:00 = 08:00-17:00).
     * Si la dueña la marcó como fuera de horario, solo se exige la jornada máxima.
     */
    protected function validarHorarioSalon(string $fecha, string $horaInicio, string $horaFin, bool $fueraDeHorario): void
    {
        $inicioEnMinutos = $this->aMinutos($horaInicio);
        $finEnMinutos = $this->aMinutos($horaFin);

        if ($fueraDeHorario) {
            if (!$this->cabeEnFranjas($inicioEnMinutos, $finEnMinutos, [self::JORNADA_MAXIMA])) {
                throw ValidationException::withMessages([
                    'horario' => ['Una atención fuera de horario debe quedar entre las 06:00 y las 22:00.'],
                ]);
            }

            return;
        }

        $franjasDelSalon = $this->franjasLocal($fecha);

        if (count($franjasDelSalon) === 0) {
            throw ValidationException::withMessages([
                'horario' => ['El salón no atiende ese día. Si la cita se hará igual, márcala como fuera de horario.'],
            ]);
        }

        if (!$this->cabeEnFranjas($inicioEnMinutos, $finEnMinutos, $franjasDelSalon)) {
            throw ValidationException::withMessages([
                'horario' => ['La cita queda fuera del horario del salón. Si se hará igual, márcala como fuera de horario.'],
            ]);
        }
    }

    /**
     * Reglas 3 y 4: Validar servicio activo y obtener precio congelado vigente a la fecha de reserva.
     */
    protected function obtenerPrecioVigente(int $servicioId, string $fecha): float
    {
        $servicio = Servicio::find($servicioId);

        if (!$servicio) {
            throw ValidationException::withMessages([
                'servicio_id' => ["El servicio con ID {$servicioId} no fue encontrado."],
            ]);
        }

        // Regla 3: Ningún servicio agregado puede tener activo = false
        if (!$servicio->activo) {
            throw ValidationException::withMessages([
                'servicio_id' => ["El servicio '{$servicio->nombre}' se encuentra inactivo y no puede ser seleccionado."],
            ]);
        }

        // Regla 4: Consultar precio vigente (vigente_desde <= fecha AND (vigente_hasta IS NULL OR vigente_hasta >= fecha))
        $precioVigente = PrecioServicio::where('servicio_id', $servicioId)
            ->where('vigente_desde', '<=', $fecha)
            ->where(function ($query) use ($fecha) {
                $query->whereNull('vigente_hasta')
                    ->orWhere('vigente_hasta', '>=', $fecha);
            })
            ->orderByDesc('vigente_desde')
            ->first();

        if (!$precioVigente) {
            throw ValidationException::withMessages([
                'precio' => ["No existe un precio vigente para el servicio '{$servicio->nombre}' en la fecha {$fecha}."],
            ]);
        }

        return (float) $precioVigente->precio;
    }

    /**
     * HU-22: solo se aplica una promoción activa y vigente en la fecha de la cita.
     */
    protected function validarDescuentoVigente(int $descuentoId, string $fecha): void
    {
        $descuento = Descuento::find($descuentoId);
        $vigente = $descuento
            && $descuento->activo
            && ($descuento->fecha_inicio === null || $descuento->fecha_inicio <= $fecha)
            && ($descuento->fecha_fin === null || $descuento->fecha_fin >= $fecha);

        if (!$vigente) {
            throw ValidationException::withMessages([
                'descuento_id' => ['La promoción elegida no está activa o no rige en la fecha de la cita.'],
            ]);
        }
    }

    /**
     * HU-14: una cita a domicilio necesita dirección y puede reservar tiempo de traslado;
     * una cita en el salón no guarda ninguno de los dos.
     */
    protected function datosDomicilio(array $datos): array
    {
        $aDomicilio = (bool) ($datos['a_domicilio'] ?? false);
        $direccion = trim((string) ($datos['direccion_domicilio'] ?? ''));

        if ($aDomicilio && $direccion === '') {
            throw ValidationException::withMessages([
                'direccion_domicilio' => ['La dirección es obligatoria para una reserva a domicilio.'],
            ]);
        }

        return [
            'a_domicilio' => $aDomicilio,
            'direccion_domicilio' => $aDomicilio ? $direccion : null,
            'minutos_traslado' => $aDomicilio ? (int) ($datos['minutos_traslado'] ?? 0) : 0,
        ];
    }

    /**
     * Franjas del horario del salón para la fecha, ordenadas y con las contiguas unidas.
     * Vacío si el salón no abre ese día.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function franjasLocal(string $fecha): array
    {
        $diaDeLaSemana = Carbon::parse($fecha)->dayOfWeekIso;

        $horarios = HorarioAtencion::where('dia_semana', $diaDeLaSemana)
            ->orderBy('hora_apertura')
            ->get(['hora_apertura', 'hora_cierre']);

        $franjas = [];
        foreach ($horarios as $horario) {
            $franjas[] = [$horario->hora_apertura, $horario->hora_cierre];
        }

        return $this->unirFranjas($franjas);
    }

    protected function unirFranjas(array $franjas): array
    {
        $unidas = [];
        foreach ($franjas as [$inicio, $fin]) {
            $inicio = $this->normalizarHora($inicio);
            $fin = $this->normalizarHora($fin);
            $ultima = array_key_last($unidas);

            if ($ultima !== null && $inicio <= $unidas[$ultima][1]) {
                $unidas[$ultima][1] = max($unidas[$ultima][1], $fin);
            } else {
                $unidas[] = [$inicio, $fin];
            }
        }

        return $unidas;
    }

    protected function cabeEnFranjas(int $inicio, int $fin, array $franjas): bool
    {
        foreach ($franjas as [$desde, $hasta]) {
            if ($this->aMinutos($desde) <= $inicio && $this->aMinutos($hasta) >= $fin) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bloques en minutos que la profesional ya tiene ocupados ese día (con traslados incluidos).
     *
     * @return array<int, array{0: int, 1: int}>
     */
    protected function bloquesOcupados(int $personalId, string $fecha, ?int $excluirReservaId = null): array
    {
        $consulta = Reserva::where('personal_id', $personalId)
            ->where('fecha', $fecha)
            ->whereIn('estado', Reserva::ESTADOS_QUE_OCUPAN);

        if ($excluirReservaId !== null) {
            $consulta->where('id', '!=', $excluirReservaId);
        }

        $bloques = [];
        foreach ($consulta->get(['hora_inicio', 'hora_fin', 'minutos_traslado']) as $reserva) {
            $traslado = (int) $reserva->minutos_traslado;
            $bloques[] = [
                $this->aMinutos($reserva->hora_inicio) - $traslado,
                $this->aMinutos($reserva->hora_fin) + $traslado,
            ];
        }

        return $bloques;
    }

    protected function chocaConBloques(int $inicio, int $fin, array $bloques): bool
    {
        foreach ($bloques as [$desde, $hasta]) {
            if ($inicio < $hasta && $fin > $desde) {
                return true;
            }
        }

        return false;
    }

    protected function inicioDe(Reserva $reserva): Carbon
    {
        return Carbon::parse("{$reserva->fecha} {$reserva->hora_inicio}");
    }

    protected function aMinutos(string $hora): int
    {
        [$h, $m] = array_map('intval', explode(':', $hora));

        return $h * 60 + $m;
    }

    protected function aHora(int $minutos): string
    {
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }

    /**
     * Normalizar hora a formato H:i:s
     */
    protected function normalizarHora(string $hora): string
    {
        $hora = trim($hora);
        if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
            return $hora . ':00';
        }
        return $hora;
    }
}
