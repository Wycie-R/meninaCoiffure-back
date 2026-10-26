<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Reserva;
use App\Models\Role;
use App\Models\SolicitudCita;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AvanceDosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cuentas de clientas: ven solo sus citas y pedidos, y piden citas que la dueña acepta
 * (creando la reserva con las reglas de siempre) o rechaza con un motivo.
 */
class SolicitudCitaTest extends TestCase
{
    use RefreshDatabase;

    private User $clienta;
    private Cliente $ficha;
    private User $duena;

    protected function setUp(): void
    {
        parent::setUp();
        // Personal, horario del salón y catálogo
        $this->seed(AvanceDosSeeder::class);

        $this->duena = User::factory()->create(['rol_id' => Role::firstOrCreate(['nombre' => 'admin'])->id]);
        [$this->clienta, $this->ficha] = $this->crearClienta('Lucía', 'lucia@gmail.com');
    }

    private function crearClienta(string $nombre, string $email): array
    {
        $usuario = User::factory()->create([
            'name' => $nombre,
            'email' => $email,
            'rol_id' => Role::firstOrCreate(['nombre' => 'cliente'])->id,
        ]);
        $ficha = Cliente::create(['nombre' => $nombre, 'apellido' => 'Prueba', 'email' => $email]);
        $ficha->user_id = $usuario->id;
        $ficha->save();

        return [$usuario, $ficha];
    }

    private function proximoLunes(): string
    {
        return Carbon::parse('next monday')->toDateString();
    }

    private function pedido(array $cambios = []): array
    {
        $base = [
            'servicios' => [1],
            'fecha_preferida' => $this->proximoLunes(),
            'hora_preferida' => '10:00',
            'comentario' => 'Si se puede, temprano.',
        ];

        return array_merge($base, $cambios);
    }

    private function crearSolicitud(Cliente $ficha, string $estado = 'pendiente'): SolicitudCita
    {
        $solicitud = SolicitudCita::create([
            'cliente_id' => $ficha->id,
            'fecha_preferida' => $this->proximoLunes(),
            'franja' => 'manana',
            'estado' => $estado,
        ]);
        $solicitud->servicios()->attach([1]);

        return $solicitud;
    }

    // Lo mismo que se manda a POST /api/reservas
    private function datosDeReserva(array $cambios = []): array
    {
        $base = [
            'cliente_id' => $this->ficha->id,
            'personal_id' => 1,
            'fecha' => $this->proximoLunes(),
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'servicios' => [1],
        ];

        return array_merge($base, $cambios);
    }

