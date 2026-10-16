<?php

namespace App\Http\Controllers;

use App\Models\ConsumoRecurso;
use App\Models\StockRecurso;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HU-15: Insumos del salón con su existencia y stock mínimo de reposición.
 */
class StockRecursoController extends Controller
{
    protected const MENSAJES = [
        'nombre.required' => 'El nombre del insumo es obligatorio.',
        'nombre.max' => 'El nombre no puede superar los 255 caracteres.',
        'unidad_medida.max' => 'La unidad de medida no puede superar los 50 caracteres.',
        'cantidad_actual.numeric' => 'La cantidad inicial debe ser un número.',
        'cantidad_actual.min' => 'La cantidad inicial no puede ser negativa.',
        'cantidad_actual.prohibited' => 'La existencia no se edita directamente: registra una entrada o una salida de stock.',
        'stock_minimo.required' => 'El stock mínimo es obligatorio.',
        'stock_minimo.numeric' => 'El stock mínimo debe ser un número.',
        'stock_minimo.min' => 'El stock mínimo no puede ser negativo.',
    ];

    public function __construct(
        protected InventarioService $inventario,
    ) {}

    /**
     * Listado por nombre con la alerta de reposición; ?bajo_minimo=1 deja solo los que hay que reponer.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockRecurso::query();

        if ($request->boolean('bajo_minimo')) {
            $query->whereColumn('cantidad_actual', '<=', 'stock_minimo');
        }

        if ($request->filled('buscar')) {
            $termino = '%' . mb_strtolower($request->input('buscar')) . '%';
            $query->whereRaw('LOWER(nombre) LIKE ?', [$termino]);
        }

        return response()->json([
            'data' => $query->orderBy('nombre')->get(),
        ]);
    }

    /**
     * Ficha del insumo: servicios que lo usan en su plantilla y sus últimos movimientos.
     */
    public function show(StockRecurso $recurso): JsonResponse
    {
        $recurso->load(['servicios' => fn ($q) => $q->select('servicios.id', 'servicios.nombre')->orderBy('servicios.nombre')]);
        $recurso->setRelation('movimientos', $recurso->movimientos()->orderByDesc('fecha')->orderByDesc('id')->limit(10)->get());

        return response()->json([
            'data' => $recurso,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'unidad_medida' => ['nullable', 'string', 'max:50'],
            'cantidad_actual' => ['nullable', 'numeric', 'min:0'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
        ], self::MENSAJES);

        $validated['stock_minimo'] = $validated['stock_minimo'] ?? 0;
        $inicial = (float) ($validated['cantidad_actual'] ?? 0);

        $recurso = DB::transaction(function () use ($validated, $inicial) {
            $recurso = StockRecurso::create([...$validated, 'cantidad_actual' => 0]);

            // HU-15/16: la existencia inicial entra como movimiento para que el historial cuadre
            if ($inicial > 0) {
                $this->inventario->registrarMovimiento($recurso->id, 'entrada', $inicial, 'Existencia inicial');
            }

            return $recurso->fresh();
        });

        return response()->json([
            'message' => 'Insumo registrado exitosamente.',
            'data' => $recurso,
        ], 201);
    }

    /**
     * Edita los datos del insumo; la existencia solo cambia con movimientos (HU-16).
     */
    public function update(Request $request, StockRecurso $recurso): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'unidad_medida' => ['nullable', 'string', 'max:50'],
            'stock_minimo' => ['sometimes', 'required', 'numeric', 'min:0'],
            'cantidad_actual' => ['prohibited'],
        ], self::MENSAJES);

        $recurso->update($validated);

        return response()->json([
            'message' => 'Insumo actualizado exitosamente.',
            'data' => $recurso->fresh(),
        ]);
    }

    /**
     * Un insumo que ya figura en consumos de atenciones no se borra: esos registros son historial.
     */
    public function destroy(StockRecurso $recurso): JsonResponse
    {
        if (ConsumoRecurso::where('recurso_id', $recurso->id)->exists()) {
            throw ValidationException::withMessages([
                'recurso' => ['El insumo ya se usó en atenciones registradas y no se puede eliminar.'],
            ]);
        }

        // Sus movimientos y su presencia en plantillas de servicio se borran en cascada
        $recurso->delete();

        return response()->json([
            'message' => 'Insumo eliminado exitosamente.',
        ]);
    }
}
