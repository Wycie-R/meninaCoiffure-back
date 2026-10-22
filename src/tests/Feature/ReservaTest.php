<?php

namespace Tests\Feature;

use App\Events\ReservaCompletada;
use App\Models\Cliente;
use App\Models\DetalleReserva;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Personal;
use App\Models\PrecioServicio;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\Descuento;
use App\Models\Role;
use App\Models\HorarioAtencion;
use App\Models\User;
use App\Services\ReservaService;
use Carbon\Carbon;
use Database\Seeders\AvanceDosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReservaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ejecutar los seeders del módulo para contar con el personal, el horario del salón y los servicios base
        $this->seed(AvanceDosSeeder::class);
        $this->actingAs(User::factory()->create(['rol_id' => Role::firstOrCreate(['nombre' => 'admin'])->id]));
    }

    /**
     * Helper para obtener una fecha en el próximo lunes (garantiza día laboral Lunes = 1 y fecha futura).
     */
    protected function obtenerProximoLunes(): string
    {
        return Carbon::parse('next monday')->toDateString();
    }

    /**
     * Helper para obtener el próximo domingo (día en que el salón no atiende).
     */
    protected function obtenerProximoDomingo(): string
    {
        return Carbon::parse('next sunday')->toDateString();
    }

    /**
     * Test 1: Creación exitosa de una reserva con detalles y precio congelado (Reglas 3 y 4).
     */
    public function test_creacion_exitosa_de_reserva_con_detalles_y_precio_congelado(): void
    {
        $fecha = $this->obtenerProximoLunes();

        $payload = [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fecha,
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'servicios' => [1, 2], // 1 = Corte de dama ($70000), 2 = Nutrición ($50000)
        ];

        $response = $this->postJson('/api/reservas', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Reserva creada exitosamente.')
            ->assertJsonPath('data.cliente_id', 1)
            ->assertJsonPath('data.personal_id', 1)
            ->assertJsonPath('data.estado', 'pendiente');

        $reservaId = $response->json('data.id');

        $this->assertDatabaseHas('reservas', [
            'id' => $reservaId,
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fecha,
            'estado' => 'pendiente',
        ]);

        // Verificar Regla 4: precio congelado en detalle_reserva
        $this->assertDatabaseHas('detalle_reserva', [
            'reserva_id' => $reservaId,
            'servicio_id' => 1,
            'precio_aplicado' => 70000.00,
            'agregado_en_el_momento' => false,
        ]);

        $this->assertDatabaseHas('detalle_reserva', [
            'reserva_id' => $reservaId,
            'servicio_id' => 2,
            'precio_aplicado' => 50000.00,
            'agregado_en_el_momento' => false,
        ]);
    }

    /**
     * Test 2: Falla por solapamiento de horarios (Regla 1).
     */
    public function test_falla_por_solapamiento_de_horarios_regla_1(): void
    {
        $fecha = $this->obtenerProximoLunes();

        // 1. Crear primera reserva de 10:00 a 11:30
        $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fecha,
            'hora_inicio' => '10:00',
            'hora_fin' => '11:30',
            'servicios' => [1],
        ])->assertStatus(201);

        // 2. Intentar crear segunda reserva que se cruza (11:00 a 12:00) con el mismo personal
        $solapadaResponse = $this->postJson('/api/reservas', [
            'cliente_id' => 2,
            'personal_id' => 1,
            'fecha' => $fecha,
            'hora_inicio' => '11:00',
            'hora_fin' => '12:00',
            'servicios' => [2],
        ]);

        $solapadaResponse->assertStatus(422)
            ->assertJsonValidationErrors(['horario']);

        // 3. Verificar que si es con OTRO personal (Carla = id 2), sí se permite en el mismo horario
        $otroPersonalResponse = $this->postJson('/api/reservas', [
            'cliente_id' => 2,
            'personal_id' => 2,
            'fecha' => $fecha,
            'hora_inicio' => '11:00',
            'hora_fin' => '12:00',
            'servicios' => [2],
        ]);
        $otroPersonalResponse->assertStatus(201);
    }

    /**
     * Test 3: Falla por horario fuera del horario del salón (Regla 2).
     */
    public function test_falla_fuera_del_horario_del_salon_regla_2(): void
    {
        $fechaLunes = $this->obtenerProximoLunes();
        $fechaDomingo = $this->obtenerProximoDomingo();

        // El salón atiende de lunes a viernes de 08:00 a 17:00.
        // Caso A: Lunes antes de que abra el salón (06:30 a 07:30)
        $antesDeAbrirResponse = $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fechaLunes,
            'hora_inicio' => '06:30',
            'hora_fin' => '07:30',
            'servicios' => [1],
        ]);

        $antesDeAbrirResponse->assertStatus(422)
            ->assertJsonValidationErrors(['horario']);

        // Caso B: Lunes después del cierre (18:00 a 19:00)
        $despuesDelCierreResponse = $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fechaLunes,
            'hora_inicio' => '18:00',
            'hora_fin' => '19:00',
            'servicios' => [1],
        ]);

        $despuesDelCierreResponse->assertStatus(422)
            ->assertJsonValidationErrors(['horario']);

        // Caso C: Domingo (el salón no atiende)
        $domingoResponse = $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fechaDomingo,
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'servicios' => [1],
        ]);

        $domingoResponse->assertStatus(422)
            ->assertJsonValidationErrors(['horario']);
    }

    /**
     * Test 4: Falla por intentar agregar un servicio inactivo (Regla 3).
     */
    public function test_falla_por_intentar_agregar_servicio_inactivo_regla_3(): void
    {
        $fecha = $this->obtenerProximoLunes();
        // Servicio 4 es "Decoloración Extrema (Descontinuado)" con activo = false en el seeder
        $inactivoResponse = $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fecha,
            'hora_inicio' => '14:00',
            'hora_fin' => '15:00',
            'servicios' => [4],
        ]);

        $inactivoResponse->assertStatus(422)
            ->assertJsonValidationErrors(['servicio_id']);

        // Crear una reserva válida y luego intentar agregar el servicio inactivo en el momento
        $creada = $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fecha,
            'hora_inicio' => '15:00',
            'hora_fin' => '16:00',
            'servicios' => [1],
        ])->assertStatus(201);

        $reservaId = $creada->json('data.id');

        $agregarInactivoResponse = $this->postJson("/api/reservas/{$reservaId}/servicios", [
            'servicio_id' => 4,
            'cantidad' => 1,
        ]);

        $agregarInactivoResponse->assertStatus(422)
            ->assertJsonValidationErrors(['servicio_id']);
    }

    /**
     * Test 5: Falla por intentar cancelar una reserva completada (Regla 5).
     */
    public function test_falla_por_intentar_cancelar_reserva_completada_regla_5(): void
    {
        $fecha = $this->obtenerProximoLunes();

        // 1. Crear reserva
        $creada = $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fecha,
            'hora_inicio' => '16:00',
            'hora_fin' => '17:00',
            'servicios' => [1],
        ])->assertStatus(201);

        $reservaId = $creada->json('data.id');

        // Marcar la reserva como 'completada' directamente en la base de datos
        Reserva::where('id', $reservaId)->update(['estado' => 'completada']);

        // 2. Intentar cancelar vía DELETE (destroy)
        $cancelarResponse = $this->deleteJson("/api/reservas/{$reservaId}");

        $cancelarResponse->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);

        // 3. Intentar cambiar de estado vía PUT a 'cancelada' o 'pendiente'
        $updateResponse = $this->putJson("/api/reservas/{$reservaId}", [
            'estado' => 'cancelada',
        ]);

        $updateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);

        // Verificar que en la base de datos continúa en 'completada'
        $this->assertDatabaseHas('reservas', [
            'id' => $reservaId,
            'estado' => 'completada',
        ]);
    }

    /**
     * Test 6: Cancelación exitosa de una reserva pendiente (sin borrar registro).
     */
    public function test_cancelacion_exitosa_de_reserva_no_completada(): void
    {
        $fecha = $this->obtenerProximoLunes();

        $creada = $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fecha,
            'hora_inicio' => '12:00',
            'hora_fin' => '13:00',
            'servicios' => [1],
        ])->assertStatus(201);

        $reservaId = $creada->json('data.id');

        $response = $this->deleteJson("/api/reservas/{$reservaId}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Reserva cancelada exitosamente.')
            ->assertJsonPath('data.estado', 'cancelada');

        // Verificar que el registro existe pero con estado 'cancelada'
        $this->assertDatabaseHas('reservas', [
            'id' => $reservaId,
            'estado' => 'cancelada',
        ]);
    }

    /**
     * Test 7: Agregar servicio en el momento con precio congelado (agregado_en_el_momento = true).
     */
    public function test_agregar_servicio_en_el_momento_con_precio_congelado(): void
    {
        $fecha = $this->obtenerProximoLunes();

        $creada = $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fecha,
            'hora_inicio' => '13:00',
            'hora_fin' => '14:00',
            'servicios' => [1],
        ])->assertStatus(201);

        $reservaId = $creada->json('data.id');

        // Agregar Servicio 2 (Baño de Crema, $50000) en el momento
        $response = $this->postJson("/api/reservas/{$reservaId}/servicios", [
            'servicio_id' => 2,
            'cantidad' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Servicio agregado a la reserva exitosamente.')
            ->assertJsonPath('data.reserva_id', $reservaId)
            ->assertJsonPath('data.servicio_id', 2)
            ->assertJsonPath('data.cantidad', 2)
            ->assertJsonPath('data.agregado_en_el_momento', true);

        $this->assertEquals(50000.00, (float) $response->json('data.precio_aplicado'));

        $this->assertDatabaseHas('detalle_reserva', [
            'reserva_id' => $reservaId,
            'servicio_id' => 2,
            'precio_aplicado' => 50000.00,
            'cantidad' => 2,
            'agregado_en_el_momento' => true,
        ]);
    }

    /**
     * Test 8: Falla al intentar cancelar una reserva que ya está cancelada.
     */
    public function test_falla_por_intentar_re_cancelar_reserva_ya_cancelada(): void
    {
        $fecha = $this->obtenerProximoLunes();

        $creada = $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fecha,
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'servicios' => [1],
        ])->assertStatus(201);

        $reservaId = $creada->json('data.id');

        // Primera cancelación (exitosa)
        $this->deleteJson("/api/reservas/{$reservaId}")
            ->assertStatus(200);

        // Segunda cancelación (debe fallar con 422)
        $reintentar = $this->deleteJson("/api/reservas/{$reservaId}");
        $reintentar->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);
    }

    /**
     * Test 9: Falla al intentar modificar o reactivar una reserva cancelada vía PUT.
     */
    public function test_falla_por_intentar_modificar_reserva_cancelada(): void
    {
        $fecha = $this->obtenerProximoLunes();

        $creada = $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fecha,
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'servicios' => [1],
        ])->assertStatus(201);

        $reservaId = $creada->json('data.id');

        $this->deleteJson("/api/reservas/{$reservaId}")
            ->assertStatus(200);

        // Intentar reactivarla a confirmada
        $updateResponse = $this->putJson("/api/reservas/{$reservaId}", [
            'estado' => 'confirmada',
        ]);

        $updateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);
    }

    /**
     * Test 10: Falla al intentar agregar servicios a una reserva cancelada.
     */
    public function test_falla_por_intentar_agregar_servicios_a_reserva_cancelada(): void
    {
        $fecha = $this->obtenerProximoLunes();

        $creada = $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fecha,
            'hora_inicio' => '11:00',
            'hora_fin' => '12:00',
            'servicios' => [1],
        ])->assertStatus(201);

        $reservaId = $creada->json('data.id');

        $this->deleteJson("/api/reservas/{$reservaId}")
            ->assertStatus(200);

        // Intentar agregar servicio a la reserva cancelada
        $response = $this->postJson("/api/reservas/{$reservaId}/servicios", [
            'servicio_id' => 2,
            'cantidad' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);
    }

    /**
     * Test 11: Falla al intentar cancelar una reserva cuya cita ya transcurrió en el pasado.
     */
    public function test_falla_por_intentar_cancelar_reserva_en_el_pasado(): void
    {
        // Insertar directamente una reserva con fecha anterior
        $reservaPasada = Reserva::create([
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => Carbon::yesterday()->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado' => 'pendiente',
        ]);

        $response = $this->deleteJson("/api/reservas/{$reservaPasada->id}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);
    }

    /**
     * Helper: payload mínimo válido para el próximo lunes con Carla.
     */
    protected function payload(array $cambios = []): array
    {
        return [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $this->obtenerProximoLunes(),
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'servicios' => [1],
            ...$cambios,
        ];
    }

    /**
     * Helper: reserva insertada directamente (por ejemplo, en el pasado).
     */
    protected function reservaDirecta(string $fecha, string $estado = 'pendiente'): Reserva
    {
        return Reserva::create([
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fecha,
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado' => $estado,
        ]);
    }

    /**
     * HU-14: Reserva a domicilio exige dirección; en el salón no se guarda dirección.
     */
    public function test_reserva_a_domicilio_exige_direccion(): void
    {
        $this->postJson('/api/reservas', $this->payload(['a_domicilio' => true]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['direccion_domicilio']);

        $direccion = 'Calle Ejemplo 123 c/ Las Palmeras, Villa Elisa';
        $creada = $this->postJson('/api/reservas', $this->payload(['a_domicilio' => true, 'direccion_domicilio' => $direccion]))
            ->assertStatus(201)
            ->assertJsonPath('data.a_domicilio', true)
            ->assertJsonPath('data.direccion_domicilio', $direccion);

        $this->assertDatabaseHas('reservas', ['id' => $creada->json('data.id'), 'a_domicilio' => true, 'direccion_domicilio' => $direccion]);

        $enSalon = $this->postJson('/api/reservas', $this->payload([
            'hora_inicio' => '14:00',
            'hora_fin' => '15:00',
            'direccion_domicilio' => 'No debería guardarse',
        ]))->assertStatus(201)
            ->assertJsonPath('data.a_domicilio', false)
            ->assertJsonPath('data.direccion_domicilio', null);

        $this->assertDatabaseHas('reservas', ['id' => $enSalon->json('data.id'), 'direccion_domicilio' => null]);
    }

    /**
     * Transiciones de estado: completar o marcar no asistida solo tras el inicio; los estados finales no cambian.
     */
    public function test_transiciones_de_estado_respetan_el_momento_de_la_cita(): void
    {
        $futura = $this->postJson('/api/reservas', $this->payload())->assertStatus(201)->json('data.id');

        $this->putJson("/api/reservas/{$futura}", ['estado' => 'completada'])
            ->assertStatus(422)->assertJsonValidationErrors(['estado']);
        $this->putJson("/api/reservas/{$futura}", ['estado' => 'no_asistio'])
            ->assertStatus(422)->assertJsonValidationErrors(['estado']);
        $this->putJson("/api/reservas/{$futura}", ['estado' => 'confirmada'])
            ->assertStatus(200)->assertJsonPath('data.estado', 'confirmada');
        $this->putJson("/api/reservas/{$futura}", ['estado' => 'pendiente'])
            ->assertStatus(422)->assertJsonValidationErrors(['estado']);

        $ausente = $this->reservaDirecta(Carbon::yesterday()->toDateString());
        $this->putJson("/api/reservas/{$ausente->id}", ['estado' => 'cancelada'])
            ->assertStatus(422)->assertJsonValidationErrors(['estado']);
        $this->putJson("/api/reservas/{$ausente->id}", ['estado' => 'no_asistio'])
            ->assertStatus(200)->assertJsonPath('data.estado', 'no_asistio');
        $this->putJson("/api/reservas/{$ausente->id}", ['estado' => 'completada'])
            ->assertStatus(422)->assertJsonValidationErrors(['estado']);

        $atendida = $this->reservaDirecta(Carbon::yesterday()->subDay()->toDateString());
        $this->putJson("/api/reservas/{$atendida->id}", ['estado' => 'completada'])
            ->assertStatus(200)->assertJsonPath('data.estado', 'completada');
    }

    /**
     * Una reserva nueva no puede nacer completada ni cancelada.
     */
    public function test_reserva_nueva_solo_nace_pendiente_o_confirmada(): void
    {
        $this->postJson('/api/reservas', $this->payload(['estado' => 'completada']))
            ->assertStatus(422)->assertJsonValidationErrors(['estado']);

        $this->postJson('/api/reservas', $this->payload(['estado' => 'confirmada']))
            ->assertStatus(201)->assertJsonPath('data.estado', 'confirmada');
    }

    /**
     * No se reserva con personal inactivo ni en un horario que ya pasó.
     */
    public function test_rechaza_personal_inactivo_y_horario_pasado(): void
    {
        Personal::where('id', 2)->update(['activo' => false]);

        $this->postJson('/api/reservas', $this->payload(['personal_id' => 2]))
            ->assertStatus(422)->assertJsonValidationErrors(['personal_id']);

        // Lunes a las 10:00: las 09:00 ya pasaron; las 09:45 entran en la tolerancia para quien llega sin cita
        $lunes = $this->obtenerProximoLunes();
        $this->travelTo(Carbon::parse("{$lunes} 10:00"));

        $respuesta = $this->postJson('/api/reservas', $this->payload([
            'fecha' => $lunes,
            'hora_inicio' => '09:00',
            'hora_fin' => '09:45',
        ]))->assertStatus(422)->assertJsonValidationErrors(['horario']);

        $this->assertStringContainsString('ya pasó', $respuesta->json('errors.horario.0'));

        $this->postJson('/api/reservas', $this->payload([
            'fecha' => $lunes,
            'hora_inicio' => '09:45',
            'hora_fin' => '10:30',
        ]))->assertStatus(201);
    }

    /**
     * HU-14: el traslado de una cita a domicilio también ocupa la agenda de la profesional.
     */
    public function test_traslado_a_domicilio_ocupa_la_agenda(): void
    {
        $this->postJson('/api/reservas', $this->payload([
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'a_domicilio' => true,
            'direccion_domicilio' => 'Calle Ejemplo 123, Villa Elisa',
            'minutos_traslado' => 30,
        ]))->assertStatus(201)->assertJsonPath('data.minutos_traslado', 30);

        // 11:15 cae dentro de la vuelta del traslado (hasta las 11:30)
        $this->postJson('/api/reservas', $this->payload(['hora_inicio' => '11:15', 'hora_fin' => '12:00']))
            ->assertStatus(422)->assertJsonValidationErrors(['horario']);

        // 09:00 a 09:45 se cruza con la ida del traslado (desde las 09:30)
        $this->postJson('/api/reservas', $this->payload(['hora_inicio' => '09:00', 'hora_fin' => '09:45']))
            ->assertStatus(422)->assertJsonValidationErrors(['horario']);

        $this->postJson('/api/reservas', $this->payload(['hora_inicio' => '11:30', 'hora_fin' => '12:15']))
            ->assertStatus(201);

        // En el salón el traslado se ignora
        $this->postJson('/api/reservas', $this->payload([
            'personal_id' => 2, 'minutos_traslado' => 45,
        ]))->assertStatus(201)->assertJsonPath('data.minutos_traslado', 0);
    }

    /**
     * HU-14: el traslado de la cita nueva tampoco puede pisar otra cita ya agendada.
     */
    public function test_traslado_de_la_cita_nueva_choca_con_las_existentes(): void
    {
        $this->postJson('/api/reservas', $this->payload(['hora_inicio' => '12:00', 'hora_fin' => '13:00']))
            ->assertStatus(201);

        $aDomicilio = ['a_domicilio' => true, 'direccion_domicilio' => 'Calle Ejemplo 123, Villa Elisa'];

        // 13:30 a 14:30 con 30 min de traslado: sale a las 13:00, justo cuando termina la cita de las 12:00
        $this->postJson('/api/reservas', $this->payload([...$aDomicilio, 'hora_inicio' => '13:30', 'hora_fin' => '14:30', 'minutos_traslado' => 30]))
            ->assertStatus(201);

        // 10:30 a 11:30 con 60 min de traslado: la vuelta llega hasta las 12:30 y pisa la cita de las 12:00
        $this->postJson('/api/reservas', $this->payload([...$aDomicilio, 'hora_inicio' => '10:30', 'hora_fin' => '11:30', 'minutos_traslado' => 60]))
            ->assertStatus(422)->assertJsonValidationErrors(['horario']);
    }

    /**
     * HU-14: el viaje puede empezar antes de abrir; lo que debe caber en el horario es la cita.
     */
    public function test_el_traslado_puede_quedar_fuera_del_horario_del_salon(): void
    {
        $this->postJson('/api/reservas', $this->payload([
            'hora_inicio' => '08:00',
            'hora_fin' => '09:00',
            'a_domicilio' => true,
            'direccion_domicilio' => 'Calle Ejemplo 123, Villa Elisa',
            'minutos_traslado' => 45,
        ]))->assertStatus(201);
    }

    /**
     * HU-14: cambiar el traslado de una reserva revalida la agenda.
     */
    public function test_ampliar_el_traslado_revalida_el_solapamiento(): void
    {
        $id = $this->postJson('/api/reservas', $this->payload([
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'a_domicilio' => true,
            'direccion_domicilio' => 'Calle Ejemplo 123, Villa Elisa',
            'minutos_traslado' => 15,
        ]))->assertStatus(201)->json('data.id');

        $this->postJson('/api/reservas', $this->payload(['hora_inicio' => '11:30', 'hora_fin' => '12:00']))
            ->assertStatus(201);

        $this->putJson("/api/reservas/{$id}", ['minutos_traslado' => 60])
            ->assertStatus(422)->assertJsonValidationErrors(['horario']);
        $this->putJson("/api/reservas/{$id}", ['minutos_traslado' => 30])
            ->assertStatus(200)->assertJsonPath('data.minutos_traslado', 30);
    }

    /**
     * HU-02: la disponibilidad a domicilio descuenta el traslado de la cita nueva.
     */
    public function test_disponibilidad_a_domicilio_considera_el_traslado(): void
    {
        $lunes = $this->obtenerProximoLunes();
        $this->postJson('/api/reservas', $this->payload(['hora_inicio' => '12:00', 'hora_fin' => '13:00']))->assertStatus(201);

        $horarios = collect(
            $this->getJson("/api/disponibilidad?personal_id=1&fecha={$lunes}&duracion_minutos=60&a_domicilio=1&minutos_traslado=30")
                ->assertStatus(200)
                ->json('data.horarios')
        )->keyBy('hora');

        // 10:30–11:30 vuelve a las 12:00 (justo); 11:00–12:00 vuelve a las 12:30 y pisa la cita
        $this->assertTrue($horarios['10:30']['disponible']);
        $this->assertSame('ocupado', $horarios['11:00']['motivo']);
        // 13:30 sale a las 13:00, justo cuando termina la cita
        $this->assertTrue($horarios['13:30']['disponible']);
        $this->assertSame('ocupado', $horarios['13:00']['motivo']);

        // Sin domicilio, el traslado se ignora
        $enSalon = collect(
            $this->getJson("/api/disponibilidad?personal_id=1&fecha={$lunes}&duracion_minutos=60&minutos_traslado=30")
                ->json('data.horarios')
        )->keyBy('hora');
        $this->assertTrue($enSalon['11:00']['disponible']);
    }

    /**
     * Regla 2: a domicilio también se respeta el horario del salón, salvo fuera de horario.
     */
    public function test_domicilio_tambien_respeta_el_horario_del_salon(): void
    {
        $aDomicilio = [
            'hora_inicio' => '17:30',
            'hora_fin' => '18:15',
            'a_domicilio' => true,
            'direccion_domicilio' => 'Calle Ejemplo 123, Villa Elisa',
        ];

        $this->postJson('/api/reservas', $this->payload($aDomicilio))
            ->assertStatus(422)->assertJsonValidationErrors(['horario']);

        $this->postJson('/api/reservas', $this->payload([...$aDomicilio, 'fuera_de_horario' => true]))
            ->assertStatus(201)
            ->assertJsonPath('data.fuera_de_horario', true);
    }

    /**
     * Fuera de horario: se puede atender un día en que el salón no abre.
     */
    public function test_fuera_de_horario_permite_atender_un_domingo(): void
    {
        $this->postJson('/api/reservas', $this->payload([
            'fecha' => $this->obtenerProximoDomingo(),
            'fuera_de_horario' => true,
        ]))->assertStatus(201)->assertJsonPath('data.fuera_de_horario', true);

        // Una cita normal (dentro del horario) queda sin la marca
        $this->postJson('/api/reservas', $this->payload(['personal_id' => 2]))
            ->assertStatus(201)->assertJsonPath('data.fuera_de_horario', false);
    }

    /**
     * Fuera de horario: la cita debe quedar dentro de la jornada máxima (06:00 a 22:00).
     */
    public function test_fuera_de_horario_respeta_la_jornada_maxima(): void
    {
        $this->postJson('/api/reservas', $this->payload([
            'hora_inicio' => '21:30',
            'hora_fin' => '22:30',
            'fuera_de_horario' => true,
        ]))->assertStatus(422)->assertJsonValidationErrors(['horario']);

        $this->postJson('/api/reservas', $this->payload([
            'hora_inicio' => '05:30',
            'hora_fin' => '06:30',
            'fuera_de_horario' => true,
        ]))->assertStatus(422)->assertJsonValidationErrors(['horario']);

        $this->postJson('/api/reservas', $this->payload([
            'hora_inicio' => '21:00',
            'hora_fin' => '22:00',
            'fuera_de_horario' => true,
        ]))->assertStatus(201);
    }

    /**
     * Fuera de horario: la Regla 1 sigue valiendo (una profesional, una cita a la vez).
     */
    public function test_fuera_de_horario_respeta_el_solapamiento(): void
    {
        $nocturna = ['hora_inicio' => '19:00', 'hora_fin' => '20:00', 'fuera_de_horario' => true];

        $this->postJson('/api/reservas', $this->payload($nocturna))->assertStatus(201);

        $this->postJson('/api/reservas', $this->payload([...$nocturna, 'hora_inicio' => '19:30', 'hora_fin' => '20:30']))
            ->assertStatus(422)->assertJsonValidationErrors(['horario']);

        // Otra profesional sí puede a la misma hora
        $this->postJson('/api/reservas', $this->payload([...$nocturna, 'personal_id' => 2]))->assertStatus(201);
    }

    /**
     * Reprogramar: la marca se conserva; sin ella no se puede mover la cita fuera del horario.
     */
    public function test_reprogramar_fuera_del_horario_exige_la_marca(): void
    {
        $id = $this->postJson('/api/reservas', $this->payload())->assertStatus(201)->json('data.id');

        $this->putJson("/api/reservas/{$id}", ['hora_inicio' => '18:00', 'hora_fin' => '19:00'])
            ->assertStatus(422)->assertJsonValidationErrors(['horario']);

        $this->putJson("/api/reservas/{$id}", ['hora_inicio' => '18:00', 'hora_fin' => '19:00', 'fuera_de_horario' => true])
            ->assertStatus(200)->assertJsonPath('data.fuera_de_horario', true);

        // Sin enviar la marca, se conserva la que tenía al moverla otra vez fuera del horario
        $this->putJson("/api/reservas/{$id}", ['hora_inicio' => '18:30', 'hora_fin' => '19:30'])
            ->assertStatus(200)->assertJsonPath('data.fuera_de_horario', true);

        // Quitar la marca con la cita fuera del horario no se permite
        $this->putJson("/api/reservas/{$id}", ['fuera_de_horario' => false])
            ->assertStatus(422)->assertJsonValidationErrors(['horario']);
    }

    /**
     * Bajo demanda: cualquier profesional activa recibe citas dentro del horario del salón,
     * sin cargar un horario propio.
     */
    public function test_profesional_nueva_recibe_citas_sin_horario_propio(): void
    {
        $nueva = Personal::create(['nombre' => 'Lucía', 'apellido' => 'Vera', 'fecha_ingreso' => now()->toDateString(), 'activo' => true]);

        $this->postJson('/api/reservas', $this->payload(['personal_id' => $nueva->id, 'hora_inicio' => '08:00', 'hora_fin' => '09:00']))
            ->assertStatus(201);
        $this->postJson('/api/reservas', $this->payload(['personal_id' => $nueva->id, 'hora_inicio' => '16:00', 'hora_fin' => '17:00']))
            ->assertStatus(201);
    }

    /**
     * HU-06: un profesional solo ve y opera sobre su propia agenda.
     */
    public function test_profesional_opera_solo_sobre_su_agenda(): void
    {
        $deMonica = $this->postJson('/api/reservas', $this->payload())->assertStatus(201)->json('data.id');
        $deCarla = $this->postJson('/api/reservas', $this->payload(['personal_id' => 2]))->assertStatus(201)->json('data.id');

        $carla = User::factory()->create([
            'rol_id' => Role::firstOrCreate(['nombre' => 'profesional'])->id,
            'personal_id' => 2,
        ]);
        $this->actingAs($carla);

        $this->getJson('/api/reservas')->assertStatus(200)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $deCarla);
        $this->getJson("/api/reservas/{$deMonica}")->assertStatus(403);
        $this->putJson("/api/reservas/{$deMonica}", ['estado' => 'confirmada'])->assertStatus(403);
        $this->putJson("/api/reservas/{$deCarla}", ['estado' => 'confirmada'])->assertStatus(200);
        $this->postJson('/api/reservas', $this->payload(['hora_inicio' => '15:00', 'hora_fin' => '16:00']))->assertStatus(403);
        $this->postJson('/api/reservas', $this->payload(['personal_id' => 2, 'hora_inicio' => '15:00', 'hora_fin' => '16:00']))->assertStatus(201);
    }

    /**
     * HU-13 y HU-16: completar una reserva emite ReservaCompletada con las notas de la atención.
     */
    public function test_completar_reserva_emite_evento_con_notas(): void
    {
        Event::fake([ReservaCompletada::class]);
        $reserva = $this->reservaDirecta(Carbon::yesterday()->toDateString());

        $this->putJson("/api/reservas/{$reserva->id}", ['estado' => 'completada', 'notas' => 'Tinte 6.3, quedó conforme'])
            ->assertStatus(200);

        Event::assertDispatched(ReservaCompletada::class, fn ($evento) => $evento->reserva->id === $reserva->id
            && $evento->notas === 'Tinte 6.3, quedó conforme');
    }

    /**
     * HU-02: disponibilidad de un profesional en una fecha.
     */
    public function test_disponibilidad_marca_horarios_ocupados(): void
    {
        $lunes = $this->obtenerProximoLunes();
        $this->postJson('/api/reservas', $this->payload(['hora_inicio' => '10:00', 'hora_fin' => '11:00']))->assertStatus(201);

        $horarios = collect(
            $this->getJson("/api/disponibilidad?personal_id=1&fecha={$lunes}&duracion_minutos=60")
                ->assertStatus(200)
                ->assertJsonPath('data.franjas', [['08:00', '17:00']])
                ->json('data.horarios')
        )->keyBy('hora');

        // Ya no existe el motivo local_cerrado: solo se ofrecen horarios dentro del salón
        $this->assertSame([], $horarios->where('motivo', 'local_cerrado')->all());

        $this->assertTrue($horarios['08:00']['disponible']);
        $this->assertSame('ocupado', $horarios['09:30']['motivo']);
        $this->assertSame('ocupado', $horarios['10:30']['motivo']);
        $this->assertTrue($horarios['11:00']['disponible']);
        $this->assertTrue($horarios['16:00']['disponible']);
        $this->assertArrayNotHasKey('16:30', $horarios->all()); // no entra una hora antes del cierre
    }

    /**
     * HU-02: fuera de horario, la disponibilidad recorre la jornada máxima.
     */
    public function test_disponibilidad_fuera_de_horario_usa_la_jornada_maxima(): void
    {
        $domingo = $this->obtenerProximoDomingo();

        $datos = $this->getJson("/api/disponibilidad?personal_id=1&fecha={$domingo}&duracion_minutos=60&fuera_de_horario=1")
            ->assertStatus(200)
            ->assertJsonPath('data.franjas', [['06:00', '22:00']])
            ->json('data.horarios');

        $this->assertSame('06:00', $datos[0]['hora']);
        $this->assertSame('21:00', $datos[count($datos) - 1]['hora']);
    }

    /**
     * HU-02: si el salón no abre ese día (y no es fuera de horario), no hay horarios.
     */
    public function test_disponibilidad_vacia_cuando_el_salon_no_abre(): void
    {
        $domingo = $this->obtenerProximoDomingo();

        $this->getJson("/api/disponibilidad?personal_id=1&fecha={$domingo}&duracion_minutos=60")
            ->assertStatus(200)
            ->assertJsonPath('data.franjas', [])
            ->assertJsonPath('data.horarios', []);
    }

    /**
     * Regla 2: una cita puede ocupar dos franjas contiguas del horario del salón.
     */
    public function test_cita_en_franjas_contiguas_del_horario_del_salon(): void
    {
        HorarioAtencion::where('dia_semana', 1)->delete();
        HorarioAtencion::create(['dia_semana' => 1, 'hora_apertura' => '08:00:00', 'hora_cierre' => '12:00:00']);
        HorarioAtencion::create(['dia_semana' => 1, 'hora_apertura' => '12:00:00', 'hora_cierre' => '14:00:00']);

        $this->postJson('/api/reservas', $this->payload(['hora_inicio' => '11:30', 'hora_fin' => '12:30']))
            ->assertStatus(201);

        $this->postJson('/api/reservas', $this->payload(['hora_inicio' => '13:30', 'hora_fin' => '14:30']))
            ->assertStatus(422)->assertJsonValidationErrors(['horario']);
    }

    /**
     * Modificar reserva: la lista de servicios reemplaza a la actual. Los que siguen conservan
     * su precio congelado (Regla 4) y los nuevos toman el precio vigente.
     */
    public function test_modificar_los_servicios_de_una_reserva(): void
    {
        $id = $this->postJson('/api/reservas', $this->payload(['servicios' => [1]]))->assertStatus(201)->json('data.id');

        // Simula que el corte se reservó cuando costaba menos
        DetalleReserva::where('reserva_id', $id)->update(['precio_aplicado' => 1000]);
        $precioServicio2 = (float) Servicio::find(2)->precioVigente($this->obtenerProximoLunes())->precio;

        $respuesta = $this->putJson("/api/reservas/{$id}", ['servicios' => [1, 2], 'hora_fin' => '12:00'])
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.detalles')
            ->assertJsonPath('data.hora_fin', '12:00:00');

        $this->assertEquals(1000 + $precioServicio2, $respuesta->json('data.subtotal'));

        // Quitar el corte deja solo el servicio 2
        $this->putJson("/api/reservas/{$id}", ['servicios' => [2]])
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.detalles')
            ->assertJsonPath('data.detalles.0.servicio_id', 2);
    }

    public function test_modificar_servicios_valida_la_lista(): void
    {
        $id = $this->postJson('/api/reservas', $this->payload())->assertStatus(201)->json('data.id');

        $this->putJson("/api/reservas/{$id}", ['servicios' => []])->assertStatus(422)->assertJsonValidationErrors(['servicios']);
        $this->putJson("/api/reservas/{$id}", ['servicios' => [1, 1]])->assertStatus(422)->assertJsonValidationErrors(['servicios.1']);
        $this->putJson("/api/reservas/{$id}", ['servicios' => [999]])->assertStatus(422)->assertJsonValidationErrors(['servicios.0']);

        // Servicio 4 está inactivo (Regla 3): no se agrega y la reserva queda como estaba
        $this->putJson("/api/reservas/{$id}", ['servicios' => [1, 4]])->assertStatus(422)->assertJsonValidationErrors(['servicio_id']);
        $this->assertSame([1], DetalleReserva::where('reserva_id', $id)->pluck('servicio_id')->all());
    }

    public function test_no_se_sacan_servicios_por_debajo_de_lo_pagado(): void
    {
        $id = $this->postJson('/api/reservas', $this->payload(['servicios' => [1, 2], 'hora_fin' => '12:00']))
            ->assertStatus(201)->json('data.id');
        $total = Reserva::with(['detalles', 'descuento', 'pagos'])->find($id)->total;

        Pago::create([
            'reserva_id' => $id,
            'metodo_pago_id' => MetodoPago::first()->id,
            'monto' => $total,
            'fecha' => now(),
        ]);

        $this->putJson("/api/reservas/{$id}", ['servicios' => [1]])
            ->assertStatus(422)->assertJsonValidationErrors(['servicios']);
        $this->assertCount(2, DetalleReserva::where('reserva_id', $id)->get());
    }

    public function test_no_se_modifican_los_servicios_de_una_reserva_cerrada(): void
    {
        $reserva = $this->reservaDirecta(Carbon::yesterday()->toDateString(), 'completada');

        $this->putJson("/api/reservas/{$reserva->id}", ['servicios' => [2]])
            ->assertStatus(422)->assertJsonValidationErrors(['estado']);
    }

    /**
     * Modificar reserva: pasar del salón al domicilio (con traslado) y volver.
     */
    public function test_modificar_domicilio_y_traslado(): void
    {
        $id = $this->postJson('/api/reservas', $this->payload())->assertStatus(201)->json('data.id');

        $this->putJson("/api/reservas/{$id}", ['a_domicilio' => true, 'minutos_traslado' => 30])
            ->assertStatus(422)->assertJsonValidationErrors(['direccion_domicilio']);

        $this->putJson("/api/reservas/{$id}", [
            'a_domicilio' => true,
            'direccion_domicilio' => 'Calle Ejemplo 123, Villa Elisa',
            'minutos_traslado' => 30,
        ])->assertStatus(200)
            ->assertJsonPath('data.a_domicilio', true)
            ->assertJsonPath('data.minutos_traslado', 30);

        // De vuelta al salón: la dirección y el traslado se limpian
        $this->putJson("/api/reservas/{$id}", ['a_domicilio' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.a_domicilio', false)
            ->assertJsonPath('data.direccion_domicilio', null)
            ->assertJsonPath('data.minutos_traslado', 0);
    }

    /**
     * HU-02: al modificar una reserva, su propio horario no cuenta como ocupado.
     */
    public function test_disponibilidad_puede_excluir_la_propia_reserva(): void
    {
        $lunes = $this->obtenerProximoLunes();
        $id = $this->postJson('/api/reservas', $this->payload(['hora_inicio' => '10:00', 'hora_fin' => '11:00']))
            ->assertStatus(201)->json('data.id');

        $url = "/api/disponibilidad?personal_id=1&fecha={$lunes}&duracion_minutos=60";

        $conLaPropia = collect($this->getJson($url)->json('data.horarios'))->keyBy('hora');
        $this->assertSame('ocupado', $conLaPropia['10:00']['motivo']);

        $sinLaPropia = collect($this->getJson($url . "&excluir_reserva_id={$id}")->json('data.horarios'))->keyBy('hora');
        $this->assertTrue($sinLaPropia['10:00']['disponible']);
    }

    /**
     * Listado con filtros, tope de per_page y montos con descuento.
     */
    public function test_listado_filtra_y_calcula_totales_con_descuento(): void
    {
        $descuento = Descuento::first(); // Promo Primavera: 10 %

        $creada = $this->postJson('/api/reservas', $this->payload(['servicios' => [1, 2], 'descuento_id' => $descuento->id]))
            ->assertStatus(201)
            ->assertJsonPath('data.descuento.nombre', 'Promo Primavera');

        $this->assertEquals(120000, $creada->json('data.subtotal'));
        $this->assertEquals(12000, $creada->json('data.monto_descuento'));
        $this->assertEquals(108000, $creada->json('data.total'));

        $this->postJson('/api/reservas', $this->payload(['personal_id' => 2]))->assertStatus(201);

        $this->getJson('/api/reservas?personal_id=2')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.personal_id', 2);

        $lunes = $this->obtenerProximoLunes();
        $this->getJson("/api/reservas?fecha_desde={$lunes}&fecha_hasta={$lunes}&estado=pendiente")
            ->assertStatus(200)->assertJsonCount(2, 'data');

        $this->getJson('/api/reservas?per_page=500')
            ->assertStatus(422)->assertJsonValidationErrors(['per_page']);
    }

    /**
     * HU-07: No se desactiva personal con citas por atender.
     */
    public function test_no_se_desactiva_personal_con_reservas_futuras(): void
    {
        $id = $this->postJson('/api/reservas', $this->payload())->assertStatus(201)->json('data.id');

        $this->deleteJson('/api/personal/1')->assertStatus(422)->assertJsonValidationErrors(['activo']);
        $this->putJson('/api/personal/1', ['activo' => false])->assertStatus(422)->assertJsonValidationErrors(['activo']);

        $this->deleteJson("/api/reservas/{$id}")->assertStatus(200);
        $this->deleteJson('/api/personal/1')->assertStatus(200);
        $this->assertDatabaseHas('personal', ['id' => 1, 'activo' => false]);
    }

    /**
     * Clientes: alta, búsqueda y borrado protegido si tiene reservas.
     */
    public function test_gestion_de_clientes(): void
    {
        $this->postJson('/api/clientes', ['nombre' => 'Camila'])
            ->assertStatus(422)->assertJsonValidationErrors(['apellido']);

        $nueva = $this->postJson('/api/clientes', ['nombre' => 'Camila', 'apellido' => 'Benítez', 'telefono' => '0983555666'])
            ->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Camila');

        $this->getJson('/api/clientes?buscar=camila ben')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.apellido', 'Benítez');

        $this->postJson('/api/reservas', $this->payload())->assertStatus(201); // cliente 1 queda con reserva
        $this->deleteJson('/api/clientes/1')->assertStatus(422)->assertJsonValidationErrors(['cliente']);
        $this->deleteJson('/api/clientes/' . $nueva->json('data.id'))->assertStatus(200);
    }
}
