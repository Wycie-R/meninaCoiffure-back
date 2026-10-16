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
use App\Models\TurnoPersonal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservaService
{
    /** Relaciones que acompañan a una reserva en las respuestas de la API. */
    public const RELACIONES = ['cliente', 'personal', 'detalles.servicio', 'descuento', 'pagos'];

    /**
     * Minutos de tolerancia para registrar una cita que ya empezó: clientes que llegan
     * sin turno y se atienden por orden de llegada.
     */
    public const TOLERANCIA_SIN_TURNO = 30;

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

            // Bloquea al profesional hasta el fin de la transacción: dos pedidos simultáneos
            // (por ejemplo, en nodos distintos detrás de HAProxy) no pueden pasar ambos la Regla 1
            $this->validarPersonalActivo($personalId, bloquear: true);
            $this->validarHorarioFuturo($fecha, $horaInicio);

            // Regla 1: No solapamiento de horarios para el profesional (con el traslado si es a domicilio)
            $this->validarSolapamiento($personalId, $fecha, $horaInicio, $horaFin, $domicilio['minutos_traslado']);

            // Regla 2: Dentro del turno laboral del personal y, en el salón, del horario del local
            $this->validarTurnoPersonal($personalId, $fecha, $horaInicio, $horaFin);
            if (!$domicilio['a_domicilio']) {
                $this->validarHorarioLocal($fecha, $horaInicio, $horaFin);
            }

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

            $horarioCambiado = (
                (isset($datos['fecha']) && $datos['fecha'] !== $reserva->fecha) ||
                (isset($datos['hora_inicio']) && $this->normalizarHora($datos['hora_inicio']) !== $this->normalizarHora($reserva->hora_inicio)) ||
                (isset($datos['hora_fin']) && $this->normalizarHora($datos['hora_fin']) !== $this->normalizarHora($reserva->hora_fin)) ||
                (isset($datos['personal_id']) && $datos['personal_id'] !== $reserva->personal_id) ||
                $domicilio['minutos_traslado'] !== $reserva->minutos_traslado ||
                $domicilio['a_domicilio'] !== $reserva->a_domicilio
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
                $this->validarTurnoPersonal($personalId, $fecha, $horaInicio, $horaFin);
                if (!$domicilio['a_domicilio']) {
                    $this->validarHorarioLocal($fecha, $horaInicio, $horaFin);
                }
            }

            $camposActualizables = [
                'cliente_id', 'personal_id', 'fecha', 'hora_inicio', 'hora_fin', 'estado', 'descuento_id',
            ];

            $datosActualizar = array_intersect_key($datos, array_flip($camposActualizables));
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

            // HU-13 y HU-16: al completar, la visita pasa al historial y se descuentan los insumos
            if ($nuevoEstado === 'completada' && $estadoAnterior !== 'completada') {
                ReservaCompletada::dispatch($reserva->fresh(), $datos['notas'] ?? null);
            }

            return $reserva->fresh(self::RELACIONES);
        });
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
     * HU-02: Horarios en los que un profesional puede recibir una cita de cierta duración.
     * Recorre las franjas del turno (unidas) cada $intervalo minutos y marca cada inicio
     * como disponible, ocupado (Regla 1, con traslados) o pasado.
     *
     * @return array{franjas: array<int, array{0: string, 1: string}>, horarios: array<int, array{hora: string, disponible: bool, motivo: ?string}>}
     */
    public function horariosDisponibles(int $personalId, string $fecha, int $duracion, bool $aDomicilio = false, int $traslado = 0, int $intervalo = 30): array
    {
        $traslado = $aDomicilio ? $traslado : 0;
        $franjas = $this->franjasDelDia($personalId, $fecha);

        // En el salón, la cita además debe caber en el horario del local
        $local = $aDomicilio ? null : $this->franjasLocal($fecha);

        $ocupados = $this->bloquesOcupados($personalId, $fecha);
        $limitePasado = now()->subMinutes(self::TOLERANCIA_SIN_TURNO);
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
                } elseif ($local !== null && !$this->cabeEnFranjas($inicio, $fin, $local)) {
                    $motivo = 'local_cerrado';
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
     * a quien llega sin turno y se atiende por orden de llegada.
     */
    protected function validarHorarioFuturo(string $fecha, string $horaInicio): void
    {
        if (Carbon::parse("{$fecha} {$horaInicio}")->lt(now()->subMinutes(self::TOLERANCIA_SIN_TURNO))) {
            throw ValidationException::withMessages([
                'horario' => ['El horario seleccionado ya pasó. Elige un horario posterior a la hora actual.'],
            ]);
        }
    }

    /**
     * Regla 1: Validar que el profesional no tenga reservas activas solapadas.
     * Una cita a domicilio ocupa también su tiempo de traslado antes y después,
     * así que se comparan bloques [inicio - traslado, fin + traslado].
     */
    protected function validarSolapamiento(int $personalId, string $fecha, string $horaInicio, string $horaFin, int $traslado = 0, ?int $excluirReservaId = null): void
    {
        $bloques = $this->bloquesOcupados($personalId, $fecha, $excluirReservaId);
        $inicio = $this->aMinutos($horaInicio) - $traslado;
        $fin = $this->aMinutos($horaFin) + $traslado;

        if ($this->chocaConBloques($inicio, $fin, $bloques)) {
            throw ValidationException::withMessages([
                'horario' => ['El profesional ya cuenta con una reserva activa en el rango horario seleccionado.'],
            ]);
        }
    }

    /**
     * Regla 2: Validar que el horario esté dentro del turno laboral del personal.
     * diaOfWeekIso: 1=Lunes a 7=Domingo
     * Las franjas contiguas del mismo día se unen (09:00-11:00 + 11:00-13:00 = 09:00-13:00),
     * y la cita debe quedar completa dentro de una de esas franjas unidas.
     */
    protected function validarTurnoPersonal(int $personalId, string $fecha, string $horaInicio, string $horaFin): void
    {
        $franjas = $this->franjasDelDia($personalId, $fecha);

        if (!$this->cabeEnFranjas($this->aMinutos($horaInicio), $this->aMinutos($horaFin), $franjas)) {
            throw ValidationException::withMessages([
                'horario' => ['El horario seleccionado se encuentra fuera del turno laboral del personal asignado.'],
            ]);
        }
    }

    /**
     * Regla 2 (local): una cita en el salón debe caber en el horario de atención del local.
     * Si el horario del local no está configurado, no se aplica.
     */
    protected function validarHorarioLocal(string $fecha, string $horaInicio, string $horaFin): void
    {
        $franjas = $this->franjasLocal($fecha);

        if ($franjas !== null && !$this->cabeEnFranjas($this->aMinutos($horaInicio), $this->aMinutos($horaFin), $franjas)) {
            throw ValidationException::withMessages([
                'horario' => ['El salón está cerrado en ese horario. Para atender fuera del horario del local, registra la cita a domicilio.'],
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
     * Franjas del turno del profesional para el día de la fecha, ordenadas y con las contiguas unidas.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    protected function franjasDelDia(int $personalId, string $fecha): array
    {
        $franjas = TurnoPersonal::where('personal_id', $personalId)
            ->where('dia_semana', Carbon::parse($fecha)->dayOfWeekIso)
            ->orderBy('hora_inicio')
            ->get(['hora_inicio', 'hora_fin'])
            ->map(fn ($t) => [$t->hora_inicio, $t->hora_fin])
            ->all();

        return $this->unirFranjas($franjas);
    }

    /**
     * Franjas del horario del local para la fecha; null si el horario del local no está configurado.
     *
     * @return array<int, array{0: string, 1: string}>|null
     */
    protected function franjasLocal(string $fecha): ?array
    {
        if (!HorarioAtencion::exists()) {
            return null;
        }

        $franjas = HorarioAtencion::where('dia_semana', Carbon::parse($fecha)->dayOfWeekIso)
            ->orderBy('hora_apertura')
            ->get(['hora_apertura', 'hora_cierre'])
            ->map(fn ($h) => [$h->hora_apertura, $h->hora_cierre])
            ->all();

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
     * Bloques en minutos que el profesional ya tiene ocupados ese día (con traslados incluidos).
     *
     * @return array<int, array{0: int, 1: int}>
     */
    protected function bloquesOcupados(int $personalId, string $fecha, ?int $excluirReservaId = null): array
    {
        return Reserva::where('personal_id', $personalId)
            ->where('fecha', $fecha)
            ->whereIn('estado', Reserva::ESTADOS_QUE_OCUPAN)
            ->when($excluirReservaId, fn ($q) => $q->where('id', '!=', $excluirReservaId))
            ->get(['hora_inicio', 'hora_fin', 'minutos_traslado'])
            ->map(fn (Reserva $r) => [
                $this->aMinutos($r->hora_inicio) - (int) $r->minutos_traslado,
                $this->aMinutos($r->hora_fin) + (int) $r->minutos_traslado,
            ])
            ->all();
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
