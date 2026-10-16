<?php

namespace Tests\Feature;

use App\Events\ReservaCompletada;
use App\Models\Cliente;
use App\Models\DetalleReserva;
use App\Models\Personal;
use App\Models\PrecioServicio;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\Descuento;
use App\Models\Role;
use App\Models\TurnoPersonal;
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
        // Ejecutar los seeders del módulo para contar con el personal, turnos y servicios base
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
     * Helper para obtener el próximo domingo (día sin turnos laborales).
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
     * Test 3: Falla por horario fuera del turno laboral del personal (Regla 2).
     */
    public function test_falla_por_horario_fuera_del_turno_laboral_del_personal_regla_2(): void
    {
        $fechaLunes = $this->obtenerProximoLunes();
        $fechaDomingo = $this->obtenerProximoDomingo();

        // Personal 1 trabaja de 08:00 a 18:00 de Lunes a Viernes.
        // Caso A: Lunes antes de comenzar su turno (06:30 a 07:30)
        $antesTurnoResponse = $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fechaLunes,
            'hora_inicio' => '06:30',
            'hora_fin' => '07:30',
            'servicios' => [1],
        ]);

        $antesTurnoResponse->assertStatus(422)
            ->assertJsonValidationErrors(['horario']);

        // Caso B: Lunes después de finalizar su turno (18:00 a 19:00)
        $despuesTurnoResponse = $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $fechaLunes,
            'hora_inicio' => '18:00',
            'hora_fin' => '19:00',
            'servicios' => [1],
        ]);

        $despuesTurnoResponse->assertStatus(422)
            ->assertJsonValidationErrors(['horario']);

        // Caso C: Domingo (día en el que no tiene turnos asignados)
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

        // Lunes a las 10:00: las 09:00 ya pasaron; las 09:45 entran en la tolerancia para quien llega sin turno
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
     * HU-14: el traslado de una cita a domicilio también ocupa la agenda del profesional.
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

        $this->postJson('/api/reservas', $this->payload(['hora_inicio' => '11:30', 'hora_fin' => '12:15']))
            ->assertStatus(201);

        // En el salón el traslado se ignora
        $this->postJson('/api/reservas', $this->payload([
            'personal_id' => 2, 'minutos_traslado' => 45,
        ]))->assertStatus(201)->assertJsonPath('data.minutos_traslado', 0);
    }

    /**
     * Regla 2 (local): en el salón la cita debe caber en el horario del local; a domicilio, no.
     */
    public function test_horario_del_local_aplica_solo_en_el_salon(): void
    {
        TurnoPersonal::where('personal_id', 1)->where('dia_semana', 1)->update(['hora_fin' => '19:00:00']);

        $this->postJson('/api/reservas', $this->payload(['hora_inicio' => '17:30', 'hora_fin' => '18:15']))
            ->assertStatus(422)->assertJsonValidationErrors(['horario']);

        $this->postJson('/api/reservas', $this->payload([
            'hora_inicio' => '17:30',
            'hora_fin' => '18:15',
            'a_domicilio' => true,
            'direccion_domicilio' => 'Calle Ejemplo 123, Villa Elisa',
        ]))->assertStatus(201);
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
                ->assertJsonPath('data.franjas.0', ['08:00', '17:00'])
                ->json('data.horarios')
        )->keyBy('hora');

        $this->assertTrue($horarios['08:00']['disponible']);
        $this->assertSame('ocupado', $horarios['09:30']['motivo']);
        $this->assertSame('ocupado', $horarios['10:30']['motivo']);
        $this->assertTrue($horarios['11:00']['disponible']);
        $this->assertTrue($horarios['16:00']['disponible']);
        $this->assertArrayNotHasKey('16:30', $horarios->all()); // no entra una hora antes del cierre
    }

    /**
     * Regla 2: una cita puede ocupar dos franjas contiguas del mismo día.
     */
    public function test_cita_en_franjas_contiguas_del_turno(): void
    {
        TurnoPersonal::where('personal_id', 1)->where('dia_semana', 1)->delete();
        TurnoPersonal::create(['personal_id' => 1, 'dia_semana' => 1, 'hora_inicio' => '08:00:00', 'hora_fin' => '12:00:00']);
        TurnoPersonal::create(['personal_id' => 1, 'dia_semana' => 1, 'hora_inicio' => '12:00:00', 'hora_fin' => '14:00:00']);

        $this->postJson('/api/reservas', $this->payload(['hora_inicio' => '11:30', 'hora_fin' => '12:30']))
            ->assertStatus(201);

        $this->postJson('/api/reservas', $this->payload(['hora_inicio' => '13:30', 'hora_fin' => '14:30']))
            ->assertStatus(422)->assertJsonValidationErrors(['horario']);
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
