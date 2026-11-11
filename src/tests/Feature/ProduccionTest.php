<?php

namespace Tests\Feature;

use App\Models\CategoriaServicio;
use App\Models\Cliente;
use App\Models\Descuento;
use App\Models\HorarioAtencion;
use App\Models\MetodoPago;
use App\Models\Personal;
use App\Models\Reserva;
use App\Models\Role;
use App\Models\Servicio;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProduccionSeeder;
use Database\Seeders\SprintUnoDosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Lo que cambia para producción: documentación de la API solo para la administración, datos
 * reales sin cuentas de prueba, cuentas del equipo creadas por terminal y proxies de confianza.
 */
class ProduccionTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'una-clave-bien-larga';

    private function crearUsuario(string $rol, string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'rol_id' => Role::firstOrCreate(['nombre' => $rol])->id,
        ]);
    }

    private function crearFicha(string $nombre, string $email): Personal
    {
        return Personal::create([
            'nombre' => $nombre, 'apellido' => 'Prueba', 'email' => $email,
            'telefono' => '0981000000', 'fecha_ingreso' => '2025-01-01', 'activo' => true,
        ]);
    }

    // ---------- Documentación de la API ----------

    public function test_la_documentacion_y_el_openapi_piden_sesion(): void
    {
        $this->get('/docs')->assertRedirect('/login');
        $this->get('/openapi.json')->assertRedirect('/login');
        // Bajo api/* las respuestas son JSON: sin sesión es 401 en lugar de redirigir
        $this->get('/api/documentation')->assertUnauthorized();
    }

    public function test_una_profesional_no_ve_la_documentacion(): void
    {
        $profesional = $this->crearUsuario('profesional', 'carla@meninacoiffure.com');

        $this->actingAs($profesional)->get('/docs')->assertForbidden();
        $this->actingAs($profesional)->get('/openapi.json')->assertForbidden();
    }

    public function test_la_administracion_ve_la_documentacion_y_el_openapi(): void
    {
        $admin = $this->crearUsuario('admin', 'admin@meninacoiffure.com');

        $this->actingAs($admin)->get('/docs')->assertOk()->assertSee('/openapi.json', false);

        $respuesta = $this->actingAs($admin)->get('/openapi.json');
        $respuesta->assertOk();

        // response()->file devuelve el archivo tal cual: se lee desde su ruta
        $rutaDelArchivo = $respuesta->baseResponse->getFile()->getPathname();
        $especificacion = json_decode(file_get_contents($rutaDelArchivo), true);
        $this->assertArrayHasKey('/api/auth/login', $especificacion['paths']);
    }

    public function test_el_openapi_ya_no_esta_en_la_carpeta_publica(): void
    {
        // nginx sirve public/ sin pasar por Laravel: ahí sería público aunque /docs pida admin
        $this->assertFileDoesNotExist(public_path('openapi.json'));
        $this->assertFileExists(resource_path('openapi/openapi.json'));
    }

    // ---------- Proxies de confianza ----------

    public function test_detras_del_proxy_https_las_redirecciones_salen_con_https(): void
    {
        $respuesta = $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get('/docs');

        $respuesta->assertRedirect();
        $this->assertStringStartsWith('https://', $respuesta->headers->get('Location'));
    }

    // ---------- Seeders ----------

    public function test_el_seeder_de_produccion_carga_los_datos_reales_sin_cuentas(): void
    {
        $this->seed(ProduccionSeeder::class);

        $this->assertSame(['admin', 'cliente', 'profesional'], Role::orderBy('nombre')->pluck('nombre')->all());
        $this->assertSame(5, HorarioAtencion::count());
        $this->assertSame(4, MetodoPago::count());
        $this->assertSame(['Capilar', 'Maquillaje', 'Planchita y ondas', 'Uñas'], CategoriaServicio::orderBy('nombre')->pluck('nombre')->all());
        $this->assertSame(24, Servicio::count());

        // Nada de cuentas, personal ni clientas de prueba
        $this->assertSame(0, User::count());
        $this->assertSame(0, Personal::count());
        $this->assertSame(0, Cliente::count());
    }

    public function test_los_precios_son_los_de_la_lista_2025(): void
    {
        $this->seed(ProduccionSeeder::class);

        $precios = [
            'Manicure' => 40000,
            'Diseño de uñas' => 5000,
            'Ondas pelo largo' => 70000,
            'Trenzas' => 25000,
            'Maquillaje glam' => 120000,
            'Extensiones de pestañas' => 250000,
            'Tintura' => 90000,
            "Baño L'Oréal" => 80000,
        ];

        foreach ($precios as $nombre => $precio) {
            $servicio = Servicio::where('nombre', $nombre)->first();
            $this->assertNotNull($servicio, "Falta el servicio {$nombre}");
            $this->assertEquals($precio, (float) $servicio->precioVigente(now()->toDateString())->precio, "Precio de {$nombre}");
            $this->assertNotNull($servicio->especialidad_id, "{$nombre} sin especialidad");
        }
    }

    public function test_la_promo_miercoles_rige_solo_los_miercoles(): void
    {
        $this->seed(ProduccionSeeder::class);

        $miercoles = now()->next('Wednesday')->toDateString();
        $jueves = now()->next('Thursday')->toDateString();

        $this->assertTrue(Descuento::vigentes($miercoles)->where('nombre', 'Promo miércoles')->exists());
        $this->assertFalse(Descuento::vigentes($jueves)->where('nombre', 'Promo miércoles')->exists());
    }

    public function test_el_seeder_de_produccion_se_puede_repetir_sin_duplicar(): void
    {
        $this->seed(ProduccionSeeder::class);
        $this->seed(ProduccionSeeder::class);

        $this->assertSame(24, Servicio::count());
        $this->assertSame(5, HorarioAtencion::count());
        $this->assertSame(1, Descuento::where('nombre', 'Promo miércoles')->count());
        $this->assertSame(1, Descuento::where('nombre', 'Promo miércoles')->first()->dias()->count());
    }

    public function test_los_seeders_de_demo_no_corren_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $seeders = [DatabaseSeeder::class, SprintUnoDosSeeder::class];
        foreach ($seeders as $seeder) {
            $lanzoError = false;
            try {
                $this->app->make($seeder)->run();
            } catch (RuntimeException $excepcion) {
                $lanzoError = true;
            }
            $this->assertTrue($lanzoError, "{$seeder} corrió en producción");
        }

        // Ninguna cuenta con password123 llegó a crearse
        $this->assertSame(0, User::count());
    }

    public function test_la_demo_de_desarrollo_carga_el_catalogo_real_y_el_equipo(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@meninacoiffure.com')->first();
        $this->assertTrue($admin->esAdmin());
        $this->assertNotNull($admin->personal_id);
        $this->assertTrue(Hash::check('password123', $admin->password));

        $this->assertSame(24, Servicio::count());
        $this->assertGreaterThan(0, Reserva::count());
        $this->assertNotNull(User::where('email', 'clienta@meninacoiffure.com')->first()?->cliente);
    }

    // ---------- Cuentas del equipo por terminal ----------

    public function test_crea_la_cuenta_de_administracion_sin_ficha(): void
    {
        $this->seed(ProduccionSeeder::class);

        $this->artisan('salon:crear-cuenta', ['--rol' => 'admin', '--email' => ' Duena@Gmail.com ', '--nombre' => 'Mónica'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', self::CLAVE)
            ->expectsQuestion('Repite la contraseña', self::CLAVE)
            ->assertSuccessful();

        $usuario = User::where('email', 'duena@gmail.com')->first();
        $this->assertNotNull($usuario);
        $this->assertTrue($usuario->esAdmin());
        $this->assertNull($usuario->personal_id);
        $this->assertTrue(Hash::check(self::CLAVE, $usuario->password));

        // Y puede iniciar sesión con esa contraseña
        $this->postJson('/api/auth/login', ['email' => 'duena@gmail.com', 'password' => self::CLAVE])
            ->assertOk()
            ->assertJsonPath('data.pagina_inicial', '/dashboard');
    }

    public function test_la_contrasena_tiene_que_tener_12_caracteres_y_coincidir(): void
    {
        $this->artisan('salon:crear-cuenta', ['--rol' => 'admin', '--email' => 'duena@gmail.com', '--nombre' => 'Mónica'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'corta')
            ->expectsQuestion('Repite la contraseña', 'corta')
            ->expectsOutput('La contraseña tiene que tener al menos 12 caracteres.')
            ->assertFailed();

        $this->artisan('salon:crear-cuenta', ['--rol' => 'admin', '--email' => 'duena@gmail.com', '--nombre' => 'Mónica'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', self::CLAVE)
            ->expectsQuestion('Repite la contraseña', 'otra-clave-distinta')
            ->expectsOutput('Las contraseñas no coinciden.')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_no_crea_una_cuenta_con_un_correo_repetido_ni_un_rol_invalido(): void
    {
        $this->crearUsuario('cliente', 'duena@gmail.com');

        $this->artisan('salon:crear-cuenta', ['--rol' => 'admin', '--email' => 'DUENA@gmail.com', '--nombre' => 'Mónica'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', self::CLAVE)
            ->expectsQuestion('Repite la contraseña', self::CLAVE)
            ->expectsOutput('Ya hay una cuenta con el correo duena@gmail.com.')
            ->assertFailed();

        $this->artisan('salon:crear-cuenta', ['--rol' => 'cliente', '--email' => 'otra@gmail.com', '--nombre' => 'Otra'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', self::CLAVE)
            ->expectsQuestion('Repite la contraseña', self::CLAVE)
            ->expectsOutput('El rol tiene que ser admin o profesional.')
            ->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_crea_una_profesional_vinculada_a_su_ficha(): void
    {
        $ficha = $this->crearFicha('Carla', 'carla@gmail.com');

        $this->artisan('salon:crear-cuenta', ['--rol' => 'profesional', '--email' => 'carla@gmail.com', '--nombre' => 'Carla', '--personal-email' => 'CARLA@gmail.com'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', self::CLAVE)
            ->expectsQuestion('Repite la contraseña', self::CLAVE)
            ->assertSuccessful();

        $usuario = User::where('email', 'carla@gmail.com')->first();
        $this->assertTrue($usuario->hasRole('profesional'));
        $this->assertSame($ficha->id, $usuario->personal_id);
    }

    public function test_una_profesional_sin_ficha_o_con_una_ficha_ocupada_no_se_crea(): void
    {
        // Sin ficha: no vería ninguna agenda
        $this->artisan('salon:crear-cuenta', ['--rol' => 'profesional', '--email' => 'carla@gmail.com', '--nombre' => 'Carla'])
            ->expectsQuestion('Correo de su ficha de personal (la carga la administración desde el panel)', '')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', self::CLAVE)
            ->expectsQuestion('Repite la contraseña', self::CLAVE)
            ->expectsOutput('Una profesional necesita su ficha de personal: sin ella no ve ninguna agenda.')
            ->assertFailed();

        // Ficha que no existe
        $this->artisan('salon:crear-cuenta', ['--rol' => 'profesional', '--email' => 'carla@gmail.com', '--nombre' => 'Carla', '--personal-email' => 'nadie@gmail.com'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', self::CLAVE)
            ->expectsQuestion('Repite la contraseña', self::CLAVE)
            ->assertFailed();

        // Ficha ya vinculada a otra cuenta
        $ficha = $this->crearFicha('Carla', 'carla@gmail.com');
        $otra = $this->crearUsuario('profesional', 'otra@gmail.com');
        $otra->personal_id = $ficha->id;
        $otra->save();

        $this->artisan('salon:crear-cuenta', ['--rol' => 'profesional', '--email' => 'carla@gmail.com', '--nombre' => 'Carla', '--personal-email' => 'carla@gmail.com'])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', self::CLAVE)
            ->expectsQuestion('Repite la contraseña', self::CLAVE)
            ->expectsOutput('La ficha de Carla ya está vinculada a otra cuenta.')
            ->assertFailed();

        $this->assertNull(User::where('email', 'carla@gmail.com')->first());
    }

    public function test_vincula_la_cuenta_de_la_duena_con_su_ficha(): void
    {
        $duena = $this->crearUsuario('admin', 'duena@gmail.com');
        $ficha = $this->crearFicha('Mónica', 'monica@gmail.com');

        $this->artisan('salon:vincular-ficha', ['email' => 'Duena@gmail.com', 'personal-email' => 'monica@gmail.com'])
            ->assertSuccessful();

        $this->assertSame($ficha->id, $duena->fresh()->personal_id);
    }

    public function test_no_vincula_una_cuenta_de_clienta_ni_una_ficha_inexistente(): void
    {
        $this->crearUsuario('cliente', 'clienta@gmail.com');
        $this->crearUsuario('admin', 'duena@gmail.com');
        $this->crearFicha('Mónica', 'monica@gmail.com');

        $this->artisan('salon:vincular-ficha', ['email' => 'clienta@gmail.com', 'personal-email' => 'monica@gmail.com'])
            ->assertFailed();
        $this->artisan('salon:vincular-ficha', ['email' => 'duena@gmail.com', 'personal-email' => 'nadie@gmail.com'])
            ->assertFailed();

        $this->assertSame(0, User::whereNotNull('personal_id')->count());
    }
}
