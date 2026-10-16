<?php

namespace App\Http\Controllers;

use App\Models\MovimientoStock;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * HU-16: Entradas (compras) y salidas (uso, vencimiento, rotura) de insumos.
 */
class MovimientoStockController extends Controller
{
    public function __construct(
        protected InventarioService $inventario,
    ) {}

    /**
     * Historial paginado, del más reciente al más antiguo.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'recurso_id' => ['nullable', 'integer'],
            'tipo' => ['nullable', 'string', Rule::in(MovimientoStock::TIPOS)],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'tipo.in' => 'El tipo de movimiento debe ser entrada o salida.',
            'fecha_desde.date' => 'La fecha inicial no tiene un formato válido.',
            'fecha_hasta.date' => 'La fecha final no tiene un formato válido.',
            'fecha_hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'per_page.max' => 'Se pueden pedir como máximo 100 movimientos por página.',
        ]);

        $movimientos = MovimientoStock::with('recurso')
            ->when($filtros['recurso_id'] ?? null, fn ($q, $id) => $q->where('recurso_id', $id))
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['fecha_desde'] ?? null, fn ($q, $fecha) => $q->whereDate('fecha', '>=', $fecha))
            ->when($filtros['fecha_hasta'] ?? null, fn ($q, $fecha) => $q->whereDate('fecha', '<=', $fecha))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($filtros['per_page'] ?? 15)
            ->withQueryString();

        return response()->json($movimientos);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recurso_id' => ['required', 'integer', 'exists:stock_recursos,id'],
            'tipo' => ['required', 'string', Rule::in(MovimientoStock::TIPOS)],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'fecha' => ['nullable', 'date', 'before_or_equal:now'],
        ], [
            'recurso_id.required' => 'El insumo es obligatorio.',
            'recurso_id.exists' => 'El insumo seleccionado no existe.',
            'tipo.required' => 'El tipo de movimiento es obligatorio.',
            'tipo.in' => 'El tipo de movimiento debe ser entrada o salida.',
            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.numeric' => 'La cantidad debe ser un número.',
            'cantidad.gt' => 'La cantidad debe ser mayor a cero.',
            'motivo.max' => 'El motivo no puede superar los 255 caracteres.',
            'fecha.date' => 'La fecha no tiene un formato válido.',
            'fecha.before_or_equal' => 'La fecha del movimiento no puede ser futura.',
        ]);

        $movimiento = $this->inventario->registrarMovimiento(
            (int) $validated['recurso_id'],
            $validated['tipo'],
            (float) $validated['cantidad'],
            $validated['motivo'] ?? null,
            $validated['fecha'] ?? null,
        );

        return response()->json([
            'message' => $validated['tipo'] === 'entrada'
                ? 'Entrada de stock registrada exitosamente.'
                : 'Salida de stock registrada exitosamente.',
            'data' => $movimiento,
        ], 201);
    }
}
