<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\HistorialVisita;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Personal;
use App\Models\PreferenciaCliente;
use App\Models\Reserva;
use App\Models\Servicio;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Reservas de ejemplo alrededor de la fecha actual para que la agenda, el dashboard y los
 * reportes tengan datos en la demo: citas pasadas cerradas (con pagos e historial), citas de
 * hoy y próximas, en el salón y a domicilio.
 * Se inserta sin pasar por ReservaService porque incluye citas en el pasado, pero respeta
 * las Reglas 1 y 2 con los turnos que haya en la base.
 */
class ReservasDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Fátima Ramírez solo la crea este seeder: si existe, la demo ya estaba cargada
        if (Cliente::where('nombre', 'Fátima')->where('apellido', 'Ramírez')->exists()) {
            return;
        }

        $monica = Personal::where('email', 'monica@meninacoiffure.com')->first();
        $carla = Personal::where('email', 'carla@meninacoiffure.com')->first();
        $servicio = fn (string $nombre) => Servicio::where('nombre', $nombre)->where('activo', true)->first();

        $corte = $servicio('Corte de dama');
        $nutricion = $servicio('Nutrición e hidratación capilar');
        $color = $servicio('Coloración completa');
        $peinado = $servicio('Peinado para eventos');
        $maquillaje = $servicio('Maquillaje social');
        $depilacion = $servicio('Depilación facial');
        $caballero = $servicio('Corte de caballero');

        if (!$monica || !$carla || !$corte || !$nutricion || !$color || !$peinado || !$maquillaje || !$depilacion || !$caballero) {
            $this->command?->warn('ReservasDemoSeeder necesita el personal y el catálogo de AvanceDosSeeder.');
            return;
        }

        $clientes = collect([
            ['Ana', 'Gómez', '0981111222'],
            ['Beatriz', 'López', '0982333444'],
            ['Camila', 'Benítez', '0983555666'],
            ['Daniela', 'Ortiz', '0984777888'],
            ['Elena', 'Martínez', '0985999000'],
            ['Fátima', 'Ramírez', '0986121314'],
        ])->map(fn ($c) => Cliente::firstOrCreate(
            ['nombre' => $c[0], 'apellido' => $c[1]],
            ['telefono' => $c[2], 'fecha_registro' => now()->subMonths(2)->toDateString()]
        ));

        // HU-05: expediente con preferencias que la propietaria hoy recuerda de memoria
        PreferenciaCliente::firstOrCreate(['cliente_id' => $clientes[0]->id, 'categoria' => 'color'], ['descripcion' => 'Tinte castaño 6.3, raíz cada 5 semanas']);
        PreferenciaCliente::firstOrCreate(['cliente_id' => $clientes[2]->id, 'categoria' => 'alergia'], ['descripcion' => 'Sensibilidad a la parafenilendiamina: hacer prueba de mecha antes de colorear']);
        PreferenciaCliente::firstOrCreate(['cliente_id' => $clientes[3]->id, 'categoria' => 'profesional_preferido'], ['descripcion' => 'Prefiere que la atienda Carla']);

        $efectivo = MetodoPago::where('nombre', 'Efectivo')->first();
        $transferencia = MetodoPago::where('nombre', 'Transferencia bancaria')->first();

        // [días hábiles desde hoy, profesional, cliente, inicio, servicios, estado, minutos de traslado si es a domicilio]
        $agenda = [
            [-6, $monica, 0, '09:00', [$corte, $color], 'completada', null],
            [-6, $carla, 1, '10:00', [$peinado, $maquillaje], 'completada', 30],
            [-5, $monica, 2, '14:00', [$corte, $nutricion], 'completada', null],
            [-4, $carla, 3, '11:00', [$nutricion], 'no_asistio', null],
            [-3, $monica, 4, '15:00', [$depilacion], 'cancelada', null],
            [-2, $carla, 5, '09:00', [$corte], 'completada', null],
            [-1, $monica, 1, '10:30', [$caballero], 'completada', null],
            [0, $monica, 0, '09:00', [$nutricion], 'completada', null],
            [0, $carla, 2, '13:00', [$peinado], 'confirmada', null],
            [0, $monica, 3, '15:00', [$corte, $nutricion], 'pendiente', null],
            [1, $carla, 4, '09:30', [$maquillaje], 'confirmada', 20],
            [1, $monica, 5, '11:00', [$color], 'pendiente', null],
            [2, $monica, 2, '08:30', [$corte, $nutricion], 'confirmada', null],
            [3, $carla, 0, '14:00', [$peinado, $maquillaje], 'pendiente', 30],
            [5, $monica, 1, '16:00', [$depilacion], 'pendiente', null],
        ];

        foreach ($agenda as [$dias, $personal, $indiceCliente, $inicio, $servicios, $estado, $traslado]) {
            $fecha = $this->diaHabil($dias);
            $minutos = array_sum(array_map(fn (Servicio $s) => $s->duracion_minutos, $servicios));
            $horaInicio = "{$inicio}:00";
            $horaFin = Carbon::parse($inicio)->addMinutes($minutos)->format('H:i:s');
            $aDomicilio = $traslado !== null;

            if (!$this->cabe($personal, $fecha, $horaInicio, $horaFin, (int) $traslado)) {
                continue;
            }

            $reserva = Reserva::create([
                'cliente_id' => $clientes[$indiceCliente]->id,
                'personal_id' => $personal->id,
                'fecha' => $fecha->toDateString(),
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin,
                'estado' => $estado,
                'a_domicilio' => $aDomicilio,
                'direccion_domicilio' => $aDomicilio ? 'Calle Ejemplo 123 c/ Las Palmeras, Villa Elisa (portón verde)' : null,
                'minutos_traslado' => (int) $traslado,
            ]);

            foreach ($servicios as $servicioReservado) {
                $reserva->detalles()->create([
                    'servicio_id' => $servicioReservado->id,
                    'precio_aplicado' => $servicioReservado->precioVigente($fecha->toDateString())?->precio ?? 0,
                    'cantidad' => 1,
                    'agregado_en_el_momento' => false,
                ]);
            }

            $total = $reserva->load('detalles')->subtotal;

            // HU-20: las citas confirmadas a domicilio dejan seña; las completadas quedan pagadas
            if ($estado === 'completada') {
                if ($aDomicilio) {
                    $this->pagar($reserva, $transferencia, round($total * 0.3), $fecha->copy()->subDays(2), 'Seña');
                    $this->pagar($reserva, $efectivo, $total - round($total * 0.3), $fecha, 'Pago final');
                } else {
                    $this->pagar($reserva, $dias % 2 === 0 ? $efectivo : $transferencia, $total, $fecha, 'Pago de la atención');
                }

                HistorialVisita::create([
                    'reserva_id' => $reserva->id,
                    'notas' => $indiceCliente === 0 ? 'Retoque de raíz con 6.3; quedó conforme.' : null,
                    'fecha_realizada' => $fecha->toDateString(),
                ]);
            } elseif ($aDomicilio && $estado === 'confirmada') {
                $this->pagar($reserva, $transferencia, round($total * 0.3), now()->subDay(), 'Seña');
            }
        }
    }

    protected function pagar(Reserva $reserva, MetodoPago $metodo, float $monto, Carbon $fecha, string $descripcion): void
    {
        Pago::create([
            'reserva_id' => $reserva->id,
            'metodo_pago_id' => $metodo->id,
            'monto' => $monto,
            'fecha' => $fecha->copy()->setTimeFromTimeString('12:00'),
            'descripcion' => $descripcion,
        ]);
    }

    /**
     * Reglas 1 y 2: la cita entra en el turno y no choca con otra (traslados incluidos).
     */
    protected function cabe(Personal $personal, Carbon $fecha, string $horaInicio, string $horaFin, int $traslado): bool
    {
        $enTurno = $personal->turnos()
            ->where('dia_semana', $fecha->dayOfWeekIso)
            ->where('hora_inicio', '<=', $horaInicio)
            ->where('hora_fin', '>=', $horaFin)
            ->exists();

        $inicio = Carbon::parse($horaInicio)->subMinutes($traslado)->format('H:i:s');
        $fin = Carbon::parse($horaFin)->addMinutes($traslado)->format('H:i:s');

        $ocupado = Reserva::where('personal_id', $personal->id)
            ->where('fecha', $fecha->toDateString())
            ->whereIn('estado', Reserva::ESTADOS_QUE_OCUPAN)
            ->get(['hora_inicio', 'hora_fin', 'minutos_traslado'])
            ->contains(fn (Reserva $r) => $inicio < Carbon::parse($r->hora_fin)->addMinutes($r->minutos_traslado)->format('H:i:s')
                && $fin > Carbon::parse($r->hora_inicio)->subMinutes($r->minutos_traslado)->format('H:i:s'));

        return $enTurno && !$ocupado;
    }

    /**
     * Día hábil (lunes a viernes, como el horario del local) a cierta distancia de hoy.
     * Si hoy es fin de semana, el "día 0" es el próximo lunes.
     */
    protected function diaHabil(int $dias): Carbon
    {
        $fecha = now()->startOfDay();
        while ($fecha->isWeekend()) {
            $fecha->addDay();
        }

        $paso = $dias < 0 ? -1 : 1;
        for ($i = 0; $i < abs($dias); $i++) {
            do {
                $fecha->addDays($paso);
            } while ($fecha->isWeekend());
        }

        return $fecha;
    }
}
