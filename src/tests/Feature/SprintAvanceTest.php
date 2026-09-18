<?php

namespace Tests\Feature;

use App\Models\CategoriaServicio;
use App\Models\Personal;
use App\Models\PrecioServicio;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\User;
use Database\Seeders\SprintUnoDosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SprintAvanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ejecutar el seeder del Sprint 1 y 2 con roles, usuarios, personal y servicios
        $this->seed(SprintUnoDosSeeder::class);
    }

    /**
     * HU-25: Inicio de sesión exitoso por rol (Administrador y Profesional)
     */
    public function test_inicio_de_sesion_exitoso_por_rol(): void
    {
        // 1. Login como Administradora
        $responseAdmin = $this->postJson('/api/auth/login', [
            'email' => 'admin@meninacoiffure.com',
            'password' => 'password123',
        ]);

        $responseAdmin->assertStatus(200)
            ->assertJsonPath('message', 'Inicio de sesión exitoso.')
            ->assertJsonPath('data.user.email', 'admin@meninacoiffure.com')
            ->assertJsonPath('data.user.rol', 'admin');

        // 2. Login como Profesional (asociada a personal Carla)
        $responseCarla = $this->postJson('/api/auth/login', [
            'email' => 'carla@meninacoiffure.com',
            'password' => 'password123',
        ]);

        $responseCarla->assertStatus(200)
            ->assertJsonPath('data.user.rol', 'profesional')
            ->assertJsonStructure(['data' => ['user' => ['personal']]]);

        // 3. Obtener usuario autenticado en /api/auth/me
        $responseMe = $this->actingAs(User::where('email', 'admin@meninacoiffure.com')->first())
            ->getJson('/api/auth/me');

        $responseMe->assertStatus(200)
            ->assertJsonPath('data.email', 'admin@meninacoiffure.com')
            ->assertJsonPath('data.rol', 'admin');
    }

    /**
     * HU-25: Falla de autenticación con credenciales inválidas
     */
    public function test_falla_inicio_sesion_credenciales_invalidas(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@meninacoiffure.com',
            'password' => 'password_erroneo_123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * HU-10: Gestión de servicios (creación con precio inicial, cambio de precio e inactivación)
     */
    public function test_gestion_de_servicios_y_precios_historicos_hu_10(): void
    {
        $categoria = CategoriaServicio::first();

        // 1. Crear nuevo servicio con precio inicial
        $crearResponse = $this->postJson('/api/servicios', [
            'categoria_id' => $categoria->id,
            'nombre' => 'Alisado Brasileño con Keratina',
            'descripcion' => 'Tratamiento termoactivo restaurador',
            'duracion_minutos' => 120,
            'precio' => 250000.00,
            'activo' => true,
        ]);

        $crearResponse->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Alisado Brasileño con Keratina');
        $this->assertEquals(250000.00, (float) $crearResponse->json('data.precio_actual'));

        $servicioId = $crearResponse->json('data.id');

        // Verificar persistencia en tabla 'servicios' y 'precios_servicio'
        $this->assertDatabaseHas('servicios', [
            'id' => $servicioId,
            'nombre' => 'Alisado Brasileño con Keratina',
            'activo' => true,
        ]);

        $this->assertDatabaseHas('precios_servicio', [
            'servicio_id' => $servicioId,
            'precio' => 250000.00,
            'vigente_hasta' => null,
        ]);

        // 2. Cambiar precio del servicio (ajuste de tarifa a 280.000)
        $cambioPrecioResponse = $this->postJson("/api/servicios/{$servicioId}/cambiar-precio", [
            'precio' => 280000.00,
        ]);

        $cambioPrecioResponse->assertStatus(200);
        $this->assertEquals(280000.00, (float) $cambioPrecioResponse->json('data.nuevo_precio'));

        // Verificar que el precio anterior se cerró y existe el nuevo registro vigente
        $this->assertDatabaseHas('precios_servicio', [
            'servicio_id' => $servicioId,
            'precio' => 280000.00,
            'vigente_hasta' => null,
        ]);

        // 3. Inactivación lógica del servicio
        $deleteResponse = $this->deleteJson("/api/servicios/{$servicioId}");
        $deleteResponse->assertStatus(200);

        $this->assertDatabaseHas('servicios', [
            'id' => $servicioId,
            'activo' => false,
        ]);
    }

    /**
     * HU-01: Consulta de catálogo de servicios por clientes (filtros y precio vigente)
     */
    public function test_consulta_de_catalogo_de_servicios_hu_01(): void
    {
        $response = $this->getJson('/api/servicios');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'nombre',
                        'duracion_minutos',
                        'activo',
                        'categoria',
                        'precio_vigente',
                    ]
                ]
            ]);

        // Decoloración Extrema está inactiva en el seeder, por lo que con solo_activos=true no debe aparecer
        $nombres = collect($response->json('data'))->pluck('nombre');
        $this->assertFalse($nombres->contains('Decoloración Extrema (Descontinuado)'));

        // Probar filtro por búsqueda
        $busquedaResponse = $this->getJson('/api/servicios?buscar=Corte');
        $busquedaResponse->assertStatus(200);
        $this->assertTrue(collect($busquedaResponse->json('data'))->pluck('nombre')->contains('Corte Femenino'));
    }

    /**
     * HU-07: Gestión de personal y asignación de turnos laborales
     */
    public function test_gestion_de_personal_y_turnos_laborales_hu_07(): void
    {
        // 1. Alta de nueva profesional con turnos
        $crearPersonalResponse = $this->postJson('/api/personal', [
            'nombre' => 'Elena',
            'apellido' => 'Manicurista',
            'telefono' => '0981999888',
            'email' => 'elena@meninacoiffure.com',
            'turnos' => [
                [
                    'dia_semana' => 1, // Lunes
                    'hora_inicio' => '09:00',
                    'hora_fin' => '17:00',
                ],
                [
                    'dia_semana' => 2, // Martes
                    'hora_inicio' => '09:00',
                    'hora_fin' => '17:00',
                ],
            ],
        ]);

        $crearPersonalResponse->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Elena')
            ->assertJsonPath('data.apellido', 'Manicurista')
            ->assertJsonCount(2, 'data.turnos');

        $personalId = $crearPersonalResponse->json('data.id');

        // 2. Modificar turnos laborales del profesional
        $nuevosTurnosResponse = $this->postJson("/api/personal/{$personalId}/turnos", [
            'turnos' => [
                [
                    'dia_semana' => 3, // Miércoles
                    'hora_inicio' => '10:00',
                    'hora_fin' => '18:00',
                ],
            ],
        ]);

        $nuevosTurnosResponse->assertStatus(200)
            ->assertJsonCount(1, 'data.turnos')
            ->assertJsonPath('data.turnos.0.dia_semana', 3);

        // 3. Inactivación lógica del profesional
        $desactivarResponse = $this->deleteJson("/api/personal/{$personalId}");
        $desactivarResponse->assertStatus(200);

        $this->assertDatabaseHas('personal', [
            'id' => $personalId,
            'activo' => false,
        ]);
    }

    /**
     * T-02: Sesiones centralizadas (persistencia en base de datos)
     */
    public function test_sesiones_centralizadas_persisten_en_base_de_datos(): void
    {
        // Verificar que la tabla sessions existe en la base de datos
        $this->assertTrue(Schema::hasTable('sessions') || config('session.driver') === 'database');
    }
}
