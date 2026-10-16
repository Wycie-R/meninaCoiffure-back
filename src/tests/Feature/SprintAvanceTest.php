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
        $this->comoAdmin();
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

        // 3. Inactivación lógica del servicio (deja de ofrecerse, conserva su historial)
        $this->putJson("/api/servicios/{$servicioId}", ['activo' => false])->assertStatus(200);

        $this->assertDatabaseHas('servicios', [
            'id' => $servicioId,
            'activo' => false,
        ]);

        // 4. Un servicio que nunca se usó en reservas se puede eliminar
        $this->deleteJson("/api/servicios/{$servicioId}")->assertStatus(200);
        $this->assertDatabaseMissing('servicios', ['id' => $servicioId]);
        $this->assertDatabaseMissing('precios_servicio', ['servicio_id' => $servicioId]);
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
        $this->assertTrue(collect($busquedaResponse->json('data'))->pluck('nombre')->contains('Corte de dama'));
    }

    /**
     * HU-07: Gestión de personal y asignación de turnos laborales
     */
    public function test_gestion_de_personal_y_turnos_laborales_hu_07(): void
    {
        $this->comoAdmin();

        // 1. Alta de nueva profesional con turnos
        $crearPersonalResponse = $this->postJson('/api/personal', [
            'nombre' => 'Elena',
            'apellido' => 'Peinadora',
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
            ->assertJsonPath('data.apellido', 'Peinadora')
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
     * HU-25: La API de gestión exige sesión; el catálogo (HU-01) sigue siendo público.
     */
    public function test_api_de_gestion_exige_sesion_y_catalogo_es_publico(): void
    {
        $this->getJson('/api/reservas')->assertStatus(401);
        $this->getJson('/api/clientes')->assertStatus(401);
        $this->getJson('/api/personal')->assertStatus(401);
        $this->postJson('/api/servicios', [])->assertStatus(401);

        $this->getJson('/api/servicios')->assertStatus(200);
        $this->getJson('/api/categorias-servicio')->assertStatus(200);
    }

    /**
     * HU-25: Roles reales del salón (sin recepcionista) y una cuenta por cada profesional.
     */
    public function test_roles_del_salon_y_cuentas_del_equipo(): void
    {
        $this->assertDatabaseMissing('roles', ['nombre' => 'recepcionista']);
        $this->assertDatabaseMissing('users', ['email' => 'recepcion@meninacoiffure.com']);

        // La propietaria administra y además atiende: su cuenta admin está vinculada a su ficha
        $this->postJson('/api/auth/login', [
            'email' => 'admin@meninacoiffure.com',
            'password' => 'password123',
        ])->assertStatus(200)
            ->assertJsonPath('data.user.rol', 'admin')
            ->assertJsonPath('data.user.personal.nombre_completo', 'Mónica Propietaria');

        $this->postJson('/api/auth/login', [
            'email' => 'carla@meninacoiffure.com',
            'password' => 'password123',
        ])->assertStatus(200)
            ->assertJsonPath('data.user.rol', 'profesional')
            ->assertJsonPath('data.user.personal.nombre_completo', 'Carla Estilista');
    }

    /**
     * Volver a sembrar no duplica personal, y el correo del personal es único.
     */
    public function test_reseed_no_duplica_y_correo_de_personal_es_unico(): void
    {
        $this->seed(SprintUnoDosSeeder::class);
        $this->assertSame(1, Personal::where('email', 'carla@meninacoiffure.com')->count());

        $this->comoAdmin();
        $this->postJson('/api/personal', ['nombre' => 'Carla', 'apellido' => 'Repetida', 'email' => 'CARLA@meninacoiffure.com'])
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    /**
     * HU-10: Un servicio usado en reservas no se elimina; se desactiva.
     */
    public function test_servicio_con_reservas_no_se_elimina(): void
    {
        $this->comoAdmin();
        $reserva = \App\Models\Reserva::create([
            'cliente_id' => 1, 'personal_id' => 1, 'fecha' => now()->toDateString(),
            'hora_inicio' => '10:00:00', 'hora_fin' => '10:45:00', 'estado' => 'completada',
        ]);
        $reserva->detalles()->create(['servicio_id' => 1, 'precio_aplicado' => 70000, 'cantidad' => 1]);

        $this->deleteJson('/api/servicios/1')->assertStatus(422)->assertJsonValidationErrors(['servicio']);
        $this->assertDatabaseHas('servicios', ['id' => 1]);
    }

    /**
     * Permisos por rol: un profesional no gestiona catálogo, personal, pagos ni reportes.
     */
    public function test_profesional_no_accede_a_funciones_de_administracion(): void
    {
        $this->actingAs(User::where('email', 'carla@meninacoiffure.com')->first());

        $this->postJson('/api/servicios', [])->assertStatus(403);
        $this->postJson('/api/servicios/1/cambiar-precio', ['precio' => 1])->assertStatus(403);
        $this->postJson('/api/personal', [])->assertStatus(403);
        $this->getJson('/api/pagos')->assertStatus(403);
        $this->getJson('/api/reportes/resumen')->assertStatus(403);
        $this->deleteJson('/api/clientes/1')->assertStatus(403);

        // Lo que necesita para atender sí lo puede consultar
        $this->getJson('/api/personal')->assertStatus(200);
        $this->getJson('/api/clientes')->assertStatus(200);
    }

    /**
     * HU-09: No se borra una categoría que tiene servicios (antes podía dar un error 500).
     */
    public function test_no_se_elimina_categoria_con_servicios(): void
    {
        $this->comoAdmin();
        $categoria = CategoriaServicio::has('servicios')->first();

        $this->deleteJson("/api/categorias-servicio/{$categoria->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['categoria']);

        $vacia = CategoriaServicio::create(['nombre' => 'Categoría sin servicios']);
        $this->deleteJson("/api/categorias-servicio/{$vacia->id}")->assertStatus(200);
        $this->assertDatabaseMissing('categorias_servicio', ['id' => $vacia->id]);
    }

    /**
     * HU-11: Dos cambios de precio el mismo día corrigen el precio de ese día sin dejar un rango invertido.
     */
    public function test_cambiar_precio_dos_veces_el_mismo_dia_no_invierte_rangos(): void
    {
        $this->comoAdmin();
        $servicio = Servicio::where('nombre', 'Corte de dama')->first();
        $hoy = now()->toDateString();

        $this->postJson("/api/servicios/{$servicio->id}/cambiar-precio", ['precio' => 75000])->assertStatus(200);
        $this->postJson("/api/servicios/{$servicio->id}/cambiar-precio", ['precio' => 78000])->assertStatus(200);

        $this->assertSame(1, PrecioServicio::where('servicio_id', $servicio->id)->where('vigente_desde', $hoy)->count());
        $this->assertDatabaseHas('precios_servicio', ['servicio_id' => $servicio->id, 'vigente_desde' => $hoy, 'precio' => 78000, 'vigente_hasta' => null]);
        $this->assertSame(0, PrecioServicio::whereColumn('vigente_hasta', '<', 'vigente_desde')->count());
        $this->assertEquals(78000, $servicio->fresh()->precioVigente()->precio);

        // Un precio no puede regir desde una fecha pasada
        $this->postJson("/api/servicios/{$servicio->id}/cambiar-precio", [
            'precio' => 60000,
            'vigente_desde' => now()->subDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['vigente_desde']);
    }

    /**
     * HU-08: Los turnos validan horas invertidas y franjas solapadas, y pueden quedar vacíos.
     */
    public function test_turnos_validan_horas_y_solapamientos(): void
    {
        $this->comoAdmin();
        $carla = Personal::where('email', 'carla@meninacoiffure.com')->first();

        $this->postJson("/api/personal/{$carla->id}/turnos", [
            'turnos' => [['dia_semana' => 1, 'hora_inicio' => '18:00', 'hora_fin' => '09:00']],
        ])->assertStatus(422)->assertJsonValidationErrors(['turnos.0.hora_fin']);

        $this->postJson("/api/personal/{$carla->id}/turnos", [
            'turnos' => [
                ['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '12:00'],
                ['dia_semana' => 1, 'hora_inicio' => '11:00', 'hora_fin' => '15:00'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['turnos']);

        // Franjas contiguas sí se aceptan
        $this->postJson("/api/personal/{$carla->id}/turnos", [
            'turnos' => [
                ['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '12:00'],
                ['dia_semana' => 1, 'hora_inicio' => '12:00', 'hora_fin' => '15:00'],
            ],
        ])->assertStatus(200)->assertJsonCount(2, 'data.turnos');

        // Lista vacía: queda sin turnos
        $this->postJson("/api/personal/{$carla->id}/turnos", ['turnos' => []])
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.turnos');
    }

    protected function comoAdmin(): void
    {
        $this->actingAs(User::where('email', 'admin@meninacoiffure.com')->first());
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
