<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a ciertos roles: ->middleware('rol:admin').
 */
class VerificarRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        // Dada de baja en Personal: se le cierra la sesión acá mismo, también la de "Seguir conectado"
        if ($usuario !== null && $usuario->esProfesionalSinFichaActiva()) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            abort(403, 'Tu cuenta está desactivada. Habla con la administración del salón.');
        }

        foreach ($roles as $rol) {
            if ($usuario?->hasRole($rol)) {
                return $next($request);
            }
        }

        abort(403, 'No tienes permiso para realizar esta acción.');
    }
}
