<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Login con Keycloak por OpenID Connect: Authorization Code + PKCE (S256).
 * Keycloak dice quién es la persona; el rol y los permisos se siguen decidiendo en Laravel.
 */
class KeycloakController extends Controller
{
    private const ERROR_EXPIRADO = 'El inicio de sesión expiró o no es válido. Inténtalo de nuevo.';
    private const ERROR_PROVEEDOR = 'No se pudo completar el inicio de sesión con tu cuenta. Inténtalo de nuevo en unos minutos.';

    /**
     * Paso 1: manda el navegador a la pantalla de login de Keycloak.
     */
    public function redirigir(Request $request): RedirectResponse
    {
        if (!$this->keycloakActivo()) {
            abort(404);
        }

        $state = Str::random(40);
        $verificador = Str::random(64);
        // PKCE S256: a Keycloak solo viaja el hash del verificador, nunca el verificador
        $hashDelVerificador = hash('sha256', $verificador, true);
        $desafio = rtrim(strtr(base64_encode($hashDelVerificador), '+/', '-_'), '=');

        $request->session()->put('keycloak_state', $state);
        $request->session()->put('keycloak_verificador', $verificador);

        $parametros = http_build_query([
            'client_id' => config('services.keycloak.client_id'),
            'redirect_uri' => $this->urlCallback(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => $desafio,
            'code_challenge_method' => 'S256',
        ]);

        return redirect()->away(self::urlPublica('/protocol/openid-connect/auth') . '?' . $parametros);
    }

    /**
     * Paso 2: Keycloak vuelve con un código de un solo uso. Se canjea servidor a servidor
     * (con el secreto del cliente y el verificador PKCE) y se piden los datos de la persona.
     */
    public function callback(Request $request): RedirectResponse
    {
        if (!$this->keycloakActivo()) {
            abort(404);
        }

        // pull: el state y el verificador sirven una sola vez
        $stateGuardado = $request->session()->pull('keycloak_state');
        $verificador = $request->session()->pull('keycloak_verificador');
        $stateRecibido = (string) $request->query('state', '');

        // Sin state válido no se sigue: protege contra CSRF en la vuelta del login
        if (!$stateGuardado || !hash_equals($stateGuardado, $stateRecibido)) {
            return $this->volverAlLogin(self::ERROR_EXPIRADO);
        }

        // La persona canceló o Keycloak rechazó el pedido
        $codigo = (string) $request->query('code', '');
        if ($request->query('error') || $codigo === '') {
            return $this->volverAlLogin(self::ERROR_PROVEEDOR);
        }

        try {
            $respuestaToken = Http::asForm()->post(self::urlInterna('/protocol/openid-connect/token'), [
                'grant_type' => 'authorization_code',
                'code' => $codigo,
                'redirect_uri' => $this->urlCallback(),
                'client_id' => config('services.keycloak.client_id'),
                'client_secret' => config('services.keycloak.client_secret'),
                'code_verifier' => $verificador,
            ]);

            if ($respuestaToken->failed()) {
                Log::warning('Keycloak rechazó el canje del código', ['estado' => $respuestaToken->status()]);
                return $this->volverAlLogin(self::ERROR_PROVEEDOR);
            }

            $accessToken = $respuestaToken->json('access_token');
            $idToken = $respuestaToken->json('id_token');

            // El token llegó por el canal servidor a servidor con nuestro secreto: se confía en
            // userinfo en lugar de verificar la firma del JWT a mano
            $respuestaPersona = Http::withToken((string) $accessToken)
                ->get(self::urlInterna('/protocol/openid-connect/userinfo'));

            if ($respuestaPersona->failed()) {
                Log::warning('Keycloak rechazó la consulta de userinfo', ['estado' => $respuestaPersona->status()]);
                return $this->volverAlLogin(self::ERROR_PROVEEDOR);
            }
        } catch (ConnectionException $excepcion) {
            Log::error('No se pudo conectar con Keycloak', ['error' => $excepcion->getMessage()]);
            return $this->volverAlLogin(self::ERROR_PROVEEDOR);
        }

        $persona = $respuestaPersona->json();
        $sub = (string) ($persona['sub'] ?? '');
        $email = strtolower(trim((string) ($persona['email'] ?? '')));

        if ($sub === '' || $email === '') {
            return $this->volverAlLogin(self::ERROR_PROVEEDOR);
        }

        $usuario = $this->buscarOCrearUsuario($persona, $sub, $email);
        if ($usuario === null) {
            return $this->volverAlLogin('Ya existe una cuenta del salón con ese correo. Pide a la administración que lo verifique.');
        }

        Auth::login($usuario);
        $request->session()->regenerate();
        // Se guarda para cerrar también la sesión de Keycloak al salir
        $request->session()->put('keycloak_id_token', $idToken);

        return redirect($usuario->paginaInicial());
    }

    /**
     * Vinculación de cuentas: por el "sub" de Keycloak, por correo verificado (la dueña y el equipo)
     * o, si es alguien nuevo, como clienta.
     */
    private function buscarOCrearUsuario(array $persona, string $sub, string $email): ?User
    {
        $usuarioVinculado = User::where('keycloak_id', $sub)->first();
        if ($usuarioVinculado !== null) {
            return $usuarioVinculado;
        }

        $usuarioConMismoCorreo = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($usuarioConMismoCorreo !== null) {
            // Con el registro abierto, cualquiera podría crear en Keycloak una cuenta con el correo
            // de la dueña: solo se vincula si Keycloak confirma que el correo está verificado
            $correoVerificado = ($persona['email_verified'] ?? false) === true;
            if (!$correoVerificado || $usuarioConMismoCorreo->keycloak_id !== null) {
                return null;
            }

            $usuarioConMismoCorreo->keycloak_id = $sub;
            $usuarioConMismoCorreo->save();

            return $usuarioConMismoCorreo;
        }

        $nombre = trim((string) ($persona['name'] ?? ''));
        if ($nombre === '') {
            $nombre = $email;
        }

        $rolCliente = Role::firstOrCreate(['nombre' => 'cliente']);

        return User::create([
            'name' => $nombre,
            'email' => $email,
            'keycloak_id' => $sub,
            'password' => null,
            'rol_id' => $rolCliente->id,
        ]);
    }

    /**
     * URL de cierre de sesión en Keycloak, o null si el login es local o no hay token guardado.
     */
    public static function urlCierreDeSesion(?string $idToken): ?string
    {
        if (config('services.keycloak.proveedor') !== 'keycloak' || !$idToken) {
            return null;
        }

        $parametros = http_build_query([
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => url('/salon'),
        ]);

        return self::urlPublica('/protocol/openid-connect/logout') . '?' . $parametros;
    }

    private function keycloakActivo(): bool
    {
        return config('services.keycloak.proveedor') === 'keycloak';
    }

    private function volverAlLogin(string $mensaje): RedirectResponse
    {
        return redirect('/login')->with('error_login', $mensaje);
    }

    private function urlCallback(): string
    {
        return url('/auth/keycloak/callback');
    }

    // El navegador ve a Keycloak por localhost:8080
    private static function urlPublica(string $ruta): string
    {
        return rtrim(config('services.keycloak.url_publica'), '/') . '/realms/' . config('services.keycloak.realm') . $ruta;
    }

    // Laravel, dentro de Docker, le habla por keycloak:8080
    private static function urlInterna(string $ruta): string
    {
        return rtrim(config('services.keycloak.url_interna'), '/') . '/realms/' . config('services.keycloak.realm') . $ruta;
    }
}
