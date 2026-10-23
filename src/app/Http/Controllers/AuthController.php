<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * HU-25: Inicio de sesión por rol
     */
    public function login(Request $request): JsonResponse
    {
        // Con Keycloak como proveedor no se deja abierta una segunda puerta con contraseña local
        if (config('services.keycloak.proveedor') === 'keycloak') {
            abort(404);
        }

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico es inválido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        $user = User::with(['role', 'personal'])->where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas no son válidas.'],
            ]);
        }

        // Iniciar sesión del usuario
        Auth::login($user);
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
            ],
        ], 200);
    }

    /**
     * HU-25: Cerrar sesión activa
     */
    public function logout(Request $request): JsonResponse
    {
        // Se lee antes de invalidar la sesión: sirve para cerrar también la sesión de Keycloak
        $idToken = null;
        if ($request->hasSession()) {
            $idToken = $request->session()->get('keycloak_id_token');
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
            // Con login local queda en null y el frontend vuelve a /login como siempre
            'url_cierre' => KeycloakController::urlCierreDeSesion($idToken),
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
