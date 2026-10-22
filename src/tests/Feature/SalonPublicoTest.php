<?php

namespace Tests\Feature;

use App\Models\Descuento;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SprintUnoDosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Página pública del salón: cualquiera ve el catálogo, el horario y las promociones vigentes
 * sin iniciar sesión. Lo que se modifica sigue reservado a la administración.
 */
class SalonPublicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_raiz_lleva_al_salon_sin_sesion_y_al_panel_con_sesion(): void
    {
        $this->get('/')->assertRedirect('/salon');

        $admin = User::factory()->create(['rol_id' => Role::firstOrCreate(['nombre' => 'admin'])->id]);
        $this->actingAs($admin);
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_la_pagina_del_salon_es_publica_y_recibe_el_whatsapp(): void
    {
        config(['app.salon_whatsapp' => '595981000000']);

        $this->get('/salon')
            ->assertStatus(200)
            ->assertSee('Salon\/Index', false)
            ->assertSee('595981000000', false);
    }

    public function test_catalogo_y_horario_se_consultan_sin_sesion(): void
    {
        $this->seed(SprintUnoDosSeeder::class);

        $this->getJson('/api/servicios')->assertStatus(200);
        $this->getJson('/api/categorias-servicio')->assertStatus(200);
        $this->getJson('/api/horarios-atencion')->assertStatus(200)->assertJsonCount(5, 'data');
    }

    public function test_cambiar_el_horario_o_ver_los_descuentos_sigue_pidiendo_sesion(): void
    {
        $this->putJson('/api/horarios-atencion', ['horarios' => []])->assertStatus(401);
        $this->getJson('/api/descuentos')->assertStatus(401);
    }

    public function test_promociones_publicas_solo_las_vigentes_hoy(): void
    {
        $hoy = now()->toDateString();
        Descuento::create(['nombre' => 'Sin fechas', 'tipo' => 'porcentaje', 'valor' => 10]);
        Descuento::create(['nombre' => 'Este mes', 'tipo' => 'monto_fijo', 'valor' => 20000,
            'fecha_inicio' => now()->subDays(3)->toDateString(), 'fecha_fin' => $hoy]);
        Descuento::create(['nombre' => 'Desactivada', 'tipo' => 'porcentaje', 'valor' => 15, 'activo' => false]);
        Descuento::create(['nombre' => 'Vencida', 'tipo' => 'porcentaje', 'valor' => 15,
            'fecha_fin' => now()->subDay()->toDateString()]);
        Descuento::create(['nombre' => 'Todavía no empieza', 'tipo' => 'porcentaje', 'valor' => 15,
            'fecha_inicio' => now()->addDay()->toDateString()]);

        $respuesta = $this->getJson('/api/promociones')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('message', null)
            ->assertJsonPath('data.0.nombre', 'Este mes')
            ->assertJsonPath('data.1.nombre', 'Sin fechas');

        // La página pública no expone datos internos como el uso en reservas
        $this->assertArrayNotHasKey('reservas_count', $respuesta->json('data.0'));
        $this->assertArrayNotHasKey('activo', $respuesta->json('data.0'));
    }

    public function test_sin_promociones_vigentes_avisa(): void
    {
        Descuento::create(['nombre' => 'Desactivada', 'tipo' => 'porcentaje', 'valor' => 15, 'activo' => false]);

        $this->getJson('/api/promociones')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('message', 'No hay promociones vigentes por el momento.');
    }
}
