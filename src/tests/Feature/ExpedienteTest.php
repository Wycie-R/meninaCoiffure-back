<?php

namespace Tests\Feature;

use App\Events\ReservaCompletada;
use App\Models\HistorialVisita;
use App\Models\Reserva;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\SprintUnoDosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HU-05 y HU-13: Expediente del cliente (preferencias e historial de visitas).
 */
class ExpedienteTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SprintUnoDosSeeder::class);
        $this->admin = User::where('email', 'admin@meninacoiffure.com')->first();
        $this->actingAs($this->admin);
    }

    protected function proximoLunes(): string
    {
        return Carbon::parse('next monday')->toDateString();
    }

    /**
     * Crea la reserva por la API para el próximo lunes y la completa viajando a la hora de la cita.
     */
    protected function atenderPorApi(int $clienteId, int $personalId, string $horaInicio, string $horaFin, array $servicios = [1], ?string $notas = null): int
    {
        $reservaId = $this->postJson('/api/reservas', [
            'cliente_id' => $clienteId,
            'personal_id' => $personalId,
            'fecha' => $this->proximoLunes(),
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'servicios' => $servicios,
        ])->assertStatus(201)->json('data.id');

        $this->travelTo(Carbon::parse($this->proximoLunes() . ' ' . $horaInicio)->addMinutes(10));

        $this->putJson("/api/reservas/{$reservaId}", array_filter([
            'estado' => 'completada',
            'notas' => $notas,
        ], fn ($valor) => $valor !== null))->assertStatus(200)->assertJsonPath('data.estado', 'completada');

        $this->travelBack();

        return $reservaId;
    }

    // ---------- HU-05: Preferencias ----------

    public function test_crud_de_preferencias_del_cliente(): void
    {
        $this->getJson('/api/clientes/1/preferencias')->assertStatus(200)->assertJsonCount(0, 'data');

        $id = $this->postJson('/api/clientes/1/preferencias', [
            'categoria' => 'alergia',
            'descripcion' => 'Sensible al amoníaco',
        ])->assertStatus(201)
            ->assertJsonPath('data.cliente_id', 1)
            ->assertJsonPath('data.categoria', 'alergia')
            ->json('data.id');

        // La categoría es opcional
        $this->postJson('/api/clientes/1/preferencias', ['descripcion' => 'Le gusta el café sin azúcar'])
            ->assertStatus(201)->assertJsonPath('data.categoria', null);

        $this->getJson('/api/clientes/1/preferencias')->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.descripcion', 'Sensible al amoníaco');

        // Las de otro cliente no se mezclan
        $this->getJson('/api/clientes/2/preferencias')->assertJsonCount(0, 'data');

        $this->putJson("/api/preferencias/{$id}", ['categoria' => 'producto', 'descripcion' => 'Usa champú sin sulfatos'])
            ->assertStatus(200)
            ->assertJsonPath('data.categoria', 'producto')
            ->assertJsonPath('data.descripcion', 'Usa champú sin sulfatos');

        $this->deleteJson("/api/preferencias/{$id}")->assertStatus(200);
        $this->assertDatabaseMissing('preferencias_cliente', ['id' => $id]);
        $this->getJson('/api/clientes/1/preferencias')->assertJsonCount(1, 'data');
    }

    public function test_preferencia_valida_categoria_y_descripcion(): void
    {
        $this->postJson('/api/clientes/1/preferencias', ['categoria' => 'horoscopo', 'descripcion' => 'Algo'])
            ->assertStatus(422)->assertJsonValidationErrors(['categoria']);

        $this->postJson('/api/clientes/1/preferencias', ['categoria' => 'color'])
            ->assertStatus(422)->assertJsonValidationErrors(['descripcion']);

        $this->postJson('/api/clientes/1/preferencias', ['descripcion' => str_repeat('a', 1001)])
            ->assertStatus(422)->assertJsonValidationErrors(['descripcion']);

        $this->postJson('/api/clientes/999/preferencias', ['descripcion' => 'Algo'])->assertStatus(404);
        $this->assertDatabaseCount('preferencias_cliente', 0);
    }

    public function test_la_profesional_tambien_registra_preferencias(): void
    {
        $this->actingAs(User::where('email', 'carla@meninacoiffure.com')->first());

        $this->postJson('/api/clientes/2/preferencias', ['categoria' => 'color', 'descripcion' => 'Rubio ceniza 9.1'])
            ->assertStatus(201);
    }

    // ---------- HU-13: Historial ----------

    public function test_completar_reserva_crea_el_historial_con_notas_y_sin_duplicar(): void
    {
        $reservaId = $this->atenderPorApi(1, 1, '10:00', '11:00', [1], 'Se usó tinte 6.3');

        $this->assertDatabaseCount('historial_visitas', 1);
        $visita = HistorialVisita::where('reserva_id', $reservaId)->first();
        $this->assertSame('Se usó tinte 6.3', $visita->notas);
        $this->assertSame($this->proximoLunes(), $visita->fecha_realizada->toDateString());

        // Un segundo intento de completar se rechaza y no duplica el historial
        $this->putJson("/api/reservas/{$reservaId}", ['estado' => 'completada', 'notas' => 'Otra'])->assertStatus(422);
        $this->assertDatabaseCount('historial_visitas', 1);

        // Si el evento se repitiera, el listener actualiza el registro existente en vez de duplicarlo
        event(new ReservaCompletada(Reserva::find($reservaId), 'Notas corregidas'));
        $this->assertDatabaseCount('historial_visitas', 1);
        $this->assertDatabaseHas('historial_visitas', ['reserva_id' => $reservaId, 'notas' => 'Notas corregidas']);
    }

    public function test_completar_sin_notas_crea_historial_con_notas_vacias(): void
    {
        $reservaId = $this->atenderPorApi(2, 2, '09:00', '10:00');

        $this->assertDatabaseHas('historial_visitas', ['reserva_id' => $reservaId, 'notas' => null]);
    }

    public function test_historial_del_cliente_con_resumen(): void
    {
        $this->postJson('/api/clientes/1/preferencias', ['categoria' => 'alergia', 'descripcion' => 'Sensible al amoníaco'])
            ->assertStatus(201);

        // Tres visitas: dos con Carla (corte) y una con Mónica (corte + nutrición)
        $primera = $this->atenderPorApi(1, 2, '08:00', '09:00', [1], 'Corte en capas');
        $segunda = $this->atenderPorApi(1, 1, '10:00', '11:00', [1, 2], 'Nutrición profunda');
        $tercera = $this->atenderPorApi(1, 2, '14:00', '15:00', [1], 'Repaso de flequillo');

        // Visita de otro cliente: no debe aparecer
        $this->atenderPorApi(2, 1, '15:00', '16:00', [3]);

        // Ausencias y cancelaciones (directas en la base)
        $ayer = Carbon::yesterday()->toDateString();
        foreach (['no_asistio', 'no_asistio', 'cancelada'] as $i => $estado) {
            Reserva::create([
                'cliente_id' => 1, 'personal_id' => 1, 'fecha' => $ayer,
                'hora_inicio' => sprintf('%02d:00:00', 9 + $i), 'hora_fin' => sprintf('%02d:00:00', 10 + $i),
                'estado' => $estado,
            ]);
        }

        $respuesta = $this->getJson('/api/clientes/1/historial')->assertStatus(200);

        $respuesta->assertJsonPath('data.cliente.id', 1)
            ->assertJsonPath('data.cliente.nombre', 'Ana')
            ->assertJsonPath('data.cliente.telefono', '0981111222')
            ->assertJsonCount(1, 'data.preferencias')
            ->assertJsonPath('data.preferencias.0.categoria', 'alergia')
            ->assertJsonCount(3, 'data.visitas');

        // Más reciente primero (mismo día de realización: manda la hora de la cita)
        $respuesta->assertJsonPath('data.visitas.0.reserva_id', $tercera)
            ->assertJsonPath('data.visitas.1.reserva_id', $segunda)
            ->assertJsonPath('data.visitas.2.reserva_id', $primera)
            ->assertJsonPath('data.visitas.1.notas', 'Nutrición profunda')
            ->assertJsonPath('data.visitas.1.profesional.id', 1)
            ->assertJsonPath('data.visitas.1.profesional.nombre', 'Mónica')
            ->assertJsonPath('data.visitas.1.a_domicilio', false)
            ->assertJsonCount(2, 'data.visitas.1.servicios')
            ->assertJsonPath('data.visitas.1.servicios.0.nombre', 'Corte de dama')
            ->assertJsonPath('data.visitas.1.servicios.0.cantidad', 1);
        $this->assertEquals(70000, $respuesta->json('data.visitas.1.servicios.0.precio_aplicado'));
        $this->assertEquals(120000, $respuesta->json('data.visitas.1.total'));
        $this->assertSame($this->proximoLunes(), $respuesta->json('data.visitas.0.fecha_realizada'));

        $respuesta->assertJsonPath('data.resumen.visitas', 3)
            ->assertJsonPath('data.resumen.ultima_visita', $this->proximoLunes())
            ->assertJsonPath('data.resumen.profesional_frecuente.id', 2)
            ->assertJsonPath('data.resumen.profesional_frecuente.nombre', 'Carla')
            ->assertJsonPath('data.resumen.servicios_frecuentes.0.nombre', 'Corte de dama')
            ->assertJsonPath('data.resumen.servicios_frecuentes.0.veces', 3)
            ->assertJsonPath('data.resumen.servicios_frecuentes.1.nombre', 'Nutrición e hidratación capilar')
            ->assertJsonPath('data.resumen.servicios_frecuentes.1.veces', 1)
            ->assertJsonCount(2, 'data.resumen.servicios_frecuentes')
            ->assertJsonPath('data.resumen.ausencias', 2)
            ->assertJsonPath('data.resumen.cancelaciones', 1);
    }

    public function test_historial_vacio_de_cliente_sin_visitas(): void
    {
        $this->getJson('/api/clientes/2/historial')->assertStatus(200)
            ->assertJsonCount(0, 'data.visitas')
            ->assertJsonPath('data.resumen.visitas', 0)
            ->assertJsonPath('data.resumen.ultima_visita', null)
            ->assertJsonPath('data.resumen.profesional_frecuente', null)
            ->assertJsonPath('data.resumen.servicios_frecuentes', [])
            ->assertJsonPath('data.resumen.ausencias', 0)
            ->assertJsonPath('data.resumen.cancelaciones', 0);

        $this->getJson('/api/clientes/999/historial')->assertStatus(404);
    }

    public function test_historial_no_hace_consultas_por_cada_visita(): void
    {
        $this->atenderPorApi(1, 1, '08:00', '09:00', [1, 2]);

        DB::enableQueryLog();
        $this->getJson('/api/clientes/1/historial')->assertStatus(200);
        $conUna = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->atenderPorApi(1, 2, '10:00', '11:00', [3]);
        $this->atenderPorApi(1, 1, '13:00', '14:00', [5]);

        DB::flushQueryLog();
        $this->getJson('/api/clientes/1/historial')->assertStatus(200)->assertJsonCount(3, 'data.visitas');
        $conTres = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($conUna, $conTres);
    }

    public function test_actualizar_notas_de_la_visita_y_permiso_por_agenda(): void
    {
        // Visita atendida por Mónica (personal 1)
        $reservaMonica = $this->atenderPorApi(1, 1, '10:00', '11:00', [1], 'Inicial');
        $visitaMonica = HistorialVisita::where('reserva_id', $reservaMonica)->value('id');

        // Visita atendida por Carla (personal 2)
        $reservaCarla = $this->atenderPorApi(2, 2, '10:00', '11:00', [1]);
        $visitaCarla = HistorialVisita::where('reserva_id', $reservaCarla)->value('id');

        // La administración corrige cualquiera
        $this->putJson("/api/historial-visitas/{$visitaMonica}", ['notas' => 'Corregida por la dueña'])
            ->assertStatus(200)
            ->assertJsonPath('data.notas', 'Corregida por la dueña')
            ->assertJsonPath('data.profesional.id', 1);

        $this->putJson("/api/historial-visitas/{$visitaMonica}", ['notas' => str_repeat('x', 2001)])
            ->assertStatus(422)->assertJsonValidationErrors(['notas']);

        // Carla solo edita las visitas de su propia agenda
        $this->actingAs(User::where('email', 'carla@meninacoiffure.com')->first());

        $this->putJson("/api/historial-visitas/{$visitaMonica}", ['notas' => 'Intento ajeno'])->assertStatus(403);
        $this->assertDatabaseHas('historial_visitas', ['id' => $visitaMonica, 'notas' => 'Corregida por la dueña']);

        $this->putJson("/api/historial-visitas/{$visitaCarla}", ['notas' => 'Mechas suaves'])
            ->assertStatus(200)->assertJsonPath('data.notas', 'Mechas suaves');

        // Cualquier rol consulta el historial completo del cliente
        $this->getJson('/api/clientes/1/historial')->assertStatus(200)->assertJsonCount(1, 'data.visitas');
    }

    public function test_expediente_exige_sesion(): void
    {
        auth()->logout();

        $this->getJson('/api/clientes/1/historial')->assertStatus(401);
        $this->getJson('/api/clientes/1/preferencias')->assertStatus(401);
    }
}
