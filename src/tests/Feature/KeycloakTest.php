<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Login con Keycloak (Authorization Code + PKCE). Keycloak se simula con Http::fake():
 * las pruebas no necesitan el contenedor levantado.
 */
class KeycloakTest extends TestCase
{
    use RefreshDatabase;

    private const URL_TOKEN = 'http://keycloak:8080/realms/menina/protocol/openid-connect/token';
    private const URL_USERINFO = 'http://keycloak:8080/realms/menina/protocol/openid-connect/userinfo';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.keycloak.proveedor' => 'keycloak',
            'services.keycloak.url_publica' => 'http://localhost:8080',
            'services.keycloak.url_interna' => 'http://keycloak:8080',
            'services.keycloak.realm' => 'menina',
            'services.keycloak.client_id' => 'menina-web',
            'services.keycloak.client_secret' => 'secreto-de-prueba',
        ]);
    }

    private function simularKeycloak(array $persona): void
    {
        Http::fake([
            self::URL_TOKEN => Http::response([
                'access_token' => 'token-de-acceso',
                'id_token' => 'token-de-identidad',
                'token_type' => 'Bearer',
            ]),
            self::URL_USERINFO => Http::response($persona),
        ]);
    }

    // La vuelta de Keycloak con el state que Laravel guardó al redirigir
    private function volverDeKeycloak(string $state = 'state-guardado')
    {
        return $this->withSession(['keycloak_state' => 'state-guardado', 'keycloak_verificador' => 'verificador-guardado'])
            ->get('/auth/keycloak/callback?code=codigo-de-un-uso&state=' . $state);
    }

    public function test_redirigir_manda_a_keycloak_con_pkce_s256_y_guarda_el_state(): void
    {
        $respuesta = $this->get('/auth/keycloak/redirigir');

        $respuesta->assertStatus(302);
        $destino = $respuesta->headers->get('Location');
        $this->assertStringStartsWith('http://localhost:8080/realms/menina/protocol/openid-connect/auth?', $destino);

        parse_str(parse_url($destino, PHP_URL_QUERY), $parametros);
        $this->assertSame('menina-web', $parametros['client_id']);
        $this->assertSame('code', $parametros['response_type']);
        $this->assertSame('S256', $parametros['code_challenge_method']);
        $this->assertSame(url('/auth/keycloak/callback'), $parametros['redirect_uri']);
        $this->assertSame(session('keycloak_state'), $parametros['state']);

        // El desafío es el hash del verificador guardado, nunca el verificador en claro
        $verificador = session('keycloak_verificador');
        $desafioEsperado = rtrim(strtr(base64_encode(hash('sha256', $verificador, true)), '+/', '-_'), '=');
        $this->assertSame($desafioEsperado, $parametros['code_challenge']);
        $this->assertStringNotContainsString($verificador, $destino);
    }

    public function test_callback_con_state_distinto_no_inicia_sesion(): void
    {
        Http::fake();

        $this->volverDeKeycloak('state-falso')
            ->assertRedirect('/login')
            ->assertSessionHas('error_login');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_callback_vincula_a_la_duena_por_correo_verificado_y_conserva_su_rol(): void
    {
        $rolAdmin = Role::firstOrCreate(['nombre' => 'admin']);
        $duena = User::factory()->create(['email' => 'admin@meninacoiffure.com', 'rol_id' => $rolAdmin->id]);
        $this->simularKeycloak([
            'sub' => 'sub-de-la-duena',
            'email' => 'Admin@MeninaCoiffure.com',
            'email_verified' => true,
            'name' => 'Mónica Propietaria',
        ]);

        $this->volverDeKeycloak()->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($duena);
        $this->assertSame('sub-de-la-duena', $duena->fresh()->keycloak_id);
        $this->assertTrue($duena->fresh()->esAdmin());
        $this->assertSame('token-de-identidad', session('keycloak_id_token'));

        // El canje lleva el verificador PKCE y el secreto, por el canal interno
        Http::assertSent(function ($pedido) {
            return $pedido->url() === self::URL_TOKEN
                && $pedido['code'] === 'codigo-de-un-uso'
                && $pedido['code_verifier'] === 'verificador-guardado'
                && $pedido['client_secret'] === 'secreto-de-prueba';
        });
    }

    public function test_callback_con_cuenta_ya_vinculada_entra_por_el_sub(): void
    {
        $rolProfesional = Role::firstOrCreate(['nombre' => 'profesional']);
        $carla = User::factory()->create([
            'email' => 'carla@meninacoiffure.com',
            'keycloak_id' => 'sub-de-carla',
            'rol_id' => $rolProfesional->id,
        ]);
        // Aunque en Keycloak haya cambiado el correo, el sub identifica a la misma persona
        $this->simularKeycloak(['sub' => 'sub-de-carla', 'email' => 'carla.nueva@gmail.com', 'email_verified' => false]);

        $this->volverDeKeycloak()->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($carla);
    }

    public function test_callback_de_persona_nueva_crea_una_clienta_sin_contrasena(): void
    {
        $this->simularKeycloak([
            'sub' => 'sub-nuevo',
            'email' => 'lucia@gmail.com',
            'email_verified' => false,
            'name' => 'Lucía Benítez',
            'given_name' => 'Lucía',
            'family_name' => 'Benítez',
        ]);

        $this->volverDeKeycloak()->assertRedirect('/mi-cuenta');

        $clienta = User::where('email', 'lucia@gmail.com')->first();
        $this->assertNotNull($clienta);
        $this->assertSame('Lucía Benítez', $clienta->name);
        $this->assertSame('sub-nuevo', $clienta->keycloak_id);
        $this->assertNull($clienta->password);
        $this->assertTrue($clienta->hasRole('cliente'));
        $this->assertAuthenticatedAs($clienta);

        // Los permisos de Configuración son del equipo: una clienta no tiene ninguno
        $this->getJson('/api/auth/me')
            ->assertJsonPath('data.rol', 'cliente')
            ->assertJsonPath('data.permisos.puede_crear_reservas', false)
            ->assertJsonPath('data.permisos.puede_ver_clientes', false);

        // Se le crea su ficha para que pueda pedir citas
        $ficha = $clienta->cliente;
        $this->assertNotNull($ficha);
        $this->assertSame('Lucía', $ficha->nombre);
        $this->assertSame('Benítez', $ficha->apellido);
        $this->assertSame('lucia@gmail.com', $ficha->email);
    }

    public function test_clienta_con_correo_verificado_se_vincula_a_la_ficha_que_cargo_la_duena(): void
    {
        $fichaExistente = Cliente::create(['nombre' => 'Ana', 'apellido' => 'Gómez', 'email' => 'ana@gmail.com']);
        $this->simularKeycloak(['sub' => 'sub-ana', 'email' => 'ana@gmail.com', 'email_verified' => true, 'name' => 'Ana Gómez']);

        $this->volverDeKeycloak()->assertRedirect('/mi-cuenta');

        $clienta = User::where('email', 'ana@gmail.com')->first();
        $this->assertSame($fichaExistente->id, $clienta->cliente->id);
        $this->assertSame(1, Cliente::where('email', 'ana@gmail.com')->count());
    }

    public function test_correo_sin_verificar_no_toma_la_ficha_de_otra_clienta(): void
    {
        $fichaAjena = Cliente::create(['nombre' => 'Ana', 'apellido' => 'Gómez', 'email' => 'ana@gmail.com']);
        $this->simularKeycloak(['sub' => 'sub-intrusa', 'email' => 'ana@gmail.com', 'email_verified' => false, 'name' => 'Intrusa']);

        $this->volverDeKeycloak()->assertRedirect('/mi-cuenta');

        // Entra como clienta nueva, con una ficha propia y vacía
        $this->assertNull($fichaAjena->fresh()->user_id);
        $clienta = User::where('keycloak_id', 'sub-intrusa')->first();
        $this->assertNotSame($fichaAjena->id, $clienta->cliente->id);
    }

    public function test_registro_lleva_al_formulario_de_crear_cuenta_de_keycloak(): void
    {
        $destino = $this->get('/auth/keycloak/redirigir?registro=1')->headers->get('Location');

        $this->assertStringStartsWith('http://localhost:8080/realms/menina/protocol/openid-connect/registrations?', $destino);
        $this->assertStringContainsString('code_challenge_method=S256', $destino);
    }

    public function test_correo_sin_verificar_no_se_apropia_de_una_cuenta_del_equipo(): void
    {
        $rolAdmin = Role::firstOrCreate(['nombre' => 'admin']);
        $duena = User::factory()->create(['email' => 'admin@meninacoiffure.com', 'rol_id' => $rolAdmin->id]);
        $this->simularKeycloak(['sub' => 'sub-intruso', 'email' => 'admin@meninacoiffure.com', 'email_verified' => false]);

        $this->volverDeKeycloak()->assertRedirect('/login')->assertSessionHas('error_login');

        $this->assertGuest();
        $this->assertNull($duena->fresh()->keycloak_id);
    }

    public function test_si_keycloak_rechaza_el_codigo_vuelve_al_login_sin_sesion(): void
    {
        Http::fake([self::URL_TOKEN => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->volverDeKeycloak()->assertRedirect('/login')->assertSessionHas('error_login');

        $this->assertGuest();
    }

    public function test_si_keycloak_no_responde_vuelve_al_login_sin_sesion(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $this->volverDeKeycloak()->assertRedirect('/login')->assertSessionHas('error_login');

        $this->assertGuest();
    }

    public function test_con_keycloak_el_login_con_contrasena_queda_cerrado(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'admin@meninacoiffure.com', 'password' => 'password123'])
            ->assertStatus(404);
    }

    public function test_al_salir_devuelve_la_url_de_cierre_de_keycloak(): void
    {
        $rolAdmin = Role::firstOrCreate(['nombre' => 'admin']);
        $duena = User::factory()->create(['rol_id' => $rolAdmin->id]);

        $respuesta = $this->actingAs($duena)
            ->withSession(['keycloak_id_token' => 'token-de-identidad'])
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        $urlCierre = $respuesta->json('url_cierre');
        $this->assertStringStartsWith('http://localhost:8080/realms/menina/protocol/openid-connect/logout?', $urlCierre);
        $this->assertStringContainsString('id_token_hint=token-de-identidad', $urlCierre);
        $this->assertStringContainsString('post_logout_redirect_uri=' . urlencode(url('/login')), $urlCierre);
        $this->assertGuest();
    }

    public function test_una_clienta_con_sesion_no_entra_al_panel_y_la_raiz_la_lleva_a_sus_citas(): void
    {
        $rolCliente = Role::firstOrCreate(['nombre' => 'cliente']);
        $clienta = User::factory()->create(['name' => 'Lucia Benitez', 'rol_id' => $rolCliente->id]);

        $this->actingAs($clienta);
        $this->get('/')->assertRedirect('/mi-cuenta');
        $this->get('/login')->assertRedirect('/mi-cuenta');
        $this->get('/mi-cuenta')->assertStatus(200);
        $this->get('/dashboard')->assertStatus(403);
        $this->get('/salon')->assertStatus(200)->assertSee('"cuenta":{"nombre":"Lucia Benitez"}', false);
    }

    public function test_con_proveedor_local_las_rutas_de_keycloak_no_existen(): void
    {
        config(['services.keycloak.proveedor' => 'local']);

        $this->get('/auth/keycloak/redirigir')->assertStatus(404);
        $this->get('/auth/keycloak/callback?code=x&state=y')->assertStatus(404);
        $this->get('/login')->assertStatus(200)->assertSee('"proveedor":"local"', false);
        $this->postJson('/api/auth/logout')->assertJsonPath('url_cierre', null);
    }

    public function test_con_keycloak_el_login_va_directo_al_formulario_del_salon_en_keycloak(): void
    {
        $this->get('/login')->assertRedirect('/auth/keycloak/redirigir');

        // Si el login falló, se queda en /login para mostrar el aviso (sin volver a redirigir en bucle)
        $this->withSession(['error_login' => 'El inicio de sesión expiró o no es válido. Inténtalo de nuevo.'])
            ->get('/login')
            ->assertStatus(200)
            ->assertSee('"proveedor":"keycloak"', false);
    }
}
