<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthController extends Controller
{
    // Protección contra fuerza bruta: intentos fallidos permitidos por correo e IP antes de esperar
    private const INTENTOS_DE_LOGIN = 5;
    private const SEGUNDOS_DE_ESPERA = 60;

    /**
     * HU-25: Inicio de sesión por rol
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'recordar' => ['nullable', 'boolean'],
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico es inválido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        $email = strtolower(trim($credentials['email']));

        // La clave junta correo e IP: frena a quien prueba contraseñas sin bloquear a toda una red
        $claveDeIntentos = 'login|' . $email . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($claveDeIntentos, self::INTENTOS_DE_LOGIN)) {
            $segundos = RateLimiter::availableIn($claveDeIntentos);
            throw ValidationException::withMessages([
                'email' => ['Demasiados intentos. Prueba de nuevo en ' . $segundos . ' segundos.'],
            ])->status(429);
        }

        $user = User::with(['role', 'personal'])->whereRaw('LOWER(email) = ?', [$email])->first();

        // Una cuenta sin contraseña local (creada antes con Keycloak) no entra hasta crear una
        $contrasenaCorrecta = false;
        if ($user !== null && $user->password !== null) {
            $contrasenaCorrecta = Hash::check($credentials['password'], $user->password);
        } else {
            // Sin cuenta igual se calcula un hash: la respuesta tarda lo mismo (unos 200 ms) y el
            // tiempo no revela qué correos tienen cuenta en el salón
            Hash::make($credentials['password']);
        }

        if (!$contrasenaCorrecta) {
            RateLimiter::hit($claveDeIntentos, self::SEGUNDOS_DE_ESPERA);
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas no son válidas.'],
            ]);
        }

        RateLimiter::clear($claveDeIntentos);

        // La dueña la dio de baja en Personal: la contraseña es correcta pero ya no entra
        if ($user->esProfesionalSinFichaActiva()) {
            throw ValidationException::withMessages([
                'email' => ['Tu cuenta está desactivada. Habla con la administración del salón.'],
            ]);
        }

        // "Seguir conectado": la sesión sobrevive al cierre del navegador con la cookie remember_token
        Auth::login($user, $request->boolean('recordar'));
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => 'Inicio de sesión exitoso.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'rol' => $user->role ? $user->role->nombre : null,
                    'personal' => $user->personal ? [
                        'id' => $user->personal->id,
                        'nombre_completo' => $user->personal->nombre . ' ' . $user->personal->apellido,
                    ] : null,
                    'permisos' => $user->permisos(),
                ],
                // El equipo va al panel y una clienta a sus citas
                'pagina_inicial' => $user->paginaInicial(),
            ],
        ], 200);
    }

    /**
     * Una clienta crea su cuenta desde la página del salón. Queda con su ficha para pedir citas.
     */
    public function registrar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            // max:72: bcrypt solo usa los primeros 72 bytes; lo que sigue se ignoraría sin avisar
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ], [
            'nombre.required' => 'Escribe tu nombre.',
            'apellido.required' => 'Escribe tu apellido.',
            'email.required' => 'Escribe tu correo.',
            'email.email' => 'Ese correo no es válido.',
            'password.required' => 'Escribe una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no puede superar los 72 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $email = strtolower(trim($datos['email']));
        $nombre = trim($datos['nombre']);
        $apellido = trim($datos['apellido']);

        $correoEnUso = User::whereRaw('LOWER(email) = ?', [$email])->exists();
        if ($correoEnUso) {
            throw ValidationException::withMessages([
                'email' => ['Ya hay una cuenta con ese correo. Prueba iniciar sesión.'],
            ]);
        }

        $rolCliente = Role::firstOrCreate(['nombre' => 'cliente']);

        // Cuenta y ficha juntas: una cuenta sin ficha no podría pedir citas
        $usuario = DB::transaction(function () use ($nombre, $apellido, $email, $datos, $rolCliente) {
            $usuario = User::create([
                'name' => trim($nombre . ' ' . $apellido),
                'email' => $email,
                // El cast 'hashed' del modelo la guarda hasheada
                'password' => $datos['password'],
                'rol_id' => $rolCliente->id,
            ]);

            // Siempre una ficha nueva: sin verificar el correo no se toma la ficha que cargó la
            // dueña, porque alguien podría registrarse con el correo de otra clienta y ver sus citas
            $ficha = new Cliente([
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => $email,
                'fecha_registro' => now()->toDateString(),
            ]);
            $ficha->user_id = $usuario->id;
            $ficha->save();

            return $usuario;
        });

        Auth::login($usuario);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => 'Tu cuenta quedó creada.',
            'data' => [
                'pagina_inicial' => $usuario->paginaInicial(),
            ],
        ], 201);
    }

    /**
     * "¿Olvidaste tu contraseña?": manda por correo un enlace de un solo uso.
     */
    public function enviarEnlaceDeRecuperacion(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email'],
        ], [
            'email.required' => 'Escribe tu correo.',
            'email.email' => 'Ese correo no es válido.',
        ]);

        $email = strtolower(trim($datos['email']));

        try {
            Password::sendResetLink(['email' => $email]);
        } catch (TransportExceptionInterface $excepcion) {
            Log::warning('No se pudo enviar el correo de recuperación', ['error' => $excepcion->getMessage()]);
            return response()->json([
                'message' => 'No pudimos enviar el correo. Intenta de nuevo en unos minutos.',
            ], 503);
        }

        // Misma respuesta exista o no la cuenta: así no se revela quién tiene cuenta en el salón
        return response()->json([
            'message' => 'Listo. Si hay una cuenta con ese correo, en unos minutos te llega un enlace para crear una contraseña nueva.',
        ]);
    }

    /**
     * Guarda la contraseña nueva con el enlace del correo y deja la sesión iniciada.
     */
    public function restablecerContrasena(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ], [
            'password.required' => 'Escribe la contraseña nueva.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no puede superar los 72 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $email = strtolower(trim($datos['email']));
        $usuarioRestablecido = null;

        $estado = Password::reset(
            [
                'email' => $email,
                'password' => $datos['password'],
                'token' => $datos['token'],
            ],
            function (User $usuario, string $contrasenaNueva) use (&$usuarioRestablecido) {
                // El equipo necesita 12 caracteres, igual que al crear la cuenta por terminal. Se revisa
                // acá, con el enlace ya validado: antes revelaría qué correos son del equipo. El token
                // no se consume, así puede volver a intentar con una más larga
                if (!$usuario->hasRole('cliente') && mb_strlen($contrasenaNueva) < 12) {
                    throw ValidationException::withMessages([
                        'password' => ['Las cuentas del equipo necesitan una contraseña de al menos 12 caracteres.'],
                    ]);
                }

                $usuario->password = $contrasenaNueva;
                // Cierra el "seguir conectado" de otros dispositivos: la contraseña vieja ya no sirve
                $usuario->setRememberToken(Str::random(60));
                $usuario->save();

                // Y las sesiones abiertas: si alguien tenía una robada, cambiar la contraseña lo saca
                DB::table('sessions')->where('user_id', $usuario->id)->delete();

                $usuarioRestablecido = $usuario;
            }
        );

        if ($estado !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['El enlace venció o ya se usó. Pide uno nuevo.'],
            ]);
        }

        // Se entra con la misma cuenta a la que se le cambió la contraseña (no se vuelve a buscar por correo)
        $usuario = $usuarioRestablecido;
        Auth::login($usuario);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => 'Listo, tu contraseña quedó actualizada.',
            'data' => [
                'pagina_inicial' => $usuario->paginaInicial(),
            ],
        ]);
    }

    /**
     * HU-25: Cerrar sesión activa
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    /**
     * HU-25: Obtener usuario autenticado y su rol
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        $user->load(['role', 'personal']);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->role ? $user->role->nombre : null,
                'personal' => $user->personal,
                // Lo que la dueña le habilitó (todo en true para la administración)
                'permisos' => $user->permisos(),
            ],
        ]);
    }
}
