<?php

namespace Tests\Feature;

use App\Models\CierreSalon;
use App\Models\Cliente;
use App\Models\Descuento;
use App\Models\Especialidad;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\SolicitudCita;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AvanceDosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Promociones de ciertos días de la semana, días puntuales sin atención y la especialidad
 * que pide cada servicio.
 */
class AgendaAjustesTest extends TestCase
{
    use RefreshDatabase;

    private User $duena;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AvanceDosSeeder::class);
        $this->duena = User::factory()->create(['rol_id' => Role::firstOrCreate(['nombre' => 'admin'])->id]);
        $this->actingAs($this->duena);
    }

    private function dia(string $nombre): string
    {
        return Carbon::parse('next ' . $nombre)->toDateString();
    }

    private function reserva(array $cambios = []): array
    {
        return array_merge([
            'cliente_id' => 1,
            'personal_id' => 1,
            'fecha' => $this->dia('monday'),
            'hora_inicio' => '10:00',
            'hora_fin' => '11:00',
            'servicios' => [1],
        ], $cambios);
    }

    // ---------- Promociones por día de la semana ----------

    public function test_una_promocion_puede_regir_solo_los_miercoles(): void
    {
        $respuesta = $this->postJson('/api/descuentos', [
            'nombre' => 'Miércoles de color',
            'tipo' => 'porcentaje',
            'valor' => 15,
            'dias_semana' => [3],
        ])->assertStatus(201)->assertJsonPath('data.dias_semana', [3]);

        $promoId = $respuesta->json('data.id');

        $this->postJson('/api/reservas', $this->reserva(['descuento_id' => $promoId]))
            ->assertStatus(422)->assertJsonValidationErrors('descuento_id');

        $this->postJson('/api/reservas', $this->reserva(['fecha' => $this->dia('wednesday'), 'descuento_id' => $promoId]))
            ->assertStatus(201)->assertJsonPath('data.descuento.dias_semana', [3]);
    }

    public function test_sin_dias_la_promocion_rige_todos_los_dias_y_se_pueden_cambiar(): void
    {
        $promo = Descuento::where('nombre', 'Promo Primavera')->first();
        $this->assertSame([], $promo->dias_semana);

        $this->putJson('/api/descuentos/' . $promo->id, ['dias_semana' => [1, 3, 3]])
            ->assertStatus(422)->assertJsonValidationErrors('dias_semana.2');

        $this->putJson('/api/descuentos/' . $promo->id, ['dias_semana' => [5, 1]])
            ->assertStatus(200)->assertJsonPath('data.dias_semana', [1, 5]);

        // Lista vacía: vuelve a todos los días
        $this->putJson('/api/descuentos/' . $promo->id, ['dias_semana' => []])
            ->assertStatus(200)->assertJsonPath('data.dias_semana', []);

        // Cambiar otro dato sin mandar días no los borra
        $this->putJson('/api/descuentos/' . $promo->id, ['dias_semana' => [2]])->assertStatus(200);
        $this->putJson('/api/descuentos/' . $promo->id, ['nombre' => 'Promo Primavera 2'])
            ->assertStatus(200)->assertJsonPath('data.dias_semana', [2]);
    }

    public function test_la_pagina_publica_anuncia_la_promocion_de_los_miercoles_toda_la_semana(): void
    {
        $promo = Descuento::create(['nombre' => 'Miércoles de color', 'tipo' => 'porcentaje', 'valor' => 15]);
        $promo->guardarDias([3]);

        $nombres = collect($this->getJson('/api/promociones')->assertStatus(200)->json('data'))->pluck('nombre');
        $this->assertContains('Miércoles de color', $nombres);

        // Para aplicarla a una reserva, en cambio, se mira el día: solo_vigentes es "hoy"
        $hoyEsMiercoles = now()->dayOfWeekIso === 3;
        $vigentesHoy = collect($this->getJson('/api/descuentos?solo_vigentes=1')->json('data'))->pluck('nombre');
        $this->assertSame($hoyEsMiercoles, $vigentesHoy->contains('Miércoles de color'));
    }

    // ---------- Días puntuales sin atención ----------

    public function test_la_duena_cierra_un_dia_puntual_y_se_avisa_si_ya_tenia_citas(): void
    {
        $lunes = $this->dia('monday');
        $this->postJson('/api/reservas', $this->reserva())->assertStatus(201);

        $this->postJson('/api/cierres-salon', ['fecha' => $lunes, 'motivo' => 'Capacitación'])
            ->assertStatus(201)
            ->assertJsonPath('citas_afectadas', 1)
            ->assertJsonPath('data.motivo', 'Capacitación');

        $this->postJson('/api/cierres-salon', ['fecha' => $lunes])
            ->assertStatus(422)->assertJsonValidationErrors('fecha');
        $this->postJson('/api/cierres-salon', ['fecha' => now()->subDay()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('fecha');

        $this->getJson('/api/cierres-salon')->assertStatus(200)->assertJsonPath('data.0.fecha', $lunes);
    }

    public function test_un_dia_cerrado_no_admite_citas_ni_fuera_de_horario_y_no_afecta_a_los_otros_lunes(): void
    {
        $lunes = $this->dia('monday');
        $lunesSiguiente = Carbon::parse($lunes)->addWeek()->toDateString();
        CierreSalon::create(['fecha' => $lunes, 'motivo' => 'Feriado del salón']);

        $this->postJson('/api/reservas', $this->reserva())
            ->assertStatus(422)->assertJsonValidationErrors('horario');
        $this->postJson('/api/reservas', $this->reserva(['fuera_de_horario' => true, 'hora_inicio' => '19:00', 'hora_fin' => '20:00']))
            ->assertStatus(422)->assertJsonValidationErrors('horario');

        $this->getJson('/api/disponibilidad?' . http_build_query(['personal_id' => 1, 'fecha' => $lunes, 'duracion_minutos' => 60, 'fuera_de_horario' => 1]))
            ->assertStatus(200)->assertJsonCount(0, 'data.horarios');

        // El cierre es de ese día: el lunes siguiente se atiende normal
        $this->postJson('/api/reservas', $this->reserva(['fecha' => $lunesSiguiente]))->assertStatus(201);
    }

    public function test_quitar_el_cierre_vuelve_a_abrir_el_dia(): void
    {
        $cierre = CierreSalon::create(['fecha' => $this->dia('monday')]);

        $this->deleteJson('/api/cierres-salon/' . $cierre->id)->assertStatus(200);

        $this->postJson('/api/reservas', $this->reserva())->assertStatus(201);
    }

    public function test_la_clienta_ve_que_ese_dia_no_se_trabaja(): void
    {
        $lunes = $this->dia('monday');
        CierreSalon::create(['fecha' => $lunes, 'motivo' => 'Capacitación']);

        $clienta = User::factory()->create(['rol_id' => Role::firstOrCreate(['nombre' => 'cliente'])->id]);
        $ficha = Cliente::create(['nombre' => 'Lucía', 'apellido' => 'Prueba']);
        $ficha->user_id = $clienta->id;
        $ficha->save();

        $this->actingAs($clienta)
            ->getJson('/api/mi-cuenta/horarios?' . http_build_query(['fecha' => $lunes, 'servicios' => [1], 'fuera_de_horario' => 1]))
            ->assertStatus(200)
            ->assertJsonPath('data.salon_abre', false)
            ->assertJsonPath('data.cerrado.motivo', 'Capacitación')
            ->assertJsonCount(0, 'data.horarios');
    }

    public function test_solo_la_administracion_cierra_dias(): void
    {
        $carla = User::factory()->create(['rol_id' => Role::firstOrCreate(['nombre' => 'profesional'])->id, 'personal_id' => 2]);

        $this->actingAs($carla)->postJson('/api/cierres-salon', ['fecha' => $this->dia('monday')])->assertStatus(403);
    }

    public function test_la_duena_cierra_un_periodo_de_vacaciones(): void
    {
        $lunes = $this->dia('monday');
        $miercoles = Carbon::parse($lunes)->addDays(2)->toDateString();
        $viernes = Carbon::parse($lunes)->addDays(4)->toDateString();
        $lunesSiguiente = Carbon::parse($lunes)->addWeek()->toDateString();
        $this->postJson('/api/reservas', $this->reserva(['fecha' => $miercoles]))->assertStatus(201);

        $this->postJson('/api/cierres-salon', ['fecha' => $lunes, 'fecha_hasta' => $viernes, 'motivo' => 'Vacaciones'])
            ->assertStatus(201)
            ->assertJsonPath('data.fecha', $lunes)
            ->assertJsonPath('data.fecha_hasta', $viernes)
            ->assertJsonPath('citas_afectadas', 1);

        // Todos los días del período quedan cerrados, también el del medio
        foreach ([$lunes, $miercoles, $viernes] as $fecha) {
            $this->postJson('/api/reservas', $this->reserva(['fecha' => $fecha]))
                ->assertStatus(422)->assertJsonValidationErrors('horario');
            $this->getJson('/api/disponibilidad?' . http_build_query(['personal_id' => 1, 'fecha' => $fecha, 'duracion_minutos' => 60]))
                ->assertStatus(200)->assertJsonCount(0, 'data.horarios');
        }

        // Al día siguiente de volver se atiende normal
        $this->postJson('/api/reservas', $this->reserva(['fecha' => $lunesSiguiente]))->assertStatus(201);

        $this->getJson('/api/cierres-salon')->assertStatus(200)->assertJsonPath('data.0.fecha_hasta', $viernes);
    }

    public function test_un_solo_dia_se_guarda_sin_fecha_hasta(): void
    {
        $lunes = $this->dia('monday');

        $this->postJson('/api/cierres-salon', ['fecha' => $lunes, 'fecha_hasta' => $lunes])
            ->assertStatus(201)
            ->assertJsonPath('data.fecha_hasta', null);
    }

    public function test_el_periodo_no_va_al_reves_ni_se_cruza_con_otro_cierre(): void
    {
        $lunes = $this->dia('monday');
        $jueves = Carbon::parse($lunes)->addDays(3)->toDateString();

        // El último día antes del primero
        $this->postJson('/api/cierres-salon', ['fecha' => $jueves, 'fecha_hasta' => $lunes])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_hasta');

        // Un período demasiado largo (probablemente se eligió mal el año)
        $this->postJson('/api/cierres-salon', ['fecha' => $lunes, 'fecha_hasta' => Carbon::parse($lunes)->addYear()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_hasta');

        CierreSalon::create(['fecha' => Carbon::parse($lunes)->addDays(2)->toDateString()]);

        // Un período que incluye el miércoles ya cerrado no se guarda
        $this->postJson('/api/cierres-salon', ['fecha' => $lunes, 'fecha_hasta' => $jueves])
            ->assertStatus(422)->assertJsonValidationErrors('fecha');
        $this->assertSame(1, CierreSalon::count());

        // Un día suelto dentro de un período cargado tampoco
        CierreSalon::query()->delete();
        CierreSalon::create(['fecha' => $lunes, 'fecha_hasta' => $jueves]);
        $this->postJson('/api/cierres-salon', ['fecha' => Carbon::parse($lunes)->addDay()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('fecha');

        // Justo después del período, sí
        $this->postJson('/api/cierres-salon', ['fecha' => Carbon::parse($jueves)->addDay()->toDateString()])
            ->assertStatus(201);
    }

    public function test_un_periodo_que_ya_empezo_sigue_listado_y_quitarlo_reabre_todos_sus_dias(): void
    {
        $ayer = now()->subDay()->toDateString();
        $manana = now()->addDay()->toDateString();
        $cierre = CierreSalon::create(['fecha' => $ayer, 'fecha_hasta' => now()->addDays(3)->toDateString(), 'motivo' => 'Accidente']);

        $this->getJson('/api/cierres-salon')->assertStatus(200)->assertJsonPath('data.0.motivo', 'Accidente');
        $this->assertNotNull(CierreSalon::delDia(now()->toDateString()));
        $this->assertNotNull(CierreSalon::delDia($manana));

        $this->deleteJson('/api/cierres-salon/' . $cierre->id)->assertStatus(200);

        $this->assertNull(CierreSalon::delDia($manana));
    }

    // ---------- Especialidad que pide cada servicio ----------

    public function test_los_servicios_llevan_la_especialidad_que_piden(): void
    {
        $colorimetria = Especialidad::where('nombre', 'Colorimetría')->first();

        $balayage = collect($this->getJson('/api/servicios')->json('data'))->firstWhere('nombre', 'Balayage');
        $this->assertSame($colorimetria->id, $balayage['especialidad_id']);
        $this->assertSame('Colorimetría', $balayage['especialidad']['nombre']);

        $creado = $this->postJson('/api/servicios', [
            'categoria_id' => 1,
            'especialidad_id' => $colorimetria->id,
            'nombre' => 'Baño de color',
            'duracion_minutos' => 60,
            'precio' => 90000,
        ])->assertStatus(201);
        $servicioId = Servicio::where('nombre', 'Baño de color')->value('id');
        $this->assertSame($colorimetria->id, Servicio::find($servicioId)->especialidad_id);

        $this->putJson('/api/servicios/' . $servicioId, ['especialidad_id' => null])->assertStatus(200);
        $this->assertNull(Servicio::find($servicioId)->especialidad_id);
    }

    public function test_al_agendar_un_pedido_se_sugiere_primero_quien_tiene_la_especialidad(): void
    {
        // Balayage pide Colorimetría: la tiene Mónica (id 1), no Carla (id 2)
        $balayage = Servicio::where('nombre', 'Balayage')->first();
        $ficha = Cliente::create(['nombre' => 'Ana', 'apellido' => 'Prueba']);
        $solicitud = SolicitudCita::create([
            'cliente_id' => $ficha->id,
            'fecha_preferida' => $this->dia('monday'),
            'hora_preferida' => '09:00',
            'franja' => 'manana',
            'estado' => 'pendiente',
        ]);
        $solicitud->servicios()->attach([$balayage->id]);

        $libres = $this->getJson('/api/solicitudes-cita/' . $solicitud->id)->assertStatus(200)->json('data.profesionales_libres');

        $this->assertSame(1, $libres[0]);
    }
}
