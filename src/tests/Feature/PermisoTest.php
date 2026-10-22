<?php

namespace Tests\Feature;

use App\Models\Reserva;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AvanceDosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Permisos por empleada: la dueña decide desde Configuración qué puede hacer cada profesional.
 * Por defecto todo está habilitado (el comportamiento de siempre).
 */
class PermisoTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $carla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AvanceDosSeeder::class);

        $this->admin = User::factory()->create(['rol_id' => Role::firstOrCreate(['nombre' => 'admin'])->id]);
        // Carla es la profesional 2 del seeder
        $this->carla = User::factory()->create([
            'name' => 'Carla',
            'rol_id' => Role::firstOrCreate(['nombre' => 'profesional'])->id,
            'personal_id' => 2,
        ]);
    }

    /**
     * Cita de Carla el próximo lunes a las 10:00, creada por la administración.
     */
    protected function citaDeCarla(array $cambios = []): int
    {
        $this->actingAs($this->admin);

        return $this->postJson('/api/reservas', [
            'cliente_id' => 1,
            'personal_id' => 2,
            'fecha' => Carbon::parse('next monday')->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'servicios' => [1],
            ...$cambios,
        ])->assertStatus(201)->json('data.id');
    }

    protected function quitarPermiso(string $permiso): void
    {
        $this->carla->update([$permiso => false]);
        $this->actingAs($this->carla->fresh());
    }

    protected function payloadDeCarla(array $cambios = []): array
    {
        return [
            'cliente_id' => 1,
            'personal_id' => 2,
            'fecha' => Carbon::parse('next monday')->toDateString(),
            'hora_inicio' => '14:00',
            'hora_fin' => '15:00',
            'servicios' => [1],
            ...$cambios,
        ];
    }

    public function test_por_defecto_la_profesional_conserva_lo_que_podia_hacer(): void
    {
        $id = $this->citaDeCarla();
        $this->actingAs($this->carla);

        $this->getJson('/api/auth/me')->assertStatus(200)->assertJsonPath('data.permisos', [
            'puede_crear_reservas' => true,
            'puede_cambiar_estado' => true,
            'puede_reservar_fuera_de_horario' => true,
            'puede_ver_clientes' => true,
        ]);

        $this->postJson('/api/reservas', $this->payloadDeCarla())->assertStatus(201);
        $this->putJson("/api/reservas/{$id}", ['estado' => 'confirmada'])->assertStatus(200);
        $this->getJson('/api/clientes/1')->assertStatus(200);
    }

    public function test_sin_permiso_de_crear_no_agenda_ni_reprograma(): void
    {
        $id = $this->citaDeCarla();
        $this->quitarPermiso('puede_crear_reservas');

        $this->postJson('/api/reservas', $this->payloadDeCarla())
            ->assertStatus(403)
            ->assertJsonPath('message', 'La administración no te habilitó para esta acción.');
        $this->getJson('/api/disponibilidad?personal_id=2&fecha=' . Carbon::parse('next monday')->toDateString() . '&duracion_minutos=60')
            ->assertStatus(403);
        $this->postJson("/api/reservas/{$id}/servicios", ['servicio_id' => 2])->assertStatus(403);
        $this->putJson("/api/reservas/{$id}", ['hora_inicio' => '12:00', 'hora_fin' => '13:00'])->assertStatus(403);
        $this->putJson("/api/reservas/{$id}", ['servicios' => [1, 2]])->assertStatus(403);
        $this->putJson("/api/reservas/{$id}", ['a_domicilio' => true, 'direccion_domicilio' => 'Calle 1', 'minutos_traslado' => 15])->assertStatus(403);
        $this->get('/reservas/crear')->assertStatus(403);

        // Su agenda la sigue viendo, y puede cambiar el estado
        $this->getJson('/api/reservas')->assertStatus(200)->assertJsonCount(1, 'data');
        $this->getJson("/api/reservas/{$id}")->assertStatus(200);
        $this->putJson("/api/reservas/{$id}", ['estado' => 'confirmada'])->assertStatus(200);
    }

    public function test_sin_permiso_de_estado_no_confirma_ni_cancela(): void
    {
        $id = $this->citaDeCarla();
        $this->quitarPermiso('puede_cambiar_estado');

        $this->putJson("/api/reservas/{$id}", ['estado' => 'confirmada'])->assertStatus(403);
        $this->putJson("/api/reservas/{$id}", ['estado' => 'cancelada'])->assertStatus(403);
        $this->deleteJson("/api/reservas/{$id}")->assertStatus(403);
        $this->assertSame('pendiente', Reserva::find($id)->estado);

        // Reprogramar sí puede (tiene el permiso de crear)
        $this->putJson("/api/reservas/{$id}", ['hora_inicio' => '12:00', 'hora_fin' => '13:00'])->assertStatus(200);
        // Enviar el mismo estado que ya tiene no cuenta como cambio
        $this->putJson("/api/reservas/{$id}", ['estado' => 'pendiente', 'hora_inicio' => '12:30', 'hora_fin' => '13:30'])->assertStatus(200);
    }

    public function test_fuera_de_horario_requiere_su_propio_permiso(): void
    {
        $id = $this->citaDeCarla();
        $this->quitarPermiso('puede_reservar_fuera_de_horario');

        $nocturna = ['hora_inicio' => '19:00', 'hora_fin' => '20:00', 'fuera_de_horario' => true];

        $this->postJson('/api/reservas', $this->payloadDeCarla($nocturna))->assertStatus(403);
        $this->putJson("/api/reservas/{$id}", $nocturna)->assertStatus(403);

        // Dentro del horario del salón no hace falta
        $this->postJson('/api/reservas', $this->payloadDeCarla())->assertStatus(201);
    }

    public function test_mover_una_cita_ya_fuera_de_horario_no_pide_el_permiso(): void
    {
        $id = $this->citaDeCarla(['hora_inicio' => '19:00', 'hora_fin' => '20:00', 'fuera_de_horario' => true]);
        $this->quitarPermiso('puede_reservar_fuera_de_horario');

        // La dueña ya la autorizó: Carla puede correrla media hora
        $this->putJson("/api/reservas/{$id}", ['hora_inicio' => '19:30', 'hora_fin' => '20:30', 'fuera_de_horario' => true])
            ->assertStatus(200);
    }

    public function test_sin_permiso_de_clientes_no_ve_el_expediente_pero_puede_buscar_para_agendar(): void
    {
        $this->quitarPermiso('puede_ver_clientes');

        $this->getJson('/api/clientes/1')->assertStatus(403);
        $this->putJson('/api/clientes/1', ['telefono' => '0981000000'])->assertStatus(403);
        $this->getJson('/api/clientes/1/preferencias')->assertStatus(403);
        $this->getJson('/api/clientes/1/historial')->assertStatus(403);

        // Buscar y dar de alta una clienta sirve para agendar
        $this->getJson('/api/clientes')->assertStatus(200);
        $this->postJson('/api/clientes', ['nombre' => 'Rosa', 'apellido' => 'Insfrán', 'telefono' => '0981222333'])
            ->assertStatus(201);
    }

    public function test_sin_crear_ni_ver_clientes_no_accede_al_listado(): void
    {
        $this->carla->update(['puede_ver_clientes' => false, 'puede_crear_reservas' => false]);
        $this->actingAs($this->carla->fresh());

        $this->getJson('/api/clientes')->assertStatus(403);
        $this->postJson('/api/clientes', ['nombre' => 'Rosa', 'apellido' => 'Insfrán'])->assertStatus(403);
    }

    public function test_la_administracion_siempre_puede_todo(): void
    {
        // Aunque sus columnas digan false, la administración no depende de ellas
        $this->admin->update(['puede_crear_reservas' => false, 'puede_cambiar_estado' => false]);
        $this->actingAs($this->admin->fresh());

        $this->getJson('/api/auth/me')->assertJsonPath('data.permisos.puede_crear_reservas', true);
        $id = $this->postJson('/api/reservas', $this->payloadDeCarla())->assertStatus(201)->json('data.id');
        $this->putJson("/api/reservas/{$id}", ['estado' => 'confirmada'])->assertStatus(200);
    }

    public function test_la_duena_lista_y_cambia_los_permisos_del_equipo(): void
    {
        $this->actingAs($this->admin);

        $respuesta = $this->getJson('/api/configuracion/permisos')->assertStatus(200);
        $respuesta->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->carla->id)
            ->assertJsonPath('data.0.personal.id', 2)
            ->assertJsonPath('data.0.permisos.puede_cambiar_estado', true)
            ->assertJsonCount(4, 'catalogo')
            ->assertJsonPath('catalogo.0.clave', 'puede_crear_reservas');

        $this->putJson("/api/configuracion/permisos/{$this->carla->id}", ['puede_cambiar_estado' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.permisos.puede_cambiar_estado', false)
            ->assertJsonPath('data.permisos.puede_crear_reservas', true);

        $this->assertFalse($this->carla->fresh()->puede_cambiar_estado);

        // Carla lo ve reflejado en su sesión
        $this->actingAs($this->carla->fresh());
        $this->getJson('/api/auth/me')->assertJsonPath('data.permisos.puede_cambiar_estado', false);
    }

    public function test_permisos_invalidos_o_de_la_administracion_se_rechazan(): void
    {
        $this->actingAs($this->admin);

        $this->putJson("/api/configuracion/permisos/{$this->carla->id}", ['puede_cambiar_estado' => 'quizás'])
            ->assertStatus(422)->assertJsonValidationErrors(['puede_cambiar_estado']);

        $this->putJson("/api/configuracion/permisos/{$this->admin->id}", ['puede_cambiar_estado' => false])
            ->assertStatus(422)->assertJsonValidationErrors(['user']);

        // Claves desconocidas se ignoran
        $this->putJson("/api/configuracion/permisos/{$this->carla->id}", ['es_admin' => true])->assertStatus(200);
        $this->assertTrue($this->carla->fresh()->hasRole('profesional'));
    }

    public function test_una_profesional_no_entra_a_configuracion(): void
    {
        $this->actingAs($this->carla);

        $this->getJson('/api/configuracion/permisos')->assertStatus(403);
        $this->putJson("/api/configuracion/permisos/{$this->carla->id}", ['puede_cambiar_estado' => true])->assertStatus(403);
        $this->get('/configuracion')->assertStatus(403);
    }
}
