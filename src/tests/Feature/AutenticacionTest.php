<?php

namespace Tests\Feature;

use App\Mail\CorreoDelSalon;
use App\Models\Cliente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Login, registro y recuperación de contraseña con Laravel (reemplazan a Keycloak):
 * misma funcionalidad, con las cuentas en la tabla users.
 */
class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(string $rol, string $email, ?string $password = 'password123'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => $password,
            'rol_id' => Role::firstOrCreate(['nombre' => $rol])->id,
        ]);
    }

    private function datosDeRegistro(array $cambios = []): array
    {
        $base = [
            'nombre' => 'Lucía',
            'apellido' => 'Benítez',
            'email' => 'lucia@gmail.com',
            'password' => 'clave-segura',
            'password_confirmation' => 'clave-segura',
        ];

        return array_merge($base, $cambios);
    }

    // ---------- Login ----------

    public function test_el_equipo_entra_y_va_al_panel(): void
    {
        $this->crearUsuario('admin', 'admin@meninacoiffure.com');

        $this->postJson('/api/auth/login', ['email' => 'admin@meninacoiffure.com', 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('data.user.rol', 'admin')
            ->assertJsonPath('data.pagina_inicial', '/dashboard');

        $this->assertAuthenticated();
    }

    public function test_una_clienta_entra_y_va_a_sus_citas(): void
    {
        $this->crearUsuario('cliente', 'clienta@gmail.com');

        $this->postJson('/api/auth/login', ['email' => 'clienta@gmail.com', 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('data.pagina_inicial', '/mi-cuenta');
    }

    public function test_el_correo_no_distingue_mayusculas(): void
    {
        $this->crearUsuario('admin', 'admin@meninacoiffure.com');

        $this->postJson('/api/auth/login', ['email' => '  Admin@MeninaCoiffure.com ', 'password' => 'password123'])
            ->assertOk();
    }

    public function test_con_contrasena_incorrecta_no_entra(): void
    {
        $this->crearUsuario('admin', 'admin@meninacoiffure.com');

        $this->postJson('/api/auth/login', ['email' => 'admin@meninacoiffure.com', 'password' => 'otra-clave'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Las credenciales proporcionadas no son válidas.');

        $this->assertGuest();
    }

    public function test_una_cuenta_sin_contrasena_local_no_entra(): void
    {
        // Cuentas creadas antes con Keycloak: tienen que crear una con "¿Olvidaste tu contraseña?"
        $usuario = $this->crearUsuario('cliente', 'antigua@gmail.com');
        $usuario->forceFill(['password' => null])->saveQuietly();

        $this->postJson('/api/auth/login', ['email' => 'antigua@gmail.com', 'password' => 'cualquiera'])
            ->assertStatus(422);

        $this->assertGuest();
    }

    public function test_seguir_conectado_guarda_el_remember_token(): void
    {
        $usuario = $this->crearUsuario('admin', 'admin@meninacoiffure.com');
        $usuario->forceFill(['remember_token' => null])->saveQuietly();

        $this->postJson('/api/auth/login', [
            'email' => 'admin@meninacoiffure.com',
            'password' => 'password123',
            'recordar' => true,
        ])->assertOk();

        $this->assertNotNull($usuario->fresh()->remember_token);
    }

    public function test_sin_seguir_conectado_no_se_guarda_el_remember_token(): void
    {
        $usuario = $this->crearUsuario('admin', 'admin@meninacoiffure.com');
        $usuario->forceFill(['remember_token' => null])->saveQuietly();

        $this->postJson('/api/auth/login', ['email' => 'admin@meninacoiffure.com', 'password' => 'password123'])
            ->assertOk();

        $this->assertNull($usuario->fresh()->remember_token);
    }

    public function test_despues_de_5_intentos_fallidos_hay_que_esperar(): void
    {
        $this->crearUsuario('admin', 'admin@meninacoiffure.com');

        for ($intento = 1; $intento <= 5; $intento++) {
            $this->postJson('/api/auth/login', ['email' => 'admin@meninacoiffure.com', 'password' => 'mal'])
                ->assertStatus(422);
        }

        // Ni siquiera la contraseña correcta entra mientras dura la espera
        $this->postJson('/api/auth/login', ['email' => 'admin@meninacoiffure.com', 'password' => 'password123'])
            ->assertStatus(429);

        $this->assertGuest();
    }

    public function test_al_salir_se_cierra_la_sesion(): void
    {
        $usuario = $this->crearUsuario('admin', 'admin@meninacoiffure.com');

        $this->actingAs($usuario)->postJson('/api/auth/logout')->assertOk();

        $this->assertGuest('web');
    }

    // ---------- Registro ----------

    public function test_una_clienta_crea_su_cuenta_con_su_ficha_y_queda_adentro(): void
    {
        $this->postJson('/api/auth/registro', $this->datosDeRegistro(['email' => 'Lucia@Gmail.com']))
            ->assertStatus(201)
            ->assertJsonPath('data.pagina_inicial', '/mi-cuenta');

        $usuario = User::where('email', 'lucia@gmail.com')->first();
        $this->assertNotNull($usuario);
        $this->assertTrue($usuario->hasRole('cliente'));
        $this->assertSame('Lucía Benítez', $usuario->name);
        // Se guarda hasheada, nunca en texto plano
        $this->assertNotSame('clave-segura', $usuario->password);

        $ficha = $usuario->cliente;
        $this->assertNotNull($ficha);
        $this->assertSame('Lucía', $ficha->nombre);
        $this->assertSame('Benítez', $ficha->apellido);

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_no_se_puede_registrar_un_correo_que_ya_tiene_cuenta(): void
    {
        $this->crearUsuario('admin', 'lucia@gmail.com');

        $this->postJson('/api/auth/registro', $this->datosDeRegistro(['email' => 'LUCIA@gmail.com']))
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Ya hay una cuenta con ese correo. Prueba iniciar sesión.');
    }

    public function test_las_contrasenas_tienen_que_coincidir(): void
    {
        $this->postJson('/api/auth/registro', $this->datosDeRegistro(['password_confirmation' => 'otra-clave']))
            ->assertStatus(422)
            ->assertJsonPath('errors.password.0', 'Las contraseñas no coinciden.');

        $this->assertSame(0, User::count());
    }

    public function test_nombre_apellido_y_correo_son_obligatorios(): void
    {
        $this->postJson('/api/auth/registro', $this->datosDeRegistro(['nombre' => '', 'apellido' => '', 'email' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre', 'apellido', 'email']);
    }

    public function test_el_registro_no_toma_la_ficha_que_cargo_la_duena(): void
    {
        // Sin verificar el correo, alguien podría registrarse con el correo de otra clienta y ver sus citas
        $fichaDeLaDuena = Cliente::create(['nombre' => 'Lucía', 'apellido' => 'Real', 'email' => 'lucia@gmail.com']);

        $this->postJson('/api/auth/registro', $this->datosDeRegistro())->assertStatus(201);

        $this->assertNull($fichaDeLaDuena->fresh()->user_id);
        $usuario = User::where('email', 'lucia@gmail.com')->first();
        $this->assertNotSame($fichaDeLaDuena->id, $usuario->cliente->id);
    }

    // ---------- Recuperar contraseña ----------

    public function test_pedir_el_enlace_manda_un_correo_con_el_enlace_a_nuestra_pantalla(): void
    {
        Mail::fake();
        $this->crearUsuario('cliente', 'lucia@gmail.com');

        $this->postJson('/api/auth/recuperar-contrasena', ['email' => 'lucia@gmail.com'])->assertOk();

        Mail::assertSent(CorreoDelSalon::class, function (CorreoDelSalon $correo) {
            return $correo->hasTo('lucia@gmail.com')
                && str_contains($correo->texto, url('/restablecer-contrasena/'))
                && str_contains($correo->texto, '?email=lucia%40gmail.com');
        });
    }

    public function test_el_enlace_sale_de_app_url_aunque_manden_cabeceras_de_proxy(): void
    {
        config(['app.url' => 'https://meninacoiffure.com']);
        Mail::fake();
        $usuario = $this->crearUsuario('cliente', 'lucia@gmail.com');
        // El nombre lo elige quien se registra: no tiene que aparecer en el correo
        $usuario->name = 'Gana un premio en http://malo.example';
        $usuario->save();

        // Con X-Forwarded-Prefix el enlace salía como https://meninacoiffure.com.atacante.example/...
        $this->withHeaders([
            'X-Forwarded-Prefix' => '.atacante.example',
            'X-Forwarded-Port' => '8443',
        ])->postJson('/api/auth/recuperar-contrasena', ['email' => 'lucia@gmail.com'])->assertOk();

        Mail::assertSent(CorreoDelSalon::class, function (CorreoDelSalon $correo) {
            return str_contains($correo->texto, "\nhttps://meninacoiffure.com/restablecer-contrasena/")
                && !str_contains($correo->texto, 'atacante')
                && !str_contains($correo->texto, '8443')
                && !str_contains($correo->texto, 'malo.example');
        });
    }

    public function test_la_app_no_confia_en_x_forwarded_prefix_ni_port(): void
    {
        // Solo For, Host y Proto: son las que Caddy reescribe antes de que lleguen a la app
        $this->withHeaders([
            'X-Forwarded-Prefix' => '.atacante.example',
            'X-Forwarded-Port' => '8443',
        ])->get('/up');

        $this->assertSame('', request()->getBaseUrl());
        $this->assertStringNotContainsString('8443', url('/dashboard'));
    }

    public function test_un_correo_sin_cuenta_recibe_la_misma_respuesta_y_no_se_envia_nada(): void
    {
        Mail::fake();
        $this->crearUsuario('cliente', 'lucia@gmail.com');

        $conCuenta = $this->postJson('/api/auth/recuperar-contrasena', ['email' => 'lucia@gmail.com'])->json('message');
        $sinCuenta = $this->postJson('/api/auth/recuperar-contrasena', ['email' => 'nadie@gmail.com'])->json('message');

        // No se revela quién tiene cuenta en el salón
        $this->assertSame($conCuenta, $sinCuenta);
        Mail::assertSent(CorreoDelSalon::class, 1);
    }

    public function test_si_el_servidor_de_correo_falla_avisa_que_no_se_pudo_enviar(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 2,
        ]);
        $this->crearUsuario('cliente', 'lucia@gmail.com');

        $this->postJson('/api/auth/recuperar-contrasena', ['email' => 'lucia@gmail.com'])->assertStatus(503);
    }

    public function test_con_el_enlace_se_guarda_la_contrasena_nueva_y_queda_adentro(): void
    {
        $usuario = $this->crearUsuario('cliente', 'lucia@gmail.com');
        $token = Password::createToken($usuario);

        $this->postJson('/api/auth/restablecer-contrasena', [
            'token' => $token,
            'email' => 'lucia@gmail.com',
            'password' => 'clave-nueva-1',
            'password_confirmation' => 'clave-nueva-1',
        ])->assertOk()->assertJsonPath('data.pagina_inicial', '/mi-cuenta');

        $this->assertAuthenticatedAs($usuario);

        // La contraseña vieja ya no sirve y la nueva sí
        Auth::logout();
        $this->postJson('/api/auth/login', ['email' => 'lucia@gmail.com', 'password' => 'password123'])->assertStatus(422);
        $this->postJson('/api/auth/login', ['email' => 'lucia@gmail.com', 'password' => 'clave-nueva-1'])->assertOk();
    }

    public function test_restablecer_la_contrasena_cierra_las_sesiones_abiertas_de_esa_cuenta(): void
    {
        $usuario = $this->crearUsuario('admin', 'duena@gmail.com');
        $otra = $this->crearUsuario('cliente', 'lucia@gmail.com');
        // Una sesión abierta en otro dispositivo (quizás robada) y la de otra persona
        foreach ([['robada', $usuario->id], ['de-lucia', $otra->id]] as [$id, $userId]) {
            DB::table('sessions')->insert([
                'id' => $id,
                'user_id' => $userId,
                'ip_address' => '203.0.113.5',
                'user_agent' => 'Prueba',
                'payload' => '',
                'last_activity' => now()->timestamp,
            ]);
        }

        $this->postJson('/api/auth/restablecer-contrasena', [
            'token' => Password::createToken($usuario),
            'email' => 'duena@gmail.com',
            'password' => 'clave-nueva-1',
            'password_confirmation' => 'clave-nueva-1',
        ])->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'robada']);
        $this->assertDatabaseHas('sessions', ['id' => 'de-lucia']);
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_las_cuentas_del_equipo_necesitan_12_caracteres_al_restablecer(): void
    {
        $duena = $this->crearUsuario('admin', 'duena@gmail.com');
        $token = Password::createToken($duena);

        // 9 caracteres: alcanza para una clienta, no para el equipo
        $this->postJson('/api/auth/restablecer-contrasena', [
            'token' => $token,
            'email' => 'duena@gmail.com',
            'password' => 'corta-123',
            'password_confirmation' => 'corta-123',
        ])->assertStatus(422)
            ->assertJsonPath('errors.password.0', 'Las cuentas del equipo necesitan una contraseña de al menos 12 caracteres.');
        $this->assertGuest();

        // El enlace sigue sirviendo para probar con una más larga
        $this->postJson('/api/auth/restablecer-contrasena', [
            'token' => $token,
            'email' => 'duena@gmail.com',
            'password' => 'una-clave-bien-larga',
            'password_confirmation' => 'una-clave-bien-larga',
        ])->assertOk();
        $this->assertAuthenticatedAs($duena);
    }

    public function test_una_clienta_puede_restablecer_con_8_caracteres_pero_no_con_mas_de_72(): void
    {
        $lucia = $this->crearUsuario('cliente', 'lucia@gmail.com');
        $token = Password::createToken($lucia);
        $larguisima = str_repeat('a', 73);

        $this->postJson('/api/auth/restablecer-contrasena', [
            'token' => $token,
            'email' => 'lucia@gmail.com',
            'password' => $larguisima,
            'password_confirmation' => $larguisima,
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson('/api/auth/restablecer-contrasena', [
            'token' => $token,
            'email' => 'lucia@gmail.com',
            'password' => 'clave123',
            'password_confirmation' => 'clave123',
        ])->assertOk();
    }

    // ---------- CSRF ----------

    /**
     * En los tests Laravel no revisa CSRF: se cambia por una versión que sí lo hace.
     */
    private function activarCsrf(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_la_api_rechaza_peticiones_de_otro_sitio_sin_token(): void
    {
        $this->activarCsrf();
        $this->crearUsuario('cliente', 'lucia@gmail.com');
        $credenciales = ['email' => 'lucia@gmail.com', 'password' => 'password123'];

        // Un formulario de otro sitio que intenta iniciar sesión (o cualquier POST) a nombre de la víctima
        $this->withHeaders(['Sec-Fetch-Site' => 'cross-site'])
            ->postJson('/api/auth/login', $credenciales)
            ->assertStatus(419);
        $this->flushHeaders();

        // Un navegador viejo que no manda Sec-Fetch-Site, sin token
        $this->postJson('/api/auth/login', $credenciales)->assertStatus(419);
        $this->assertGuest();

        // Desde el propio sitio sí
        $this->withHeaders(['Sec-Fetch-Site' => 'same-origin'])
            ->postJson('/api/auth/login', $credenciales)
            ->assertOk();
    }

    public function test_sin_sec_fetch_site_alcanza_con_el_token(): void
    {
        $this->activarCsrf();
        $this->crearUsuario('cliente', 'lucia@gmail.com');

        // Lo que hace api.js en un navegador que no manda Sec-Fetch-Site: el token de la sesión en la cabecera
        $this->withSession(['_token' => 'token-de-la-sesion'])
            ->withHeaders(['X-CSRF-TOKEN' => 'token-de-la-sesion'])
            ->postJson('/api/auth/login', ['email' => 'lucia@gmail.com', 'password' => 'password123'])
            ->assertOk();
    }

    public function test_las_consultas_get_no_piden_token(): void
    {
        $this->activarCsrf();

        $this->withHeaders(['Sec-Fetch-Site' => 'cross-site'])->getJson('/api/servicios')->assertOk();
    }

    public function test_una_cuenta_sin_contrasena_puede_crear_una_con_el_enlace(): void
    {
        $usuario = $this->crearUsuario('cliente', 'antigua@gmail.com');
        $usuario->forceFill(['password' => null])->saveQuietly();
        $token = Password::createToken($usuario);

        $this->postJson('/api/auth/restablecer-contrasena', [
            'token' => $token,
            'email' => 'antigua@gmail.com',
            'password' => 'clave-nueva-1',
            'password_confirmation' => 'clave-nueva-1',
        ])->assertOk();

        $this->assertNotNull($usuario->fresh()->password);
    }

    public function test_el_enlace_sirve_una_sola_vez(): void
    {
        $usuario = $this->crearUsuario('cliente', 'lucia@gmail.com');
        $token = Password::createToken($usuario);
        $datos = [
            'token' => $token,
            'email' => 'lucia@gmail.com',
            'password' => 'clave-nueva-1',
            'password_confirmation' => 'clave-nueva-1',
        ];

        $this->postJson('/api/auth/restablecer-contrasena', $datos)->assertOk();
        Auth::logout();

        $this->postJson('/api/auth/restablecer-contrasena', $datos)
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'El enlace venció o ya se usó. Pide uno nuevo.');
    }

    public function test_un_token_inventado_no_cambia_la_contrasena(): void
    {
        $usuario = $this->crearUsuario('cliente', 'lucia@gmail.com');
        $hashAnterior = $usuario->password;

        $this->postJson('/api/auth/restablecer-contrasena', [
            'token' => 'token-inventado',
            'email' => 'lucia@gmail.com',
            'password' => 'clave-nueva-1',
            'password_confirmation' => 'clave-nueva-1',
        ])->assertStatus(422);

        $this->assertSame($hashAnterior, $usuario->fresh()->password);
        $this->assertGuest();
    }

    // ---------- Pantallas ----------

    public function test_las_pantallas_de_cuenta_abren_sin_sesion(): void
    {
        $this->get('/login')->assertOk()->assertSee('Auth\/Login', false);
        $this->get('/registro')->assertOk()->assertSee('Auth\/Registro', false);
        $this->get('/recuperar-contrasena')->assertOk()->assertSee('Auth\/RecuperarContrasena', false);
        $this->get('/restablecer-contrasena/token-de-prueba?email=lucia@gmail.com')
            ->assertOk()
            ->assertSee('Auth\/RestablecerContrasena', false)
            ->assertSee('token-de-prueba', false);
    }

    public function test_con_sesion_iniciada_las_pantallas_de_cuenta_mandan_a_su_pagina(): void
    {
        $clienta = $this->crearUsuario('cliente', 'lucia@gmail.com');

        $this->actingAs($clienta)->get('/registro')->assertRedirect('/mi-cuenta');
    }

    public function test_las_rutas_de_keycloak_ya_no_existen(): void
    {
        $this->get('/auth/keycloak/redirigir')->assertNotFound();
        $this->get('/auth/keycloak/callback')->assertNotFound();
    }
}
