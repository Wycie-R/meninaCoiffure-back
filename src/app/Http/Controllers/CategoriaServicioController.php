<?php

namespace App\Http\Controllers;

use App\Models\CategoriaServicio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoriaServicioController extends Controller
{
    public function index(): JsonResponse
    {
        $categorias = CategoriaServicio::withCount('servicios')->get();
        return response()->json(['data' => $categorias]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:categorias_servicio,nombre'],
            'descripcion' => ['nullable', 'string'],
        ], [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.unique' => 'Ya existe una categoría con este nombre.',
        ]);

        $categoria = CategoriaServicio::create($validated);

        return response()->json([
            'message' => 'Categoría de servicio creada exitosamente.',
            'data' => $categoria,
        ], 201);
    }

    public function show(CategoriaServicio $categorias_servicio): JsonResponse
    {
        return response()->json([
            'data' => $categorias_servicio->load('servicios'),
        ]);
    }

    public function update(Request $request, CategoriaServicio $categorias_servicio): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255', 'unique:categorias_servicio,nombre,' . $categorias_servicio->id],
            'descripcion' => ['nullable', 'string'],
        ]);

        $categorias_servicio->update($validated);

        return response()->json([
            'message' => 'Categoría de servicio actualizada exitosamente.',
            'data' => $categorias_servicio,
        ]);
    }

    public function destroy(CategoriaServicio $categorias_servicio): JsonResponse
    {
        $categorias_servicio->delete();

        return response()->json([
            'message' => 'Categoría de servicio eliminada exitosamente.',
        ]);
    }
}
