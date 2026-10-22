<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a quien tenga alguno de los permisos indicados: ->middleware('permiso:puede_ver_clientes').
 * Con varios permisos alcanza con uno: ->middleware('permiso:puede_ver_clientes,puede_crear_reservas').
 * La administración siempre pasa (User::tienePermiso).
 */
class VerificarPermiso
{
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        $usuario = $request->user();

        if ($usuario !== null) {
            foreach ($permisos as $permiso) {
                if ($usuario->tienePermiso($permiso)) {
                    return $next($request);
                }
            }
        }

        abort(403, 'La administración no te habilitó para esta acción.');
    }
}
