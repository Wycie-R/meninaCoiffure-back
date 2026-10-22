<?php

namespace Database\Seeders;

use App\Models\HorarioAtencion;
use App\Models\Personal;
use App\Models\Servicio;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de volumen para el benchmark del Avance 4 (RPS y latencia, Arquitectura A vs B).
 * No se ejecuta con db:seed; se carga a pedido:
 *   docker compose exec app php artisan db:seed --class=VolumenReservasSeeder
 * Genera clientes y unas 5.000 reservas sin solapamientos, dentro del horario del salón.
 */
class VolumenReservasSeeder extends Seeder
{
    protected const CLIENTES = 300;
    protected const RESERVAS = 5000;
    protected const LOTE = 500;

    public function run(): void
    {
        $profesionales = Personal::where('activo', true)->get();
        $servicios = Servicio::where('activo', true)->get();
        $horarioDelSalon = HorarioAtencion::orderBy('hora_apertura')->get();

        if ($profesionales->isEmpty() || $servicios->isEmpty() || $horarioDelSalon->isEmpty()) {
            $this->command?->warn('Faltan personal activo, servicios activos o el horario del salón: ejecuta primero php artisan db:seed.');
            return;
        }

        if (DB::table('clientes')->where('apellido', 'like', '% (volumen %')->exists()) {
            $this->command?->warn('Los datos de volumen ya estaban cargados.');
            return;
        }

        $precios = $servicios->mapWithKeys(fn (Servicio $s) => [$s->id => (float) ($s->precioVigente()?->precio ?? 0)]);
        $clientes = $this->crearClientes();
        $ahora = now();

        // Días que el profesional ya tiene ocupados (reservas de demo): se saltan para no generar solapamientos
        $ocupados = DB::table('reservas')->select('personal_id', 'fecha')->distinct()->get()
            ->map(fn ($r) => $r->personal_id . '|' . substr($r->fecha, 0, 10))
            ->flip();

        // Recorre días hábiles hacia atrás desde dentro de un mes, llenando bloques de una hora por profesional
        $fecha = $ahora->copy()->addMonth()->startOfDay();
        $creadas = 0;
        $reservas = [];
        $lineas = [];

        while ($creadas < self::RESERVAS) {
            foreach ($profesionales as $personal) {
                if ($ocupados->has($personal->id . '|' . $fecha->toDateString())) {
                    continue;
                }

                foreach ($horarioDelSalon->where('dia_semana', $fecha->dayOfWeekIso) as $franja) {
                    $inicio = Carbon::parse($fecha->toDateString() . ' ' . $franja->hora_apertura);
                    $cierre = Carbon::parse($fecha->toDateString() . ' ' . $franja->hora_cierre);

                    for ($hora = $inicio->copy(); $hora->copy()->addHour() <= $cierre; $hora->addHour()) {
                        if ($creadas >= self::RESERVAS || random_int(1, 100) > 70) {
                            continue;
                        }

                        $servicio = $servicios->random();
                        $minutos = min(60, $servicio->duracion_minutos);
                        $pasada = $hora->lt($ahora);
                        $id = count($reservas);

                        $reservas[] = [
                            'cliente_id' => $clientes[array_rand($clientes)],
                            'personal_id' => $personal->id,
                            'fecha' => $fecha->toDateString(),
                            'hora_inicio' => $hora->format('H:i:s'),
                            'hora_fin' => $hora->copy()->addMinutes($minutos)->format('H:i:s'),
                            'estado' => $this->estado($pasada),
                            'a_domicilio' => random_int(1, 100) <= 10,
                            'created_at' => $ahora,
                            'updated_at' => $ahora,
                        ];
                        $lineas[$id] = [$servicio->id, $precios[$servicio->id]];
                        $creadas++;

                        if (count($reservas) === self::LOTE) {
                            $this->insertar($reservas, $lineas);
                            $reservas = [];
                            $lineas = [];
                        }
                    }
                }
            }

            $fecha->subDay();
        }

        $this->insertar($reservas, $lineas);

        // Sin estadísticas nuevas el planificador estima mal y los reportes tardan ~1,4 s
        // hasta el primer autovacuum: el benchmark mediría ese plan y no el real
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ANALYZE');
        }

        $this->command?->info("Se crearon {$creadas} reservas de volumen.");
    }

    protected function crearClientes(): array
    {
        $nombres = ['Ana', 'Beatriz', 'Camila', 'Daniela', 'Elena', 'Fátima', 'Gabriela', 'Helena', 'Irene', 'Julia', 'Karen', 'Lorena', 'María', 'Natalia', 'Olga', 'Paula', 'Rocío', 'Sofía', 'Tamara', 'Valeria'];
        $apellidos = ['Acosta', 'Benítez', 'Cabrera', 'Duarte', 'Escobar', 'Fernández', 'Giménez', 'Insfrán', 'Jara', 'López', 'Martínez', 'Núñez', 'Ortiz', 'Paredes', 'Ramírez', 'Sanabria', 'Torres', 'Vera'];
        $ahora = now();

        $filas = [];
        for ($i = 1; $i <= self::CLIENTES; $i++) {
            $filas[] = [
                'nombre' => $nombres[array_rand($nombres)],
                'apellido' => $apellidos[array_rand($apellidos)] . " (volumen {$i})",
                'telefono' => sprintf('0981%06d', $i),
                'fecha_registro' => $ahora->toDateString(),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        DB::table('clientes')->insert($filas);

        return DB::table('clientes')->where('apellido', 'like', '% (volumen %')->pluck('id')->all();
    }

    protected function estado(bool $pasada): string
    {
        $azar = random_int(1, 100);

        if ($pasada) {
            return $azar <= 85 ? 'completada' : ($azar <= 93 ? 'cancelada' : 'no_asistio');
        }

        return $azar <= 60 ? 'pendiente' : ($azar <= 90 ? 'confirmada' : 'cancelada');
    }

    /**
     * Inserta un lote de reservas y sus líneas de detalle en dos consultas.
     */
    protected function insertar(array $reservas, array $lineas): void
    {
        if (!$reservas) {
            return;
        }

        DB::transaction(function () use ($reservas, $lineas) {
            foreach ($reservas as $i => &$reserva) {
                if ($reserva['a_domicilio']) {
                    $reserva['direccion_domicilio'] = 'Dirección de prueba ' . ($i + 1) . ', Villa Elisa';
                } else {
                    $reserva['direccion_domicilio'] = null;
                }
            }
            unset($reserva);

            $ids = [];
            foreach ($reservas as $reserva) {
                $ids[] = DB::table('reservas')->insertGetId($reserva);
            }

            $ahora = now();
            $detalles = [];
            foreach ($ids as $i => $id) {
                [$servicioId, $precio] = $lineas[$i];
                $detalles[] = [
                    'reserva_id' => $id,
                    'servicio_id' => $servicioId,
                    'precio_aplicado' => $precio,
                    'cantidad' => 1,
                    'agregado_en_el_momento' => false,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }

            DB::table('detalle_reserva')->insert($detalles);
        });
    }
}
