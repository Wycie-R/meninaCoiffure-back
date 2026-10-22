<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Configuración del salón: la dueña decide qué puede hacer cada empleada.
 * Solo la administración llega aquí (rol:admin en routes/api.php).
 */
class ConfiguracionController extends Controller
{
    /**
     * Cuentas de las profesionales con sus permisos, y el catálogo de permisos con sus textos.
     */
    public function permisos(): JsonResponse
    {
        $cuentas = User::with(['personal:id,nombre,apellido', 'role'])
            ->whereHas('role', function ($consulta) {
                $consulta->where('nombre', 'profesional');
            })
            ->orderBy('name')
            ->get();

        $filas = [];
        foreach ($cuentas as $cuenta) {
            $filas[] = $this->fila($cuenta);
        }

        return response()->json([
            'data' => $filas,
            'catalogo' => $this->catalogo(),
        ]);
    }

    /**
     * Cambia uno o varios permisos de una profesional. Los que no se envían quedan como estaban.
     */
    public function actualizarPermisos(Request $request, User $user): JsonResponse
    {
        $reglas = [];
        $mensajes = [];
        foreach (User::PERMISOS as $clave => $permiso) {
            $reglas[$clave] = ['sometimes', 'boolean'];
            $mensajes["{$clave}.boolean"] = "Indica con verdadero o falso el permiso '{$permiso['nombre']}'.";
        }

        $validated = $request->validate($reglas, $mensajes);

        // La administración tiene todos los permisos siempre: no se le configuran
        if (!$user->hasRole('profesional')) {
            throw ValidationException::withMessages([
                'user' => ['Solo se configuran los permisos de las cuentas de profesionales.'],
            ]);
        }

        $user->update($validated);
        $user->load(['personal:id,nombre,apellido', 'role']);

        return response()->json([
            'message' => "Permisos de {$user->name} actualizados.",
            'data' => $this->fila($user),
        ]);
    }

    protected function fila(User $cuenta): array
    {
        $personal = null;
        if ($cuenta->personal) {
            $personal = [
                'id' => $cuenta->personal->id,
                'nombre' => $cuenta->personal->nombre,
                'apellido' => $cuenta->personal->apellido,
            ];
        }

        return [
            'id' => $cuenta->id,
            'name' => $cuenta->name,
            'email' => $cuenta->email,
            'personal' => $personal,
            'permisos' => $cuenta->permisos(),
        ];
    }

    protected function catalogo(): array
    {
        $catalogo = [];
        foreach (User::PERMISOS as $clave => $permiso) {
            $catalogo[] = [
                'clave' => $clave,
                'nombre' => $permiso['nombre'],
                'descripcion' => $permiso['descripcion'],
            ];
        }

        return $catalogo;
    }
}
