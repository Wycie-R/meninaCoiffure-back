<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detrás de Caddy y HAProxy: se confía en X-Forwarded-* para saber que la petición llegó por
        // HTTPS (si no, los assets y los enlaces de los correos salen con http://) y cuál es la IP real
        // de la clienta (el límite de intentos del login va por IP). Es seguro porque nginx solo es
        // alcanzable desde la red interna de Docker, y Caddy descarta los X-Forwarded-* que manda el cliente
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Quien ya tiene sesión y abre /login va a su página: el equipo al panel, una clienta al salón
        $middleware->redirectUsersTo(function (Request $request) {
            return $request->user()->paginaInicial();
        });

        $middleware->alias([
            'rol' => \App\Http\Middleware\VerificarRol::class,
            'permiso' => \App\Http\Middleware\VerificarPermiso::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
