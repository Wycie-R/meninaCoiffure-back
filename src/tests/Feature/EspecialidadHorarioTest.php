<?php

namespace Tests\Feature;

use App\Models\Especialidad;
use App\Models\HorarioAtencion;
use App\Models\Personal;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\SprintUnoDosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HU-07 (especialidades del personal) y HU-08 (horario de atención del local).
 */
class EspecialidadHorarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SprintUnoDosSeeder::class);
        $this->actingAs(User::where('email', 'admin@meninacoiffure.com')->first());
    }

    protected function comoProfesional(): void
    {
        $this->actingAs(User::where('email', 'carla@meninacoiffure.com')->first());
    }

    // ---------- HU-07: Especialidades ----------

    public function test_listado_de_especialidades_con_su_personal(): void
    {
        $respuesta = $this->getJson('/api/especialidades')->assertStatus(200)->assertJsonCount(5, 'data');

        // Ordenadas por nombre; Maquillaje la tienen Mónica y Carla
        $this->assertSame('Colorimetría', $respuesta->json('data.0.nombre'));
        $maquillaje = collect($respuesta->json('data'))->firstWhere('nombre', 'Maquillaje');
        $this->assertSame(2, $maquillaje['personal_count']);
        $this->assertEqualsCanonicalizing(['Carla', 'Mónica'], array_column($maquillaje['personal'], 'nombre'));
        $this->assertArrayHasKey('apellido', $maquillaje['personal'][0]);

        $eventos = collect($respuesta->json('data'))->firstWhere('nombre', 'Peinados para eventos');
        $this->assertSame(1, $eventos['personal_count']);
        $this->assertSame(2, $eventos['personal'][0]['id']);

        // Cualquier rol puede consultarlas
        $this->comoProfesional();
        $this->getJson('/api/especialidades')->assertStatus(200)->assertJsonCount(5, 'data');
    }

    public function test_crud_de_especialidades(): void
    {
        $id = $this->postJson('/api/especialidades', ['nombre' => 'Barbería', 'descripcion' => 'Cortes y barba'])
            ->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Barbería')
            ->assertJsonPath('data.personal_count', 0)
            ->json('data.id');

        $this->getJson("/api/especialidades/{$id}")->assertStatus(200)
            ->assertJsonPath('data.descripcion', 'Cortes y barba');

        $this->putJson("/api/especialidades/{$id}", ['nombre' => 'Barbería clásica', 'descripcion' => null])
            ->assertStatus(200)
            ->assertJsonPath('data.nombre', 'Barbería clásica')
            ->assertJsonPath('data.descripcion', null);

        // Conservar el mismo nombre al editar no choca con la regla de único
        $this->putJson("/api/especialidades/{$id}", ['nombre' => 'Barbería clásica'])->assertStatus(200);

        Personal::find(1)->especialidades()->attach($id);

        $this->deleteJson("/api/especialidades/{$id}")->assertStatus(200);
        $this->assertDatabaseMissing('especialidades', ['id' => $id]);
        $this->assertDatabaseMissing('personal_especialidad', ['especialidad_id' => $id]);

        $this->getJson("/api/especialidades/{$id}")->assertStatus(404);
    }

    public function test_especialidad_exige_nombre_unico(): void
    {
        $this->postJson('/api/especialidades', [])->assertStatus(422)->assertJsonValidationErrors(['nombre']);

        $this->postJson('/api/especialidades', ['nombre' => 'Maquillaje'])
            ->assertStatus(422)->assertJsonValidationErrors(['nombre']);

        // Sin distinguir mayúsculas ni espacios sobrantes
        $this->postJson('/api/especialidades', ['nombre' => '  maquillaje '])
            ->assertStatus(422)->assertJsonValidationErrors(['nombre']);

        $estetica = Especialidad::where('nombre', 'Estética')->value('id');
        $this->putJson("/api/especialidades/{$estetica}", ['nombre' => 'Colorimetría'])
            ->assertStatus(422)->assertJsonValidationErrors(['nombre']);

        $this->assertDatabaseCount('especialidades', 5);
    }

    public function test_asignar_especialidades_al_personal(): void
    {
        $colorimetria = Especialidad::where('nombre', 'Colorimetría')->value('id');
        $estetica = Especialidad::where('nombre', 'Estética')->value('id');

        $this->putJson('/api/personal/2/especialidades', ['especialidades' => [$colorimetria, $estetica]])
            ->assertStatus(200)
            ->assertJsonPath('data.id', 2)
            ->assertJsonCount(2, 'data.especialidades')
            ->assertJsonMissingPath('data.turnos');

        $this->assertEqualsCanonicalizing(
            [$colorimetria, $estetica],
            Personal::find(2)->especialidades()->pluck('especialidades.id')->all()
        );

        // Ids inexistentes se rechazan sin tocar lo guardado
        $this->putJson('/api/personal/2/especialidades', ['especialidades' => [$colorimetria, 999]])
            ->assertStatus(422)->assertJsonValidationErrors(['especialidades.1']);
        $this->assertCount(2, Personal::find(2)->especialidades);

        $this->putJson('/api/personal/2/especialidades', [])->assertStatus(422)->assertJsonValidationErrors(['especialidades']);

        // Lista vacía: el profesional queda sin especialidades
        $this->putJson('/api/personal/2/especialidades', ['especialidades' => []])
            ->assertStatus(200)->assertJsonCount(0, 'data.especialidades');
    }

    public function test_profesional_no_administra_especialidades(): void
    {
        $this->comoProfesional();
        $maquillaje = Especialidad::where('nombre', 'Maquillaje')->value('id');

        $this->postJson('/api/especialidades', ['nombre' => 'Uñas'])->assertStatus(403);
        $this->putJson("/api/especialidades/{$maquillaje}", ['nombre' => 'Otro'])->assertStatus(403);
        $this->deleteJson("/api/especialidades/{$maquillaje}")->assertStatus(403);
        $this->putJson('/api/personal/2/especialidades', ['especialidades' => [$maquillaje]])->assertStatus(403);

        $this->assertDatabaseHas('especialidades', ['id' => $maquillaje, 'nombre' => 'Maquillaje']);
    }

    // ---------- HU-08: Horario del local ----------

    public function test_consulta_del_horario_del_local(): void
    {
        $this->getJson('/api/horarios-atencion')->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.dia_semana', 1)
            ->assertJsonPath('data.4.dia_semana', 5)
            ->assertJsonStructure(['data' => [['id', 'dia_semana', 'hora_apertura', 'hora_cierre']]]);

        $this->comoProfesional();
        $this->getJson('/api/horarios-atencion')->assertStatus(200)->assertJsonCount(5, 'data');
    }

    public function test_reemplazo_del_horario_del_local(): void
    {
        $respuesta = $this->putJson('/api/horarios-atencion', ['horarios' => [
            ['dia_semana' => 6, 'hora_apertura' => '08:00', 'hora_cierre' => '12:00'],
            ['dia_semana' => 1, 'hora_apertura' => '13:00', 'hora_cierre' => '18:00'],
            // Franjas contiguas del mismo día son válidas
            ['dia_semana' => 1, 'hora_apertura' => '08:00:00', 'hora_cierre' => '13:00:00'],
        ]])->assertStatus(200)->assertJsonCount(3, 'data');

        // Ordenado por día y apertura, con las horas normalizadas
        $respuesta->assertJsonPath('data.0.dia_semana', 1)
            ->assertJsonPath('data.0.hora_apertura', '08:00:00')
            ->assertJsonPath('data.1.hora_apertura', '13:00:00')
            ->assertJsonPath('data.2.dia_semana', 6);

        $this->assertSame(3, HorarioAtencion::count());
        $this->assertDatabaseMissing('horarios_atencion', ['dia_semana' => 2]);

        // Sin reservas por atender, nada queda fuera del horario nuevo
        $respuesta->assertJsonPath('reservas_fuera_de_horario', []);
    }

    public function test_achicar_el_horario_avisa_las_reservas_que_quedan_fuera(): void
    {
        $martes = Carbon::parse('next tuesday')->toDateString();
        $miercoles = Carbon::parse('next wednesday')->toDateString();
        $reserva = ['cliente_id' => 1, 'personal_id' => 1, 'servicios' => [1]];

        $tarde = $this->postJson('/api/reservas', [...$reserva, 'fecha' => $martes, 'hora_inicio' => '15:00', 'hora_fin' => '16:00'])
            ->assertStatus(201)->json('data.id');
        $this->postJson('/api/reservas', [...$reserva, 'fecha' => $martes, 'hora_inicio' => '09:00', 'hora_fin' => '10:00'])
            ->assertStatus(201);
        // Las marcadas como fuera de horario no se avisan: la dueña ya lo autorizó
        $this->postJson('/api/reservas', [...$reserva, 'fecha' => $miercoles, 'hora_inicio' => '19:00', 'hora_fin' => '20:00', 'fuera_de_horario' => true])
            ->assertStatus(201);

        $horarioDeMañana = [];
        foreach ([1, 2, 3, 4, 5] as $dia) {
            $horarioDeMañana[] = ['dia_semana' => $dia, 'hora_apertura' => '08:00', 'hora_cierre' => '13:00'];
        }

        $fuera = $this->putJson('/api/horarios-atencion', ['horarios' => $horarioDeMañana])
            ->assertStatus(200)
            ->json('reservas_fuera_de_horario');

        $this->assertCount(1, $fuera);
        $this->assertSame($tarde, $fuera[0]['id']);
        $this->assertSame('15:00:00', $fuera[0]['hora_inicio']);
        $this->assertSame(1, $fuera[0]['personal']['id']);
    }

    public function test_horario_con_horas_invertidas_o_solapadas_se_rechaza(): void
    {
        $this->putJson('/api/horarios-atencion', ['horarios' => [
            ['dia_semana' => 1, 'hora_apertura' => '08:00', 'hora_cierre' => '12:00'],
            ['dia_semana' => 2, 'hora_apertura' => '17:00', 'hora_cierre' => '08:00'],
        ]])->assertStatus(422)->assertJsonValidationErrors(['horarios.1.hora_cierre']);

        $this->putJson('/api/horarios-atencion', ['horarios' => [
            ['dia_semana' => 3, 'hora_apertura' => '08:00', 'hora_cierre' => '12:00'],
            ['dia_semana' => 3, 'hora_apertura' => '11:30', 'hora_cierre' => '15:00'],
        ]])->assertStatus(422)->assertJsonValidationErrors(['horarios']);

        $this->putJson('/api/horarios-atencion', ['horarios' => [
            ['dia_semana' => 8, 'hora_apertura' => '08:00', 'hora_cierre' => '12:00'],
        ]])->assertStatus(422)->assertJsonValidationErrors(['horarios.0.dia_semana']);

        $this->putJson('/api/horarios-atencion', ['horarios' => [
            ['dia_semana' => 1, 'hora_apertura' => '8 de la mañana', 'hora_cierre' => '12:00'],
        ]])->assertStatus(422)->assertJsonValidationErrors(['horarios.0.hora_apertura']);

        $this->putJson('/api/horarios-atencion', [])->assertStatus(422)->assertJsonValidationErrors(['horarios']);

        // Nada se tocó: sigue el horario sembrado
        $this->assertSame(5, HorarioAtencion::count());
    }

    public function test_el_salon_no_puede_quedar_sin_horario(): void
    {
        $this->putJson('/api/horarios-atencion', ['horarios' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['horarios']);

        $this->assertSame(5, HorarioAtencion::count());
    }

    public function test_reserva_en_el_salon_debe_caber_en_el_horario_del_local(): void
    {
        $this->putJson('/api/horarios-atencion', ['horarios' => [
            ['dia_semana' => 1, 'hora_apertura' => '09:00', 'hora_cierre' => '12:00'],
        ]])->assertStatus(200);

        $lunes = Carbon::parse('next monday')->toDateString();
        $reserva = [
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $lunes,
            'hora_inicio' => '14:00',
            'hora_fin' => '15:00',
            'servicios' => [1],
        ];

        // En el salón, fuera del horario del local: rechazada
        $this->postJson('/api/reservas', $reserva)->assertStatus(422)->assertJsonValidationErrors(['horario']);

        // A domicilio también se respeta el horario del salón
        $aDomicilio = [
            ...$reserva,
            'a_domicilio' => true,
            'direccion_domicilio' => 'Calle Mcal. López 123, Villa Elisa',
        ];
        $this->postJson('/api/reservas', $aDomicilio)->assertStatus(422)->assertJsonValidationErrors(['horario']);

        // Salvo que la dueña la marque como fuera de horario
        $this->postJson('/api/reservas', [...$aDomicilio, 'fuera_de_horario' => true])
            ->assertStatus(201)
            ->assertJsonPath('data.a_domicilio', true)
            ->assertJsonPath('data.fuera_de_horario', true);

        // Dentro del horario del local, en el salón: aceptada
        $this->postJson('/api/reservas', [...$reserva, 'hora_inicio' => '10:00', 'hora_fin' => '11:00'])
            ->assertStatus(201);
    }

    public function test_profesional_no_modifica_el_horario_del_local(): void
    {
        $this->comoProfesional();

        $this->putJson('/api/horarios-atencion', ['horarios' => [
            ['dia_semana' => 1, 'hora_apertura' => '08:00', 'hora_cierre' => '12:00'],
        ]])->assertStatus(403);
        $this->assertSame(5, HorarioAtencion::count());
    }
}
