<?php

namespace App\Services;

use App\Models\DetalleReserva;
use App\Models\PrecioServicio;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\TurnoPersonal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservaService
{
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

            // Regla 1: No solapamiento de horarios para el profesional
            $this->validarSolapamiento($personalId, $fecha, $horaInicio, $horaFin);

            // Regla 2: Dentro del turno laboral del personal
            $this->validarTurnoPersonal($personalId, $fecha, $horaInicio, $horaFin);

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
            ]);

            // Insertar detalles asociados
            foreach ($detallesParaCrear as $detalle) {
                $reserva->detalles()->create($detalle);
            }

            return $reserva->load(['cliente', 'personal', 'detalles.servicio']);
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
        return DB::transaction(function () use ($reserva, $datos) {
            // Regla 5: Una reserva completada no puede pasar a ningún otro estado
            if ($reserva->estado === 'completada' && isset($datos['estado']) && $datos['estado'] !== 'completada') {
                throw ValidationException::withMessages([
                    'estado' => ['Una reserva en estado completada no puede cambiar a otro estado.'],
                ]);
            }

            $personalId = $datos['personal_id'] ?? $reserva->personal_id;
            $fecha = $datos['fecha'] ?? $reserva->fecha;
            $horaInicio = isset($datos['hora_inicio']) ? $this->normalizarHora($datos['hora_inicio']) : $reserva->hora_inicio;
            $horaFin = isset($datos['hora_fin']) ? $this->normalizarHora($datos['hora_fin']) : $reserva->hora_fin;

            $horarioCambiado = (
                (isset($datos['fecha']) && $datos['fecha'] !== $reserva->fecha) ||
                (isset($datos['hora_inicio']) && $this->normalizarHora($datos['hora_inicio']) !== $this->normalizarHora($reserva->hora_inicio)) ||
                (isset($datos['hora_fin']) && $this->normalizarHora($datos['hora_fin']) !== $this->normalizarHora($reserva->hora_fin)) ||
                (isset($datos['personal_id']) && $datos['personal_id'] !== $reserva->personal_id)
            );

            if ($horarioCambiado) {
                // Revalidar Regla 1 excluyendo la reserva actual
                $this->validarSolapamiento($personalId, $fecha, $horaInicio, $horaFin, $reserva->id);

                // Revalidar Regla 2
                $this->validarTurnoPersonal($personalId, $fecha, $horaInicio, $horaFin);
            }

            $camposActualizables = [
                'cliente_id', 'personal_id', 'fecha', 'hora_inicio', 'hora_fin', 'estado', 'descuento_id'
            ];

            $datosActualizar = array_intersect_key($datos, array_flip($camposActualizables));
            if (isset($datosActualizar['hora_inicio'])) {
                $datosActualizar['hora_inicio'] = $horaInicio;
            }
            if (isset($datosActualizar['hora_fin'])) {
                $datosActualizar['hora_fin'] = $horaFin;
            }

            $reserva->update($datosActualizar);

            return $reserva->fresh(['cliente', 'personal', 'detalles.servicio']);
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

            $reserva->update(['estado' => 'cancelada']);

            return $reserva->fresh();
        });
    }

    /**
     * Regla 1: Validar que el profesional no tenga reservas activas solapadas.
     * Condición: ($horaInicio < $reservaExistente->hora_fin) && ($horaFin > $reservaExistente->hora_inicio)
     */
    protected function validarSolapamiento(int $personalId, string $fecha, string $horaInicio, string $horaFin, ?int $excluirReservaId = null): void
    {
        $query = Reserva::where('personal_id', $personalId)
            ->where('fecha', $fecha)
            ->where('estado', '!=', 'cancelada')
            ->where('hora_inicio', '<', $horaFin)
            ->where('hora_fin', '>', $horaInicio);

        if ($excluirReservaId) {
            $query->where('id', '!=', $excluirReservaId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'horario' => ['El profesional ya cuenta con una reserva activa en el rango horario seleccionado.'],
            ]);
        }
    }

    /**
     * Regla 2: Validar que el horario esté dentro del turno laboral del personal.
     * diaOfWeekIso: 1=Lunes a 7=Domingo
     * Rango completo: turno.hora_inicio <= $horaInicio y turno.hora_fin >= $horaFin
     */
    protected function validarTurnoPersonal(int $personalId, string $fecha, string $horaInicio, string $horaFin): void
    {
        $diaSemana = Carbon::parse($fecha)->dayOfWeekIso;

        $turno = TurnoPersonal::where('personal_id', $personalId)
            ->where('dia_semana', $diaSemana)
            ->where('hora_inicio', '<=', $horaInicio)
            ->where('hora_fin', '>=', $horaFin)
            ->first();

        if (!$turno) {
            throw ValidationException::withMessages([
                'horario' => ['El horario seleccionado se encuentra fuera del turno laboral del personal asignado.'],
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
