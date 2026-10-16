<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClienteController extends Controller
{
    protected const MENSAJES = [
        'nombre.required' => 'El nombre es obligatorio.',
        'apellido.required' => 'El apellido es obligatorio.',
        'email.email' => 'El correo electrónico no tiene un formato válido.',
    ];

    /**
     * Listado de clientes con búsqueda por nombre, apellido, teléfono o correo.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Cliente::query();

        if ($request->filled('buscar')) {
            $termino = '%' . mb_strtolower($request->input('buscar')) . '%';
            $query->where(function ($q) use ($termino) {
                $q->whereRaw("LOWER(nombre || ' ' || apellido) LIKE ?", [$termino])
                  ->orWhereRaw('LOWER(telefono) LIKE ?', [$termino])
                  ->orWhereRaw('LOWER(email) LIKE ?', [$termino]);
            });
        }

        $clientes = $query->orderBy('nombre')->orderBy('apellido')->get();

        return response()->json([
            'data' => $clientes,
        ]);
    }

    /**
     * Alta de cliente (por ejemplo, desde la pantalla de nueva reserva).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ], self::MENSAJES);

        $cliente = Cliente::create([
            ...$validated,
            'fecha_registro' => now()->toDateString(),
        ]);

        return response()->json([
            'message' => 'Cliente registrado exitosamente.',
            'data' => $cliente,
        ], 201);
    }

    public function show(Cliente $cliente): JsonResponse
    {
        return response()->json([
            'data' => $cliente->loadCount('reservas'),
        ]);
    }

    public function update(Request $request, Cliente $cliente): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'apellido' => ['sometimes', 'required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ], self::MENSAJES);

        $cliente->update($validated);

        return response()->json([
            'message' => 'Datos del cliente actualizados exitosamente.',
            'data' => $cliente,
        ]);
    }

    /**
     * Solo se borran clientes sin reservas: el historial de atenciones debe conservarse.
     */
    public function destroy(Cliente $cliente): JsonResponse
    {
        if ($cliente->reservas()->exists()) {
            throw ValidationException::withMessages([
                'cliente' => ['El cliente tiene reservas registradas y no se puede eliminar.'],
            ]);
        }

        $cliente->delete();

        return response()->json([
            'message' => 'Cliente eliminado exitosamente.',
        ]);
    }
}