    public function test_la_clienta_pide_una_cita_con_varios_servicios_y_queda_pendiente(): void
    {
        $this->actingAs($this->clienta)
            ->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['servicios' => [1, 2], 'telefono' => '0981 555 123']))
            ->assertStatus(201)
            ->assertJsonCount(2, 'data.servicios')
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.franja', 'manana')
            ->assertJsonPath('data.fecha_preferida', $this->proximoLunes());

        $solicitud = SolicitudCita::where('cliente_id', $this->ficha->id)->first();
        $this->assertSame('pendiente', $solicitud->estado);
        $this->assertEqualsCanonicalizing([1, 2], $solicitud->servicios->pluck('id')->all());
        // El teléfono queda en la ficha para que la dueña pueda coordinar
        $this->assertSame('0981 555 123', $this->ficha->fresh()->telefono);
    }

    public function test_pedido_con_fecha_pasada_o_domicilio_sin_direccion_es_invalido(): void
    {
        $this->actingAs($this->clienta);

        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['fecha_preferida' => now()->subDay()->toDateString()]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha_preferida');

        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['a_domicilio' => true]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('direccion_domicilio');

        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['hora_preferida' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('hora_preferida');

        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['servicios' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('servicios');

        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['servicios' => [1, 1]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('servicios.1');

        $this->assertSame(0, SolicitudCita::count());
    }

    public function test_no_se_puede_pedir_un_servicio_inactivo(): void
    {
        \App\Models\Servicio::whereKey(1)->update(['activo' => false]);

        $this->actingAs($this->clienta)
            ->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['servicios' => [2, 1]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('servicios.1');
    }

    public function test_los_pedidos_pendientes_se_acumulan_sin_tope(): void
    {
        // Antes había un tope de 3; ahora la clienta puede tener todos los pedidos abiertos que necesite
        for ($i = 0; $i < 5; $i++) {
            $this->crearSolicitud($this->ficha);
        }

        $this->actingAs($this->clienta)
            ->postJson('/api/mi-cuenta/solicitudes', $this->pedido())
            ->assertStatus(201);

        $this->assertSame(6, SolicitudCita::where('cliente_id', $this->ficha->id)->where('estado', 'pendiente')->count());
    }

    public function test_la_clienta_solo_ve_sus_citas_y_sus_pedidos(): void
    {
        [, $otraFicha] = $this->crearClienta('Otra', 'otra@gmail.com');
        $this->crearSolicitud($this->ficha);
        $this->crearSolicitud($otraFicha);

        $this->actingAs($this->duena)->postJson('/api/reservas', $this->datosDeReserva())->assertStatus(201);
        $this->postJson('/api/reservas', $this->datosDeReserva([
            'cliente_id' => $otraFicha->id,
            'hora_inicio' => '14:00',
            'hora_fin' => '15:00',
        ]))->assertStatus(201);

        $this->actingAs($this->clienta);

        $this->getJson('/api/mi-cuenta/solicitudes')->assertStatus(200)->assertJsonCount(1, 'data');

        // Cada servicio trae su id para "Volver a pedir" lo mismo
        $citas = $this->getJson('/api/mi-cuenta/citas')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.proximas')
            ->assertJsonPath('data.proximas.0.hora_inicio', '10:00')
            ->assertJsonPath('data.proximas.0.servicios.0.id', 1)
            ->assertJsonPath('data.proximas.0.servicios.0.activo', true);

        // Sin datos internos: ni pagos ni la ficha completa de la clienta
        $cita = $citas->json('data.proximas.0');
        $this->assertArrayNotHasKey('pagos', $cita);
        $this->assertArrayNotHasKey('cliente', $cita);

        $this->getJson('/api/mi-cuenta')->assertJsonPath('data.email', 'lucia@gmail.com');
    }

    public function test_la_clienta_no_entra_a_las_rutas_del_equipo(): void
    {
        $this->actingAs($this->clienta);

        $this->getJson('/api/reservas')->assertStatus(403);
        $this->getJson('/api/clientes')->assertStatus(403);
        $this->getJson('/api/solicitudes-cita')->assertStatus(403);
        $this->postJson('/api/reservas', $this->datosDeReserva())->assertStatus(403);
    }

    public function test_el_equipo_no_entra_a_mi_cuenta(): void
    {
        $this->actingAs($this->duena)->getJson('/api/mi-cuenta/citas')->assertStatus(403);
    }

    public function test_la_clienta_retira_su_pedido_pendiente_pero_no_uno_respondido_ni_ajeno(): void
    {
        $pendiente = $this->crearSolicitud($this->ficha);
        $rechazada = $this->crearSolicitud($this->ficha, 'rechazada');
        [, $otraFicha] = $this->crearClienta('Otra', 'otra@gmail.com');
        $ajena = $this->crearSolicitud($otraFicha);

        $this->actingAs($this->clienta);

        $this->deleteJson('/api/mi-cuenta/solicitudes/' . $pendiente->id)
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'cancelada');

        $this->deleteJson('/api/mi-cuenta/solicitudes/' . $rechazada->id)->assertStatus(422);
        $this->deleteJson('/api/mi-cuenta/solicitudes/' . $ajena->id)->assertStatus(404);

        $this->assertSame('pendiente', $ajena->fresh()->estado);
        // No se borra: queda el rastro del pedido retirado
        $this->assertDatabaseHas('solicitudes_cita', ['id' => $pendiente->id, 'estado' => 'cancelada']);
    }

    public function test_la_duena_acepta_y_se_crea_la_reserva_de_quien_la_pidio(): void
    {
        $solicitud = $this->crearSolicitud($this->ficha);
        [, $otraFicha] = $this->crearClienta('Otra', 'otra@gmail.com');

        // Aunque el cuerpo traiga otra clienta, la reserva es de quien hizo el pedido
        $respuesta = $this->actingAs($this->duena)
            ->postJson('/api/solicitudes-cita/' . $solicitud->id . '/aceptar', $this->datosDeReserva(['cliente_id' => $otraFicha->id]))
            ->assertStatus(201)
            ->assertJsonPath('data.cliente_id', $this->ficha->id);

        $reservaId = $respuesta->json('data.id');
        $solicitud->refresh();
        $this->assertSame('aceptada', $solicitud->estado);
        $this->assertSame($reservaId, $solicitud->reserva_id);

        // La clienta ve el día y la hora que le asignaron
        $this->actingAs($this->clienta)
            ->getJson('/api/mi-cuenta/solicitudes')
            ->assertJsonPath('data.0.estado', 'aceptada')
            ->assertJsonPath('data.0.cita_asignada.hora_inicio', '10:00');
    }

    public function test_aceptar_con_el_horario_ocupado_no_cambia_la_solicitud(): void
    {
        $this->actingAs($this->duena)->postJson('/api/reservas', $this->datosDeReserva())->assertStatus(201);
        $solicitud = $this->crearSolicitud($this->ficha);

        $this->postJson('/api/solicitudes-cita/' . $solicitud->id . '/aceptar', $this->datosDeReserva([
            'hora_inicio' => '10:30',
            'hora_fin' => '11:30',
        ]))->assertStatus(422);

        $solicitud->refresh();
        $this->assertSame('pendiente', $solicitud->estado);
        $this->assertNull($solicitud->reserva_id);
        $this->assertSame(1, Reserva::count());
    }

    public function test_no_se_responde_dos_veces_y_rechazar_exige_motivo(): void
    {
        $solicitud = $this->crearSolicitud($this->ficha);
        $this->actingAs($this->duena);

        $this->postJson('/api/solicitudes-cita/' . $solicitud->id . '/rechazar', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo_rechazo');

        $this->postJson('/api/solicitudes-cita/' . $solicitud->id . '/rechazar', ['motivo_rechazo' => 'Ese día el salón está cerrado.'])
            ->assertStatus(200)
            ->assertJsonPath('data.estado', 'rechazada');

        $this->postJson('/api/solicitudes-cita/' . $solicitud->id . '/rechazar', ['motivo_rechazo' => 'Otra vez'])
            ->assertStatus(422);
        $this->postJson('/api/solicitudes-cita/' . $solicitud->id . '/aceptar', $this->datosDeReserva())
            ->assertStatus(422);
        $this->assertSame(0, Reserva::count());

        // La clienta ve el motivo
        $this->actingAs($this->clienta)
            ->getJson('/api/mi-cuenta/solicitudes')
            ->assertJsonPath('data.0.motivo_rechazo', 'Ese día el salón está cerrado.');
    }

    public function test_la_bandeja_lista_pendientes_y_cuenta_cuantas_hay(): void
    {
        $this->crearSolicitud($this->ficha);
        $this->crearSolicitud($this->ficha);
        $this->crearSolicitud($this->ficha, 'rechazada');

        $this->actingAs($this->duena)
            ->getJson('/api/solicitudes-cita')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('pendientes', 2)
            ->assertJsonPath('data.0.cliente.nombre', 'Lucía')
            ->assertJsonPath('data.0.servicios.0.id', 1);

        $this->getJson('/api/solicitudes-cita?estado=rechazada')->assertJsonCount(1, 'data');
    }

    public function test_una_profesional_no_ve_la_bandeja(): void
    {
        $carla = User::factory()->create(['rol_id' => Role::firstOrCreate(['nombre' => 'profesional'])->id, 'personal_id' => 2]);
        $solicitud = $this->crearSolicitud($this->ficha);

        $this->actingAs($carla);
        $this->getJson('/api/solicitudes-cita')->assertStatus(403);
        $this->postJson('/api/solicitudes-cita/' . $solicitud->id . '/aceptar', $this->datosDeReserva(['personal_id' => 2]))
            ->assertStatus(403);
    }

    public function test_la_clienta_ve_los_horarios_libres_del_salon_sin_saber_quien_atiende(): void
    {
        $lunes = $this->proximoLunes();
        // Mónica ocupada de 10 a 11: a las 10 igual hay lugar porque Carla está libre
        $this->actingAs($this->duena)->postJson('/api/reservas', $this->datosDeReserva())->assertStatus(201);

        $horarios = $this->actingAs($this->clienta)
            ->getJson('/api/mi-cuenta/horarios?' . http_build_query(['fecha' => $lunes, 'servicios' => [1]]))
            ->assertStatus(200)
            ->assertJsonPath('data.salon_abre', true)
            ->json('data.horarios');

        $diezDeLaManana = collect($horarios)->firstWhere('hora', '10:00');
        $this->assertTrue($diezDeLaManana['disponible']);
        $this->assertArrayNotHasKey('profesionales', $diezDeLaManana);
    }

    public function test_un_horario_que_nadie_puede_atender_no_se_pide(): void
    {
        // Todas las profesionales activas ocupadas de 10 a 11
        $profesionales = \App\Models\Personal::where('activo', true)->pluck('id');
        $this->actingAs($this->duena);
        foreach ($profesionales as $indice => $personalId) {
            $this->postJson('/api/reservas', $this->datosDeReserva(['personal_id' => $personalId]))->assertStatus(201);
        }

        $this->actingAs($this->clienta)
            ->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['hora_preferida' => '10:00']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('hora_preferida');

        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['hora_preferida' => '14:00']))
            ->assertStatus(201)
            ->assertJsonPath('data.hora_preferida', '14:00')
            ->assertJsonPath('data.franja', 'tarde');
    }

    public function test_un_sabado_el_salon_no_abre_pero_se_puede_pedir_fuera_de_horario(): void
    {
        $sabado = Carbon::parse('next saturday')->toDateString();
        $this->actingAs($this->clienta);

        $this->getJson('/api/mi-cuenta/horarios?' . http_build_query(['fecha' => $sabado, 'servicios' => [1]]))
            ->assertStatus(200)
            ->assertJsonPath('data.salon_abre', false)
            ->assertJsonCount(0, 'data.horarios');

        $fueraDeHorario = $this->getJson('/api/mi-cuenta/horarios?' . http_build_query(['fecha' => $sabado, 'servicios' => [1], 'fuera_de_horario' => 1]))
            ->assertStatus(200)
            ->json('data.horarios');
        $this->assertSame('06:00', $fueraDeHorario[0]['hora']);

        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['fecha_preferida' => $sabado, 'hora_preferida' => '15:00']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('hora_preferida');

        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido([
            'fecha_preferida' => $sabado,
            'hora_preferida' => '15:00',
            'fuera_de_horario' => true,
        ]))->assertStatus(201)->assertJsonPath('data.fuera_de_horario', true);
    }

    public function test_si_la_clienta_elige_fuera_de_horario_una_hora_que_cae_dentro_no_queda_marcado(): void
    {
        // El lunes el salón abre de 08:00 a 17:00: las 15:30 están dentro aunque las haya mirado en "fuera de horario"
        $this->actingAs($this->clienta)
            ->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['hora_preferida' => '15:30', 'fuera_de_horario' => true]))
            ->assertStatus(201)
            ->assertJsonPath('data.fuera_de_horario', false);

        // Si la cita termina después del cierre (16:30 + 45 min = 17:15), sí queda fuera de horario
        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['hora_preferida' => '16:30', 'fuera_de_horario' => true]))
            ->assertStatus(201)
            ->assertJsonPath('data.fuera_de_horario', true);
    }

    public function test_la_duena_ve_el_pedido_con_el_horario_actual_del_salon(): void
    {
        // Pedido guardado como "dentro del horario" a las 07:00, por ejemplo antes de que cambiara el horario
        $solicitud = $this->crearSolicitud($this->ficha);
        $solicitud->hora_preferida = '07:00';
        $solicitud->fuera_de_horario = false;
        $solicitud->save();

        // Y otro guardado como "fuera de horario" a las 15:30, que cae dentro
        $otra = $this->crearSolicitud($this->ficha);
        $otra->hora_preferida = '15:30';
        $otra->fuera_de_horario = true;
        $otra->save();

        $this->actingAs($this->duena);
        $this->getJson('/api/solicitudes-cita/' . $solicitud->id)
            ->assertStatus(200)
            ->assertJsonPath('data.fuera_de_horario', true)
            ->assertJsonPath('data.profesionales_libres.0', 1);
        $this->getJson('/api/solicitudes-cita/' . $otra->id)
            ->assertStatus(200)
            ->assertJsonPath('data.fuera_de_horario', false);

        // Aceptarla marcada como fuera de horario a una hora que cae dentro: la reserva queda sin la marca
        $this->postJson('/api/solicitudes-cita/' . $otra->id . '/aceptar', $this->datosDeReserva([
            'hora_inicio' => '15:30',
            'hora_fin' => '16:15',
            'fuera_de_horario' => true,
        ]))->assertStatus(201)->assertJsonPath('data.fuera_de_horario', false);
    }

    public function test_telefono_direccion_y_ubicacion_quedan_guardados_para_el_proximo_pedido(): void
    {
        $this->actingAs($this->clienta)
            ->postJson('/api/mi-cuenta/solicitudes', $this->pedido([
                'a_domicilio' => true,
                'direccion_domicilio' => 'Las Residentas 456, portón verde',
                'telefono' => '0981 222 333',
                'latitud' => -25.3676,
                'longitud' => -57.5927,
            ]))->assertStatus(201);

        $this->getJson('/api/mi-cuenta')
            ->assertJsonPath('data.telefono', '0981 222 333')
            ->assertJsonPath('data.direccion_domicilio', 'Las Residentas 456, portón verde')
            ->assertJsonPath('data.latitud', -25.3676)
            ->assertJsonPath('data.longitud', -57.5927);

        // Un pedido nuevo sin ubicación no borra la que ya estaba
        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['hora_preferida' => '14:00']))->assertStatus(201);
        $this->assertSame(-25.3676, $this->ficha->fresh()->latitud);
    }

    public function test_la_ubicacion_va_completa_y_dentro_de_rango(): void
    {
        $this->actingAs($this->clienta);

        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['latitud' => -25.3676]))
            ->assertStatus(422)->assertJsonValidationErrors('longitud');
        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['latitud' => -125, 'longitud' => -57.5]))
            ->assertStatus(422)->assertJsonValidationErrors('latitud');
    }

    public function test_al_agendar_la_duena_ve_quien_esta_libre_a_la_hora_pedida(): void
    {
        $solicitud = $this->crearSolicitud($this->ficha);
        $solicitud->hora_preferida = '10:00';
        $solicitud->save();
        // Mónica (id 1) ya tiene una cita a esa hora
        $this->actingAs($this->duena)->postJson('/api/reservas', $this->datosDeReserva())->assertStatus(201);

        $libres = $this->getJson('/api/solicitudes-cita/' . $solicitud->id)
            ->assertStatus(200)
            ->json('data.profesionales_libres');

        $this->assertNotContains(1, $libres);
        $this->assertNotEmpty($libres);
    }

    public function test_a_domicilio_la_clienta_no_ve_horas_pegadas_a_otra_cita_por_el_traslado(): void
    {
        $lunes = $this->proximoLunes();
        // Las dos profesionales ocupadas de 10 a 11 en el salón
        $this->actingAs($this->duena);
        foreach ([1, 2] as $personalId) {
            $this->postJson('/api/reservas', $this->datosDeReserva(['personal_id' => $personalId]))->assertStatus(201);
        }

        $this->actingAs($this->clienta);
        $enElSalon = collect($this->getJson('/api/mi-cuenta/horarios?' . http_build_query(['fecha' => $lunes, 'servicios' => [1]]))
            ->assertStatus(200)->json('data.horarios'))->keyBy('hora');
        $aDomicilio = collect($this->getJson('/api/mi-cuenta/horarios?' . http_build_query(['fecha' => $lunes, 'servicios' => [1], 'a_domicilio' => 1]))
            ->assertStatus(200)->json('data.horarios'))->keyBy('hora');

        // En el salón, a las 11 ya se puede; a domicilio falta el viaje (30 min antes y después)
        $this->assertTrue($enElSalon['11:00']['disponible']);
        $this->assertFalse($aDomicilio['11:00']['disponible']);
        $this->assertTrue($aDomicilio['11:30']['disponible']);

        // Y la hora que se ofrece es la que la dueña puede aceptar con el traslado sugerido
        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido([
            'hora_preferida' => '11:00',
            'a_domicilio' => true,
            'direccion_domicilio' => 'Las Residentas 456',
        ]))->assertStatus(422)->assertJsonValidationErrors('hora_preferida');

        $solicitudId = $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido([
            'hora_preferida' => '11:30',
            'a_domicilio' => true,
            'direccion_domicilio' => 'Las Residentas 456',
        ]))->assertStatus(201)->json('data.id');

        $this->actingAs($this->duena);
        $detalle = $this->getJson('/api/solicitudes-cita/' . $solicitudId)->assertStatus(200);
        $this->assertSame(\App\Services\ReservaService::TRASLADO_ESTIMADO, $detalle->json('data.traslado_sugerido'));
        $sugerida = $detalle->json('data.profesionales_libres.0');

        $this->postJson('/api/solicitudes-cita/' . $solicitudId . '/aceptar', $this->datosDeReserva([
            'personal_id' => $sugerida,
            'hora_inicio' => '11:30',
            'hora_fin' => '12:15',
            'a_domicilio' => true,
            'direccion_domicilio' => 'Las Residentas 456',
            'minutos_traslado' => $detalle->json('data.traslado_sugerido'),
        ]))->assertStatus(201);
    }

    public function test_la_hora_esta_libre_solo_si_esta_libre_quien_hace_el_servicio(): void
    {
        $lunes = $this->proximoLunes();
        // Coloración (id 3) pide Colorimetría: solo la tiene Mónica (id 1). Mónica ocupada de 10 a 11
        $this->actingAs($this->duena)->postJson('/api/reservas', $this->datosDeReserva())->assertStatus(201);

        $this->actingAs($this->clienta);
        $coloracion = collect($this->getJson('/api/mi-cuenta/horarios?' . http_build_query(['fecha' => $lunes, 'servicios' => [3]]))
            ->assertStatus(200)->json('data.horarios'))->keyBy('hora');
        $corte = collect($this->getJson('/api/mi-cuenta/horarios?' . http_build_query(['fecha' => $lunes, 'servicios' => [1]]))
            ->assertStatus(200)->json('data.horarios'))->keyBy('hora');

        // Carla está libre a las 10, pero no hace coloración; el corte sí lo hace
        $this->assertFalse($coloracion['10:00']['disponible']);
        $this->assertTrue($corte['10:00']['disponible']);

        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['servicios' => [3], 'hora_preferida' => '10:00']))
            ->assertStatus(422)->assertJsonValidationErrors('hora_preferida');
    }

    public function test_si_nadie_hace_todos_los_servicios_juntos_se_ofrecen_las_horas_de_todas(): void
    {
        // Coloración (Colorimetría, solo Mónica) + Peinado para eventos (solo Carla): nadie hace las dos
        $peinado = \App\Models\Servicio::where('nombre', 'Peinado para eventos')->value('id');

        $horarios = $this->actingAs($this->clienta)
            ->getJson('/api/mi-cuenta/horarios?' . http_build_query(['fecha' => $this->proximoLunes(), 'servicios' => [3, $peinado]]))
            ->assertStatus(200)
            ->json('data.horarios');

        // La dueña decide al aceptar; a la clienta no se le cierra el día
        $this->assertTrue(collect($horarios)->contains('disponible', true));
    }

    public function test_a_domicilio_alcanza_con_la_direccion_o_con_la_ubicacion(): void
    {
        $this->actingAs($this->clienta);

        // Ninguna de las dos: no se sabe adónde ir
        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['a_domicilio' => true]))
            ->assertStatus(422)->assertJsonValidationErrors('direccion_domicilio');

        // Solo el mapa: vale, y la dirección queda dicha para la reserva
        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido([
            'a_domicilio' => true,
            'latitud' => -25.3676,
            'longitud' => -57.5927,
        ]))->assertStatus(201)
            ->assertJsonPath('data.direccion_domicilio', \App\Http\Controllers\MiCuentaController::DIRECCION_POR_MAPA);

        // El texto de "solo mapa" no se guarda como la dirección de su casa
        $this->assertNull($this->ficha->fresh()->direccion_domicilio);

        // Solo la dirección escrita: también vale
        $this->postJson('/api/mi-cuenta/solicitudes', $this->pedido([
            'hora_preferida' => '14:00',
            'a_domicilio' => true,
            'direccion_domicilio' => 'Las Residentas 456',
        ]))->assertStatus(201);
    }

    public function test_al_pedir_una_cita_la_duena_recibe_un_aviso_por_telegram(): void
    {
        config(['services.telegram.token' => 'token-de-prueba', 'services.telegram.chat_id' => '123456']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->actingAs($this->clienta)
            ->postJson('/api/mi-cuenta/solicitudes', $this->pedido(['servicios' => [1, 2], 'telefono' => '0981 555 123']))
            ->assertStatus(201);

        Http::assertSent(function ($pedido) {
            return $pedido->url() === 'https://api.telegram.org/bottoken-de-prueba/sendMessage'
                && $pedido['chat_id'] === '123456'
                && str_contains($pedido['text'], 'Nuevo pedido de cita')
                && str_contains($pedido['text'], 'Lucía Prueba (0981 555 123)')
                && str_contains($pedido['text'], 'a las 10:00');
        });
    }

    public function test_si_telegram_falla_el_pedido_queda_guardado_igual(): void
    {
        config(['services.telegram.token' => 'token-de-prueba', 'services.telegram.chat_id' => '123456']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'chat not found'], 400)]);

        $this->actingAs($this->clienta)
            ->postJson('/api/mi-cuenta/solicitudes', $this->pedido())
            ->assertStatus(201);

        $this->assertSame(1, SolicitudCita::count());
    }

    public function test_sin_telegram_configurado_no_se_llama_a_telegram(): void
    {
        config(['services.telegram.token' => null, 'services.telegram.chat_id' => null]);
        Http::fake();

        $this->actingAs($this->clienta)->postJson('/api/mi-cuenta/solicitudes', $this->pedido())->assertStatus(201);

        Http::assertNothingSent();
    }
}
