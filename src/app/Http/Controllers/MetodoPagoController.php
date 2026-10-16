<?php

namespace App\Http\Controllers;

use App\Models\MetodoPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HU-21: Métodos de pago con los que el salón cobra (efectivo, transferencia, tarjeta...).
 */
class MetodoPagoController extends Controller
{
    protected const MENSAJES = [
        'nombre.required' => 'El nombre del método de pago es obligatorio.',
        'nombre.max' => 'El nombre no puede superar los 255 caracteres.',
        'nombre.unique' => 'Ya existe un método de pago con ese nombre.',
    ];

    /**
     * Listado para los selectores de cobro (cualquier rol).
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => MetodoPago::query()->select(['id', 'nombre'])->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('metodos_pago', 'nombre')],
        ], self::MENSAJES);

        $metodo = MetodoPago::create(['nombre' => trim($validated['nombre'])]);

        return response()->json([
            'message' => 'Método de pago registrado exitosamente.',
            'data' => $metodo,
        ], 201);
    }

    public function show(MetodoPago $metodos_pago): JsonResponse
    {
        return response()->json([
            'data' => $metodos_pago->loadCount('pagos'),
        ]);
    }

    public function update(Request $request, MetodoPago $metodos_pago): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('metodos_pago', 'nombre')->ignore($metodos_pago->id)],
        ], self::MENSAJES);

        $metodos_pago->update(['nombre' => trim($validated['nombre'])]);

        return response()->json([
            'message' => 'Método de pago actualizado exitosamente.',
            'data' => $metodos_pago,
        ]);
    }

    /**
     * Un método con cobros registrados no se borra: esos ingresos perderían su método.
     */
    public function destroy(MetodoPago $metodos_pago): JsonResponse
    {
        if ($metodos_pago->pagos()->exists()) {
            throw ValidationException::withMessages([
                'metodo_pago' => ['El método de pago tiene cobros registrados y no se puede eliminar.'],
            ]);
        }

        $metodos_pago->delete();

        return response()->json([
            'message' => 'Método de pago eliminado exitosamente.',
        ]);
    }
}
