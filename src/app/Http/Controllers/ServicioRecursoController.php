<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use App\Models\StockRecurso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HU-17: Consumo estándar de insumos por servicio (plantilla que precarga cada atención).
 */
class ServicioRecursoController extends Controller
{
    public function index(Servicio $servicio): JsonResponse
    {
        return response()->json([
            'data' => $this->plantilla($servicio),
        ]);
    }

    /**
     * Reemplaza la plantilla completa; una lista vacía deja al servicio sin consumo estándar.
     * Los cambios rigen para las atenciones nuevas: las ya cargadas conservan su consumo.
     */
    public function update(Request $request, Servicio $servicio): JsonResponse
    {
        $validated = $request->validate([
            'recursos' => ['present', 'array'],
            'recursos.*.recurso_id' => ['required', 'integer', 'distinct', 'exists:stock_recursos,id'],
            'recursos.*.cantidad_utilizada' => ['required', 'numeric', 'gt:0'],
        ], [
            'recursos.present' => 'Envía la lista de insumos del servicio (puede estar vacía).',
            'recursos.array' => 'Los insumos deben enviarse en una lista.',
            'recursos.*.recurso_id.required' => 'Cada insumo debe indicar su recurso_id.',
            'recursos.*.recurso_id.distinct' => 'Un insumo no puede repetirse en la plantilla del servicio.',
            'recursos.*.recurso_id.exists' => 'El insumo seleccionado no existe.',
            'recursos.*.cantidad_utilizada.required' => 'Indica la cantidad estándar de cada insumo.',
            'recursos.*.cantidad_utilizada.numeric' => 'La cantidad estándar debe ser un número.',
            'recursos.*.cantidad_utilizada.gt' => 'La cantidad estándar debe ser mayor a cero.',
        ]);

        $servicio->recursos()->sync(
            collect($validated['recursos'])->mapWithKeys(fn ($item) => [
                (int) $item['recurso_id'] => ['cantidad_utilizada' => $item['cantidad_utilizada']],
            ])->all()
        );

        return response()->json([
            'message' => 'Consumo estándar del servicio actualizado exitosamente.',
            'data' => $this->plantilla($servicio),
        ]);
    }

    protected function plantilla(Servicio $servicio): array
    {
        return $servicio->recursos()
            ->orderBy('nombre')
            ->get()
            ->map(fn (StockRecurso $recurso) => [
                'recurso_id' => $recurso->id,
                'nombre' => $recurso->nombre,
                'unidad_medida' => $recurso->unidad_medida,
                'cantidad_utilizada' => (float) $recurso->pivot->cantidad_utilizada,
            ])
            ->all();
    }
}
