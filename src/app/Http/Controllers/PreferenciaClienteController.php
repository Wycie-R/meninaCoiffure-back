<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\PreferenciaCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * HU-05: Preferencias del cliente (alergias, color, profesional preferido, productos...).
 * Las consulta y registra cualquier rol: quien atiende necesita verlas antes de empezar.
 */
class PreferenciaClienteController extends Controller
{
    protected const MENSAJES = [
        'categoria.in' => 'La categoría debe ser una de: alergia, color, profesional_preferido, producto u otro.',
        'descripcion.required' => 'La descripción de la preferencia es obligatoria.',
        'descripcion.max' => 'La descripción no puede superar los 1000 caracteres.',
    ];

    public function index(Cliente $cliente): JsonResponse
    {
        return response()->json([
            'data' => $cliente->preferencias()->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, Cliente $cliente): JsonResponse
    {
        $validated = $request->validate([
            'categoria' => ['nullable', 'string', Rule::in(PreferenciaCliente::CATEGORIAS)],
            'descripcion' => ['required', 'string', 'max:1000'],
        ], self::MENSAJES);

        $preferencia = $cliente->preferencias()->create($validated);

        return response()->json([
            'message' => 'Preferencia registrada exitosamente.',
            'data' => $preferencia,
        ], 201);
    }

    public function update(Request $request, PreferenciaCliente $preferencia): JsonResponse
    {
        $validated = $request->validate([
            'categoria' => ['sometimes', 'nullable', 'string', Rule::in(PreferenciaCliente::CATEGORIAS)],
            'descripcion' => ['sometimes', 'required', 'string', 'max:1000'],
        ], self::MENSAJES);

        $preferencia->update($validated);

        return response()->json([
            'message' => 'Preferencia actualizada exitosamente.',
            'data' => $preferencia,
        ]);
    }

    public function destroy(PreferenciaCliente $preferencia): JsonResponse
    {
        $preferencia->delete();

        return response()->json([
            'message' => 'Preferencia eliminada exitosamente.',
        ]);
    }
}
