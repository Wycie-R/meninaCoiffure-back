<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\DetalleReserva;
use App\Models\Personal;
use App\Models\PrecioServicio;
use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\TurnoPersonal;
use App\Services\ReservaService;
use Carbon\Carbon;
use Database\Seeders\AvanceDosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'servicios' => [1, 2], // 1 = Corte Femenino ($70000), 2 = Nutrición ($50000)
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

        // 3. Verificar que si es con OTRO personal (Diana = id 2), sí se permite en el mismo horario
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
}
